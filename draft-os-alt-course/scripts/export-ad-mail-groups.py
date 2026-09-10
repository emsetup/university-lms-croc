#!/usr/bin/env python3
"""Разовая выгрузка distribution-групп с mail из MS AD croc.ru (LDAPS).

Креды: PORTAL_MAIL_USERNAME / PORTAL_MAIL_PASSWORD (как у EWS practice@).
Хосты: PORTAL_AD_HOSTS (через запятую) или 10.0.0.150,10.0.0.11,10.0.0.100.

Пример:
  eval "$(ssh emednikov@172.26.76.216 'awk -F= \"/^PORTAL_MAIL_USERNAME=/{print \\\"PORTAL_MAIL_USERNAME=\\\" \\$2} /^PORTAL_MAIL_PASSWORD=/{print \\\"PORTAL_MAIL_PASSWORD=\\\" \\$2}\" /var/www/os-alt-lab/.env')"
  python3 scripts/export-ad-mail-groups.py -o scripts/fixtures/ad-mail-groups.json
"""

from __future__ import annotations

import argparse
import json
import os
import ssl
import sys
import uuid
from datetime import datetime, timezone

from ldap3 import ALL, SUBTREE, Connection, Server, Tls

DEFAULT_HOSTS = "10.0.0.150,10.0.0.11,10.0.0.100"
FILTER = (
    "(&(objectCategory=group)(mail=*)"
    "(!(groupType:1.2.840.113556.1.4.803:=2147483648)))"
)
ATTRS = [
    "objectGUID",
    "cn",
    "displayName",
    "mail",
    "sAMAccountName",
    "distinguishedName",
    "groupType",
]


def one(value):
    if isinstance(value, list):
        return value[0] if value else None
    return value


def guid_str(raw) -> str | None:
    raw = one(raw)
    if raw is None:
        return None
    if isinstance(raw, bytes):
        return str(uuid.UUID(bytes_le=raw))
    s = str(raw).strip().strip("{}")
    return s or None


def bind(hosts: list[str], user: str, password: str) -> Connection:
    tls = Tls(validate=ssl.CERT_NONE, version=ssl.PROTOCOL_TLS_CLIENT)
    last = None
    for host in hosts:
        try:
            server = Server(host, port=636, use_ssl=True, tls=tls, get_info=ALL, connect_timeout=8)
            conn = Connection(server, user=user, password=password, auto_bind=True, receive_timeout=90)
            print(f"bound {host}:636 as {user}", file=sys.stderr)
            return conn
        except Exception as exc:  # noqa: BLE001
            last = exc
            print(f"fail {host}: {type(exc).__name__}: {exc}", file=sys.stderr)
    raise SystemExit(f"LDAP bind failed: {last}")


def main() -> int:
    parser = argparse.ArgumentParser(description="Export AD mail distribution groups")
    parser.add_argument("-o", "--output", required=True, help="JSON output path")
    parser.add_argument("--base-dn", default=os.environ.get("PORTAL_AD_BASE_DN", "DC=croc,DC=ru"))
    args = parser.parse_args()

    user = os.environ.get("PORTAL_MAIL_USERNAME") or os.environ.get("PORTAL_AD_USERNAME") or ""
    password = os.environ.get("PORTAL_MAIL_PASSWORD") or os.environ.get("PORTAL_AD_PASSWORD") or ""
    if not user or not password:
        print("Need PORTAL_MAIL_USERNAME and PORTAL_MAIL_PASSWORD", file=sys.stderr)
        return 1

    hosts = [h.strip() for h in (os.environ.get("PORTAL_AD_HOSTS") or DEFAULT_HOSTS).split(",") if h.strip()]
    conn = bind(hosts, user, password)

    rows: list[dict] = []
    for entry in conn.extend.standard.paged_search(
        args.base_dn,
        FILTER,
        SUBTREE,
        attributes=ATTRS,
        paged_size=500,
        generator=True,
    ):
        if entry.get("type") != "searchResEntry":
            continue
        attrs = entry.get("attributes") or {}
        mail = one(attrs.get("mail"))
        if not mail:
            continue
        rows.append(
            {
                "object_guid": guid_str(attrs.get("objectGUID")),
                "cn": one(attrs.get("cn")),
                "display_name": one(attrs.get("displayName")) or one(attrs.get("cn")),
                "mail": str(mail).strip().lower(),
                "sam_account_name": one(attrs.get("sAMAccountName")),
                "dn": entry.get("dn") or one(attrs.get("distinguishedName")),
                "group_type": int(one(attrs.get("groupType")) or 0),
            }
        )
    conn.unbind()

    seen: set[str] = set()
    unique: list[dict] = []
    for row in rows:
        if row["mail"] in seen:
            continue
        seen.add(row["mail"])
        unique.append(row)

    payload = {
        "exported_at": datetime.now(timezone.utc).isoformat(),
        "source": "MS AD croc.ru distribution groups with mail",
        "filter": FILTER,
        "count": len(unique),
        "groups": unique,
    }
    out = os.path.abspath(args.output)
    os.makedirs(os.path.dirname(out) or ".", exist_ok=True)
    with open(out, "w", encoding="utf-8") as fh:
        json.dump(payload, fh, ensure_ascii=False)
    print(f"wrote {out} count={len(unique)}", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
