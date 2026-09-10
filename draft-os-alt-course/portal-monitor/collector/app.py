#!/usr/bin/env python3
"""Push-heartbeat collector for practice.croc.ru → Telegram alerts."""

from __future__ import annotations

import asyncio
import json
import os
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import httpx
from fastapi import FastAPI, Header, HTTPException, Request

app = FastAPI(title="Portal heartbeat collector", docs_url=None, redoc_url=None)

STATE_PATH = Path(os.environ.get("PORTAL_MONITOR_STATE", "/var/lib/portal-monitor/state.json"))
HEARTBEAT_TOKEN = os.environ.get("HEARTBEAT_TOKEN", "").strip()
TELEGRAM_BOT_TOKEN = os.environ.get("TELEGRAM_BOT_TOKEN", "").strip()
TELEGRAM_CHAT_ID = os.environ.get("TELEGRAM_CHAT_ID", "").strip()
SILENCE_SECONDS = int(os.environ.get("SILENCE_SECONDS", "180"))
WATCH_INTERVAL = int(os.environ.get("WATCH_INTERVAL", "30"))
REMINDER_SECONDS = int(os.environ.get("REMINDER_SECONDS", "21600"))  # 6h while down
HOST_LABEL = os.environ.get("HOST_LABEL", "practice-croc")

_lock = asyncio.Lock()
_watcher_task: asyncio.Task | None = None


def _now() -> float:
    return time.time()


