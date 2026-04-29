"""
Lab daemon: контейнеры с меткой lab.course.os_alt_id=<uuid>.
Опционально поднимает ttyd на случайном порту для веб-терминала.
"""

from __future__ import annotations

import os
import random
import re
import shutil
import subprocess
import time
import uuid
from datetime import datetime, timedelta, timezone

from fastapi import Depends, FastAPI, HTTPException
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from pydantic import BaseModel, Field

SECRET = os.environ["LAB_DAEMON_SECRET"]
DOCKER = os.environ.get("DOCKER_BIN", "docker")
CHECK_PATH = os.environ.get("LAB_CHECK_PATH", "/opt/lab-check/check.sh")
TTL_MIN = int(os.environ.get("LAB_TTL_MINUTES", "480"))
TTY_BASE = os.environ.get("LAB_PUBLIC_TTY_BASE", "").rstrip("/")
LAB_ENABLE_TTY = os.getenv("LAB_ENABLE_TTY", "").lower() in ("1", "true", "yes")
LAB_PUBLIC_HOST = os.getenv("LAB_PUBLIC_HOST", "").strip()
PORT_MIN = int(os.getenv("LAB_TTY_PORT_MIN", "40000"))
PORT_MAX = int(os.getenv("LAB_TTY_PORT_MAX", "41000"))
LAB_SHELL_USER = (os.getenv("LAB_SHELL_USER", "student") or "student").strip()
LAB_SHELL_WORKDIR = (os.getenv("LAB_SHELL_WORKDIR", "/home/student") or "/home/student").strip()
LAB_SHELL_HOME = (os.getenv("LAB_SHELL_HOME", LAB_SHELL_WORKDIR) or LAB_SHELL_WORKDIR).strip()
LAB_TTY_FONT = (
    os.getenv(
        "LAB_TTY_FONT",
        "DejaVu Sans Mono, Liberation Mono, Noto Sans Mono, Consolas, monospace",
    )
    or "monospace"
).strip()
_raw_lang = (os.getenv("LAB_CONTAINER_LANG", "C") or "C").strip()
LAB_CONTAINER_LANG = _raw_lang if re.match(r"^[A-Za-z0-9_.@-]+$", _raw_lang) else "C"

TTY_PROCS: dict[str, subprocess.Popen] = {}

_bearer = HTTPBearer()


def _require_auth(credentials: HTTPAuthorizationCredentials = Depends(_bearer)) -> None:
    if credentials.credentials != SECRET:
        raise HTTPException(status_code=401, detail="Unauthorized")


def _docker(*args: str, check: bool = True) -> subprocess.CompletedProcess[str]:
    return subprocess.run([DOCKER, *args], check=check, capture_output=True, text=True)


def _container_name(lab_id: str) -> str:
    safe = lab_id.replace("-", "")[:32]
    return f"osaltlab_{safe}"


def _image_runs_systemd_pid1(image: str) -> bool:
    s = (image or "").lower()
    return "-systemd" in s


def _lab_runs_systemd_style(body: CreateLabBody) -> bool:
    """PID1 = systemd: тег *-systemd в имени образа или модули 8 (auditd), 9 (polkit)."""
    if _image_runs_systemd_pid1(body.image):
        return True
    return body.module_id in (8, 9)


def _lab_systemd_needs_cgroup_tmpfs(body: CreateLabBody) -> bool:
    """Без cgroup хоста + tmpfs для /run у systemd в Docker `systemctl` часто зависает (dbus)."""
    return _lab_runs_systemd_style(body)


def _lab_m8_audit_caps(body: CreateLabBody) -> bool:
    """Только модуль 8 / образ m8-systemd — capabilities для auditd в контейнере."""
    img = (body.image or "").lower()
    return body.module_id == 8 or "lab-m8-systemd" in img


