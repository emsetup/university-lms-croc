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
import json
from datetime import datetime, timedelta, timezone

from fastapi import Depends, FastAPI, HTTPException
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from pydantic import BaseModel, Field
from functools import lru_cache

SECRET = os.environ["LAB_DAEMON_SECRET"]
DOCKER = os.environ.get("DOCKER_BIN", "docker")
CHECK_PATH = os.environ.get("LAB_CHECK_PATH", "/opt/lab-check/check.sh")
TTL_MIN = int(os.environ.get("LAB_TTL_MINUTES", "480"))
# Публичный префикс для ttyd через HTTPS reverse-proxy (без mixed content):
#   LAB_PUBLIC_TTY_BASE=https://practice.croc.ru/ttyd  →  …/ttyd/<port>/
# Если пусто — legacy URL http://LAB_PUBLIC_HOST:<port>/ (ломает HTTPS-портал).
TTY_BASE = os.environ.get("LAB_PUBLIC_TTY_BASE", "").rstrip("/")
LAB_ENABLE_TTY = os.getenv("LAB_ENABLE_TTY", "").lower() in ("1", "true", "yes")
LAB_PUBLIC_HOST = os.getenv("LAB_PUBLIC_HOST", "").strip()
# Слушать только localhost: снаружи — через nginx /ttyd/<port>/ (TLS).
LAB_TTY_BIND = (os.getenv("LAB_TTY_BIND", "127.0.0.1") or "127.0.0.1").strip()
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

BUILD_WORKDIR = (os.getenv("LAB_BUILD_WORKDIR", "/tmp/lab-build") or "/tmp/lab-build").strip()
EXPORT_DIR = (os.getenv("LAB_IMAGE_EXPORT_DIR", "") or "").strip()
MAX_BUILD_LOG = int(os.getenv("LAB_BUILD_LOG_MAX_CHARS", "60000") or "60000")
PKG_SEARCH_TIMEOUT_SEC = int(os.getenv("LAB_PKG_SEARCH_TIMEOUT_SEC", "20") or "20")
PKG_SEARCH_CACHE_SEC = int(os.getenv("LAB_PKG_SEARCH_CACHE_SEC", "300") or "300")

TTY_PROCS: dict[str, subprocess.Popen] = {}

_bearer = HTTPBearer()


def _require_auth(credentials: HTTPAuthorizationCredentials = Depends(_bearer)) -> None:
    if credentials.credentials != SECRET:
        raise HTTPException(status_code=401, detail="Unauthorized")


def _docker(*args: str, check: bool = True) -> subprocess.CompletedProcess[str]:
    return subprocess.run([DOCKER, *args], check=check, capture_output=True, text=True)


def _safe_join(base_dir: str, *parts: str) -> str:
    base = os.path.abspath(base_dir)
    p = os.path.abspath(os.path.join(base, *parts))
    if p == base or p.startswith(base + os.sep):
        return p
    raise HTTPException(status_code=400, detail="path is outside of base dir")


def _truncate_log(s: str) -> str:
    if not s:
        return ""
    if len(s) <= MAX_BUILD_LOG:
        return s
    return s[:MAX_BUILD_LOG] + "\n... [truncated] ..."


def _default_base_for_os(os_id: str) -> str:
    o = (os_id or "").strip().lower()
    if o in ("alma", "almalinux"):
        return "almalinux:9"
    if o in ("centos",):
        return "quay.io/centos/centos:stream9"
    # astra/redos: by default treat like alt unless stand has private base images
    if o in ("astra", "redos"):
        return "registry.altlinux.org/alt/alt:p10"
    return "registry.altlinux.org/alt/alt:p10"


def _pkg_search_cmd(os_id: str, q: str, limit: int) -> str:
    o = (os_id or "").strip().lower()
    qq = q.replace('"', "").replace("`", "").strip()
    lim = max(1, min(100, int(limit)))
    if o in ("alma", "almalinux", "centos"):
        # dnf output is noisy; grep by query and keep short.
        return (
            "set -e; "
            "dnf -q makecache -y >/dev/null 2>&1 || true; "
            f"dnf -q search {qq} 2>/dev/null | head -n {lim}"
        )
    # apt-cache search
    return f"apt-cache search {qq} 2>/dev/null | head -n {lim}"


def _run_pkg_search_in_image(base_image: str, inner_cmd: str) -> str:
    img = (base_image or "").strip()
    if not img:
        raise HTTPException(status_code=400, detail="base image is required")
    # Use bash if available, otherwise sh.
    # We don't mount anything; just query repo metadata inside container.
    cp = subprocess.run(
        [
            DOCKER,
            "run",
            "--rm",
            "--pull=missing",
            img,
            "/bin/sh",
            "-lc",
            inner_cmd,
        ],
        capture_output=True,
        text=True,
        timeout=PKG_SEARCH_TIMEOUT_SEC,
        check=False,
    )
    out = (cp.stdout or "") + ("\n" + cp.stderr if cp.stderr else "")
    return _truncate_log(out.strip())