def _iso(ts: float | None = None) -> str:
    return datetime.fromtimestamp(ts or _now(), tz=timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def _default_state() -> dict[str, Any]:
    return {
        "last_seen": None,
        "last_payload": None,
        "status": "unknown",  # unknown | ok | degraded | down
        "alert_state": "unknown",
        "last_alert_at": None,
        "last_reminder_at": None,
    }


def _load_state() -> dict[str, Any]:
    if not STATE_PATH.is_file():
        return _default_state()
    try:
        data = json.loads(STATE_PATH.read_text(encoding="utf-8"))
        if not isinstance(data, dict):
            return _default_state()
        base = _default_state()
        base.update(data)
        return base
    except Exception:
        return _default_state()


def _save_state(state: dict[str, Any]) -> None:
    STATE_PATH.parent.mkdir(parents=True, exist_ok=True)
    tmp = STATE_PATH.with_suffix(".tmp")
    tmp.write_text(json.dumps(state, ensure_ascii=False, indent=2), encoding="utf-8")
    tmp.replace(STATE_PATH)


def _require_token(authorization: str | None) -> None:
    if not HEARTBEAT_TOKEN:
        raise HTTPException(503, "HEARTBEAT_TOKEN not configured")
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(401, "Missing Bearer token")
    if authorization[7:].strip() != HEARTBEAT_TOKEN:
        raise HTTPException(403, "Invalid token")


async def _telegram_send(text: str) -> bool:
    if not TELEGRAM_BOT_TOKEN or not TELEGRAM_CHAT_ID:
        return False
    url = f"https://api.telegram.org/bot{TELEGRAM_BOT_TOKEN}/sendMessage"
    payload = {
        "chat_id": TELEGRAM_CHAT_ID,
        "text": text,
        "disable_web_page_preview": True,
    }
    try:
        async with httpx.AsyncClient(timeout=15.0) as client:
            r = await client.post(url, json=payload)
            return r.status_code == 200
    except Exception:
        return False


def _failed_checks_summary(payload: dict[str, Any] | None) -> str:
    if not payload:
        return "no payload"
    checks = payload.get("checks") or {}
    if not isinstance(checks, dict):
        return "checks invalid"
    bad: list[str] = []
    for name, val in checks.items():
        if isinstance(val, dict):
            if val.get("ok") is False:
                bad.append(f"{name}={val}")
            elif "status" in val:
                st = val.get("status")
                if not isinstance(st, int) or st >= 500 or st == 0:
                    bad.append(f"{name} status={st}")
        elif isinstance(val, str) and val not in ("active", "ok", "running"):
            bad.append(f"{name}={val}")
        elif val is False:
            bad.append(f"{name}=false")
    return ", ".join(bad) if bad else "ok=false"


def _derive_status(state: dict[str, Any]) -> str:
    last_seen = state.get("last_seen")
    if last_seen is None:
        return "unknown"
    age = _now() - float(last_seen)
    if age > SILENCE_SECONDS:
        return "down"
    payload = state.get("last_payload") or {}
    if isinstance(payload, dict) and payload.get("ok") is False:
        return "degraded"
    return "ok"


async def _maybe_alert(state: dict[str, Any], new_status: str, reason: str) -> dict[str, Any]:
    prev = state.get("alert_state") or "unknown"
    now = _now()
    if new_status == prev:
        # Reminder while still down/degraded
        if new_status in ("down", "degraded") and REMINDER_SECONDS > 0:
            last_rem = state.get("last_reminder_at") or state.get("last_alert_at")
            if last_rem is None or (now - float(last_rem)) >= REMINDER_SECONDS:
                host = ((state.get("last_payload") or {}) or {}).get("host_id") or HOST_LABEL
                text = (
                    f"⏰ REMINDER [{new_status.upper()}] {host}\n"
                    f"reason: {reason}\n"
                    f"time: {_iso(now)}"
                )
                await _telegram_send(text)
                state["last_reminder_at"] = now
        return state

    # Skip noisy unknown→ok on first boot without prior alert
    if prev == "unknown" and new_status == "ok":
        state["alert_state"] = new_status
        state["status"] = new_status
        return state

    host = ((state.get("last_payload") or {}) or {}).get("host_id") or HOST_LABEL
    emoji = {"ok": "✅", "degraded": "⚠️", "down": "🔴", "unknown": "❓"}.get(new_status, "•")
    text = (
        f"{emoji} {new_status.upper()} {host}\n"
        f"reason: {reason}\n"
        f"prev: {prev} → {new_status}\n"
        f"time: {_iso(now)}"
    )
    await _telegram_send(text)
    state["alert_state"] = new_status
    state["status"] = new_status
    state["last_alert_at"] = now
    state["last_reminder_at"] = now
    return state


async def _watcher_loop() -> None:
    while True:
        try:
            async with _lock:
                state = _load_state()
                new_status = _derive_status(state)
                if new_status == "down":
                    age = None
                    if state.get("last_seen") is not None:
                        age = int(_now() - float(state["last_seen"]))
                    reason = f"no heartbeat for {age}s (limit {SILENCE_SECONDS}s)" if age is not None else "never received heartbeat"
                    state = await _maybe_alert(state, "down", reason)
                elif new_status == "degraded":
                    reason = _failed_checks_summary(state.get("last_payload"))
                    state = await _maybe_alert(state, "degraded", reason)
                elif new_status == "ok":
                    state = await _maybe_alert(state, "ok", "heartbeats healthy")
                else:
                    state["status"] = new_status
                _save_state(state)
        except Exception:
            pass
        await asyncio.sleep(WATCH_INTERVAL)


@app.on_event("startup")
async def _startup() -> None:
    global _watcher_task
    STATE_PATH.parent.mkdir(parents=True, exist_ok=True)
    if not STATE_PATH.is_file():
        _save_state(_default_state())
    _watcher_task = asyncio.create_task(_watcher_loop())


@app.on_event("shutdown")
async def _shutdown() -> None:
    global _watcher_task
    if _watcher_task:
        _watcher_task.cancel()
        try:
            await _watcher_task
        except asyncio.CancelledError:
            pass
        _watcher_task = None


@app.get("/health")
async def health() -> dict[str, Any]:
    return {"ok": True, "service": "portal-heartbeat-collector"}


@app.get("/status")
async def status(authorization: str | None = Header(default=None)) -> dict[str, Any]:
    _require_token(authorization)
    async with _lock:
        state = _load_state()
        derived = _derive_status(state)
        last_seen = state.get("last_seen")
        return {
            "ok": True,
            "status": derived,
            "alert_state": state.get("alert_state"),
            "last_seen": last_seen,
            "last_seen_iso": _iso(float(last_seen)) if last_seen is not None else None,
            "age_seconds": int(_now() - float(last_seen)) if last_seen is not None else None,
            "silence_seconds": SILENCE_SECONDS,
            "last_payload": state.get("last_payload"),
            "telegram_configured": bool(TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID),
        }


@app.post("/heartbeat")
async def heartbeat(
    request: Request,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _require_token(authorization)
    try:
        payload = await request.json()
    except Exception as exc:
        raise HTTPException(400, f"Invalid JSON: {exc}") from exc
    if not isinstance(payload, dict):
        raise HTTPException(400, "Expected JSON object")

    async with _lock:
        state = _load_state()
        state["last_seen"] = _now()
        state["last_payload"] = payload
        new_status = "degraded" if payload.get("ok") is False else "ok"
        if new_status == "degraded":
            reason = _failed_checks_summary(payload)
        else:
            reason = "heartbeat ok"
        state = await _maybe_alert(state, new_status, reason)
        state["status"] = new_status
        _save_state(state)
        return {
            "ok": True,
            "status": new_status,
            "received_at": _iso(),
            "host_id": payload.get("host_id"),
        }