def _stop_tty(lab_id: str) -> None:
    proc = TTY_PROCS.pop(lab_id, None)
    if proc is None:
        return
    if proc.poll() is not None:
        return
    try:
        proc.terminate()
        proc.wait(timeout=10)
    except subprocess.TimeoutExpired:
        proc.kill()
    except OSError:
        pass


class CreateLabBody(BaseModel):
    learner_id: int = Field(..., ge=1)
    module_id: int = Field(..., ge=1, le=9)
    image: str = Field(..., min_length=1)


def _start_tty(lab_id: str, container_name: str) -> str | None:
    if not LAB_ENABLE_TTY or not LAB_PUBLIC_HOST:
        return None
    if not shutil.which("ttyd"):
        return None
    for _ in range(16):
        port = random.randint(PORT_MIN, PORT_MAX)
        lang = LAB_CONTAINER_LANG
        home = LAB_SHELL_HOME
        user = LAB_SHELL_USER
        inner = (
            f"export HOME={home} USER={user} LOGNAME={user} "
            f"LANG={lang} LC_ALL={lang} LC_CTYPE={lang} LC_MESSAGES={lang}; "
            f"exec /bin/bash -il"
        )
        cmd = [
            "ttyd",
            "-W",
            "-p",
            str(port),
            "-b",
            "/",
            "-t",
            f"fontFamily={LAB_TTY_FONT}",
            "-t",
            "unicodeVersion=11",
            DOCKER,
            "exec",
            "-it",
            "-e",
            f"HOME={home}",
            "-e",
            f"USER={user}",
            "-e",
            f"LOGNAME={user}",
            "-e",
            f"LANG={lang}",
            "-e",
            f"LC_ALL={lang}",
            "-e",
            f"LC_CTYPE={lang}",
            "-e",
            "TERM=xterm-256color",
            "-u",
            user,
            "-w",
            LAB_SHELL_WORKDIR,
            container_name,
            "/bin/bash",
            "-c",
            inner,
        ]
        try:
            proc = subprocess.Popen(
                cmd,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                start_new_session=True,
            )
            time.sleep(0.35)
            if proc.poll() is not None:
                continue
            TTY_PROCS[lab_id] = proc
            return f"http://{LAB_PUBLIC_HOST}:{port}/"
        except OSError:
            continue
    return None


app = FastAPI(title="OS Alt course lab daemon", version="1")


@app.post("/internal/v1/lab")
def create_lab(body: CreateLabBody, _: None = Depends(_require_auth)) -> dict:
    lab_id = str(uuid.uuid4())
    name = _container_name(lab_id)
    _docker("rm", "-f", name, check=False)
    exp = datetime.now(timezone.utc) + timedelta(minutes=TTL_MIN)
    cmd = [
        "run",
        "-d",
        "--name",
        name,
        "--label",
        "course=os-alt-lab",
        "--label",
        f"learner_id={body.learner_id}",
        "--label",
        f"module_id={body.module_id}",
        "--label",
        f"lab.course.os_alt_id={lab_id}",
    ]
    img = (body.image or "").lower()
    if _lab_runs_systemd_style(body):
        cmd.insert(2, "--privileged")
        if _lab_systemd_needs_cgroup_tmpfs(body):
            extras_sysd = (
                "--cgroupns=host",
                "-v",
                "/sys/fs/cgroup:/sys/fs/cgroup:rw",
                "--tmpfs",
                "/tmp:exec,mode=1777",
                "--tmpfs",
                "/run",
                "--tmpfs",
                "/run/lock",
            )
            for item in reversed(extras_sysd):
                cmd.insert(3, item)
        # Только auditd-лаба: caps (cgroup/tmpfs выше — для любого systemd-контейнера).
        if _lab_m8_audit_caps(body):
            for cap in reversed(["AUDIT_CONTROL", "AUDIT_WRITE"]):
                cmd.insert(3, f"--cap-add={cap}")
        # Модуль 5 (etcnet): NET_ADMIN + tmpfs на /etc/resolv.conf — иначе Docker даёт
        # bind-mount на resolv.conf и скрипты etcnet при ifup падают с «rm: … Device or resource busy».
        if body.module_id == 5 or "lab-m5" in img:
            for item in reversed(
                [
                    "--cap-add=NET_ADMIN",
                    "/etc/resolv.conf:rw,nosuid,noexec,size=64k",
                    "--tmpfs",
                ]
            ):
                cmd.insert(3, item)
        cmd.append(body.image)
    else:
        extra: list[str] = []
        if body.module_id == 5 or ("lab-m5" in img):
            extra = [
                "--cap-add=NET_ADMIN",
                "--tmpfs",
                "/etc/resolv.conf:rw,nosuid,noexec,size=64k",
            ]
        if "lab-m8" in img:
            extra.append("--privileged")
            extra.extend(["--cap-add=AUDIT_CONTROL", "--cap-add=AUDIT_WRITE"])
        cmd.extend(extra)
        cmd.extend([body.image, "sleep", "infinity"])
    try:
        cp = _docker(*cmd)
    except subprocess.CalledProcessError as e:
        detail = (e.stderr or e.stdout or "docker run failed")[:2000]
        raise HTTPException(status_code=500, detail=detail) from e
    cid = (cp.stdout or "").strip()[:12]
    terminal_url = _start_tty(lab_id, name)
    if terminal_url is None and TTY_BASE:
        terminal_url = f"{TTY_BASE}/?lab={lab_id}"
    return {
        "lab_id": lab_id,
        "container_id": cid or name,
        "terminal_url": terminal_url,
        "expires_at": exp.isoformat(),
    }