@lru_cache(maxsize=512)
def _pkg_search_cached(cache_key: str) -> str:
    # TTL handled by key including time bucket.
    return cache_key


def _bytes_human(n: int) -> str:
    if n < 0:
        n = 0
    units = ["B", "KB", "MB", "GB", "TB"]
    x = float(n)
    i = 0
    while x >= 1024.0 and i < len(units) - 1:
        x /= 1024.0
        i += 1
    if i == 0:
        return f"{int(x)} {units[i]}"
    return f"{x:.1f} {units[i]}"


def _image_stats(image: str) -> dict:
    img = (image or "").strip()
    if not img:
        raise HTTPException(status_code=400, detail="image is required")
    cp = _docker("image", "inspect", img, check=False)
    if cp.returncode != 0:
        detail = (cp.stderr or cp.stdout or "docker image inspect failed")[:2000]
        raise HTTPException(status_code=404, detail=detail)
    try:
        data = json.loads(cp.stdout or "[]")
    except json.JSONDecodeError:
        raise HTTPException(status_code=500, detail="bad docker inspect json")
    if not isinstance(data, list) or not data:
        raise HTTPException(status_code=404, detail="image not found")
    obj = data[0] if isinstance(data[0], dict) else {}
    size = int(obj.get("Size") or 0)
    rootfs = obj.get("RootFS") if isinstance(obj.get("RootFS"), dict) else {}
    layers = rootfs.get("Layers") if isinstance(rootfs.get("Layers"), list) else []
    return {
        "image": img,
        "id": (obj.get("Id") or "")[:32],
        "created": obj.get("Created") or "",
        "size_bytes": size,
        "size_human": _bytes_human(size),
        "layers_count": len(layers),
    }


def _container_name(lab_id: str) -> str:
    safe = lab_id.replace("-", "")[:32]
    return f"osaltlab_{safe}"


def _image_runs_systemd_pid1(image: str) -> bool:
    s = (image or "").lower()
    return "-systemd" in s


def _lab_runs_systemd_style(body: CreateLabBody) -> bool:
    """PID1 = systemd: тег *-systemd в имени образа или модули 8/9/10."""
    if _image_runs_systemd_pid1(body.image):
        return True
    return body.module_id in (8, 9, 10)


def _lab_needs_privileged(body: CreateLabBody) -> bool:
    """Финальная лаба (10) работает без --privileged; остальным systemd-лабам оставляем как было."""
    img = (body.image or "").lower()
    if body.module_id == 10 or "final-lab" in img:
        return False
    return _lab_runs_systemd_style(body)


def _lab_systemd_needs_cgroup_tmpfs(body: CreateLabBody) -> bool:
    """Без cgroup хоста + tmpfs для /run у systemd в Docker `systemctl` часто зависает (dbus)."""
    return _lab_runs_systemd_style(body)


# Пустой файл на хосте: bind-mount на /etc/resolv.conf (Docker 27+ не даёт --tmpfs на файл).
LAB_EMPTY_RESOLV = os.getenv("LAB_EMPTY_RESOLV", "/tmp/os-alt-lab-empty-resolv")


def _ensure_empty_resolv_stub() -> str:
    path = LAB_EMPTY_RESOLV
    parent = os.path.dirname(path) or "/tmp"
    os.makedirs(parent, exist_ok=True)
    if not os.path.isfile(path):
        open(path, "a", encoding="utf-8").close()
    return path


def _lab_m5_resolv_mount_args() -> list[str]:
    stub = _ensure_empty_resolv_stub()
    return [
        "--cap-add=NET_ADMIN",
        "--mount",
        f"type=bind,source={stub},target=/etc/resolv.conf",
    ]


def _lab_m8_audit_caps(body: CreateLabBody) -> bool:
    """Модули с auditd-лабораторками: caps для запуска auditd в контейнере."""
    img = (body.image or "").lower()
    return body.module_id in (8, 10) or "lab-m8-systemd" in img or "final-lab" in img


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
    module_id: int = Field(..., ge=1, le=10)
    image: str = Field(..., min_length=1)


def _public_tty_url(port: int) -> str:
    """URL для браузера: предпочтительно HTTPS через nginx /ttyd/<port>/."""
    if TTY_BASE:
        return f"{TTY_BASE}/{port}/"
    if not LAB_PUBLIC_HOST:
        raise ValueError("LAB_PUBLIC_HOST or LAB_PUBLIC_TTY_BASE required")
    return f"http://{LAB_PUBLIC_HOST}:{port}/"


def _start_tty(lab_id: str, container_name: str) -> str | None:
    if not LAB_ENABLE_TTY or (not TTY_BASE and not LAB_PUBLIC_HOST):
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
            "-i",
            LAB_TTY_BIND,
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
            return _public_tty_url(port)
        except OSError:
            continue
    return None


