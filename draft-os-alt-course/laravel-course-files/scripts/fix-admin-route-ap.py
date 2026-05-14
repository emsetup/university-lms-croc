#!/usr/bin/env python3
"""
Добавляет $ap ?? [] к вызовам route() для маршрутов под /adm/kur/{adminCourse}/…
(Blade: resources/views/admin/**/*.blade.php)
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "resources" / "views" / "admin"

# Маршруты без параметра adminCourse в URI
NO_AP = frozenset(
    {
        "admin.panel",
        "admin.courses.index",
        "admin.courses.create",
        "admin.courses.store",
        "admin.courses.update",
        "admin.courses.archive",
        "admin.courses.unarchive",
        "admin.courses.select",
        "admin.courses.enter",
        "admin.courses.edit",
        "admin.staff.index",
        "admin.staff.create",
        "admin.staff.store",
        "admin.staff.edit",
        "admin.staff.update",
        "admin.staff.destroy",
        "admin.docker.library",
        "admin.docker.library.store",
        "admin.docker.library.stats.refresh",
        "admin.docker.library.build",
        "admin.docker.library.destroy",
        "admin.learners.portal",
    }
)

# route('name') без второго аргумента
ZERO_ARG = frozenset(
    {
        "admin.theory.zip",
        "admin.course.settings",
        "admin.course.settings.save",
        "admin.course.modules",
        "admin.quiz.index",
        "admin.quiz.edit.final",
        "admin.practice.images.index",
        "admin.practice.images.create",
        "admin.practice.images.store",
        "admin.practice.images.stats.refresh",
        "admin.practice.images.system.copy",
        "admin.practice.images.pkg.search",
        "admin.theory.preview-final-lab",
        "admin.certificates",
        "admin.course.settings.modules.reorder",
        "admin.course.settings.module.store",
        "admin.quiz.save.final",
    }
)

# route('name', [...]) — второй аргумент: массив без adminCourse
ROUTE_TWO_ARGS = re.compile(
    r"route\('(?P<name>admin\.[^']+)'\s*,\s*(?P<arr>\[(?:[^\[\]]|\[[^\]]*\])*\])\)"
)


def fix_zero_arg(text: str) -> str:
    for name in ZERO_ARG:
        text = text.replace(f"route('{name}')", f"route('{name}', $ap ?? [])")
    return text


def fix_two_args(text: str) -> str:
    def repl(m: re.Match[str]) -> str:
        name = m.group("name")
        arr = m.group("arr")
        if name in NO_AP:
            return m.group(0)
        if "adminCourse" in arr or "array_merge($ap" in arr:
            return m.group(0)
        return f"route('{name}', array_merge($ap ?? [], {arr}))"

    return ROUTE_TWO_ARGS.sub(repl, text)


def main() -> int:
    if not ROOT.is_dir():
        print("Нет каталога", ROOT, file=sys.stderr)
        return 1
    changed = 0
    for path in sorted(ROOT.rglob("*.blade.php")):
        orig = path.read_text(encoding="utf-8")
        t = fix_zero_arg(orig)
        t = fix_two_args(t)
        if t != orig:
            path.write_text(t, encoding="utf-8")
            print(path.relative_to(ROOT.parent.parent))
            changed += 1
    print("files changed:", changed)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