def _cid_for_lab(lab_id: str) -> str:
    cp = _docker(
        "ps",
        "-q",
        "-f",
        f"label=lab.course.os_alt_id={lab_id}",
        check=False,
    )
    lines = (cp.stdout or "").strip().split("\n")
    cid = (lines[0].strip() if lines else "") or ""
    if not cid:
        raise HTTPException(status_code=404, detail="Lab not found or stopped")
    return cid


def _read_bash_history_by_cid(cid: str) -> str:
    hist_file = f"{LAB_SHELL_WORKDIR}/.bash_history"
    inner = f"if [ -r '{hist_file}' ]; then cat '{hist_file}'; fi"
    cp = subprocess.run(
        [DOCKER, "exec", "-u", LAB_SHELL_USER, cid, "/bin/bash", "-lc", inner],
        capture_output=True,
        text=True,
        check=False,
    )
    return (cp.stdout or "").strip()


@app.get("/internal/v1/lab/{lab_id}/bash-history")
def bash_history(lab_id: str, _: None = Depends(_require_auth)) -> dict:
    cid = _cid_for_lab(lab_id)
    return {"bash_history": _read_bash_history_by_cid(cid)}


@app.post("/internal/v1/lab/{lab_id}/check")
def check_lab(lab_id: str, _: None = Depends(_require_auth)) -> dict:
    cid = _cid_for_lab(lab_id)
    # Запускаем check.sh напрямую (shebang #!/bin/bash), без «bash /path» — так надёжнее для set -u и PATH.
    cp = subprocess.run(
        [DOCKER, "exec", cid, CHECK_PATH],
        capture_output=True,
        text=True,
    )
    out = (cp.stdout or "")
    if cp.stderr:
        out = out + "\n" + cp.stderr
    passed = cp.returncode == 0
    bash_hist = _read_bash_history_by_cid(cid)
    return {
        "exit_code": cp.returncode,
        "stdout": out.strip(),
        "passed": passed,
        "bash_history": bash_hist,
    }


@app.delete("/internal/v1/lab/{lab_id}")
def delete_lab(lab_id: str, _: None = Depends(_require_auth)) -> dict:
    _stop_tty(lab_id)
    name = _container_name(lab_id)
    subprocess.run([DOCKER, "rm", "-f", name], capture_output=True, text=True)
    return {"ok": True}


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}