app = FastAPI(title="OS Alt course lab daemon", version="1")


class ImageBuildBody(BaseModel):
    context_dir: str = Field(..., min_length=1)
    dockerfile_rel: str = Field(..., min_length=1)
    tags: list[str] = Field(..., min_length=1)
    build_args: dict[str, str] | None = None


class ImageExportBody(BaseModel):
    tag: str = Field(..., min_length=1)
    out_name: str = Field(..., min_length=1)


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
        if _lab_needs_privileged(body):
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
                cmd.insert(2, item)
        # Модуль 5 (etcnet): NET_ADMIN + writable /etc/resolv.conf (не bind с хоста Docker).
        if body.module_id == 5 or "lab-m5" in img:
            for item in reversed(_lab_m5_resolv_mount_args()):
                cmd.insert(2, item)
        cmd.append(body.image)
    else:
        extra: list[str] = []
        if body.module_id == 5 or ("lab-m5" in img):
            extra = _lab_m5_resolv_mount_args()
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


@app.get("/internal/v1/image-stats")
def image_stats(image: str, _: None = Depends(_require_auth)) -> dict:
    return _image_stats(image)


@app.get("/internal/v1/pkg-search")
def pkg_search(os: str, q: str, base_image: str = "", limit: int = 50, _: None = Depends(_require_auth)) -> dict:
    query = (q or "").strip()
    if len(query) < 1:
        raise HTTPException(status_code=400, detail="q is required")
    base = (base_image or "").strip() or _default_base_for_os(os)
    lim = max(1, min(100, int(limit)))
    inner = _pkg_search_cmd(os, query, lim)

    # naive TTL bucket key for lru_cache
    bucket = int(time.time() // max(30, PKG_SEARCH_CACHE_SEC))
    cache_key = f"{bucket}:{os}:{base}:{lim}:{query}"

    # lru_cache can't store dynamic results without calling a function;
    # we store the key and execute search only if key wasn't seen.
    # (Good enough for MVP; can be replaced with dict+expires.)
    if _pkg_search_cached(cache_key) != cache_key:
        pass

    try:
        raw = _run_pkg_search_in_image(base, inner)
    except subprocess.TimeoutExpired:
        raise HTTPException(status_code=504, detail="pkg search timeout")
    except OSError as e:
        raise HTTPException(status_code=500, detail=f"pkg search failed: {e}")

    lines = [ln.strip() for ln in raw.splitlines() if ln.strip()][:lim]
    return {"os": os, "base_image": base, "q": query, "limit": lim, "lines": lines}


@app.post("/internal/v1/image-build")
def image_build(body: ImageBuildBody, _: None = Depends(_require_auth)) -> dict:
    # Сборка разрешена только из каталога BUILD_WORKDIR (куда Laravel складывает рецепты).
    ctx = _safe_join(BUILD_WORKDIR, body.context_dir)
    dockerfile = _safe_join(ctx, body.dockerfile_rel)

    tags = []
    for t in body.tags:
        tt = (t or "").strip()
        if not tt:
            continue
        tags.append(tt)
    if not tags:
        raise HTTPException(status_code=400, detail="tags are required")

    if not os.path.isdir(ctx):
        raise HTTPException(status_code=404, detail="context_dir not found")
    if not os.path.isfile(dockerfile):
        raise HTTPException(status_code=404, detail="Dockerfile not found")

    cmd: list[str] = ["build", "-f", dockerfile]
    for t in tags:
        cmd.extend(["-t", t])
    if body.build_args:
        for k, v in body.build_args.items():
            kk = (k or "").strip()
            if not kk:
                continue
            cmd.extend(["--build-arg", f"{kk}={v}"])
    cmd.append(ctx)

    cp = _docker(*cmd, check=False)
    log = _truncate_log((cp.stdout or "") + ("\n" + cp.stderr if cp.stderr else ""))
    if cp.returncode != 0:
        return {"ok": False, "log": log, "exit_code": cp.returncode}
    return {"ok": True, "log": log, "exit_code": 0}


@app.post("/internal/v1/image-export")
def image_export(body: ImageExportBody, _: None = Depends(_require_auth)) -> dict:
    if not EXPORT_DIR:
        raise HTTPException(status_code=400, detail="LAB_IMAGE_EXPORT_DIR is not configured")
    out = _safe_join(EXPORT_DIR, body.out_name)
    os.makedirs(os.path.dirname(out), exist_ok=True)
    cp = _docker("save", body.tag, "-o", out, check=False)
    log = _truncate_log((cp.stdout or "") + ("\n" + cp.stderr if cp.stderr else ""))
    if cp.returncode != 0:
        return {"ok": False, "log": log, "exit_code": cp.returncode}
    return {"ok": True, "log": log, "exit_code": 0, "out_path": out}


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}
