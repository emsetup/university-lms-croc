#!/usr/bin/env python3
import re
from pathlib import Path

p = Path("/var/www/os-alt-lab/config/course.php")
text = p.read_text(encoding="utf-8")
pattern = r"""(
            5 => \[
                'theory_quiz' => require __DIR__\.'/snippets/module_05_theory_quiz_questions\.php',
)                'module_exam' => \[[\s\S]*?
                \],
(            \],)"""
repl = r"""\1                'module_exam' => require __DIR__.'/snippets/module_05_module_exam_questions.php',
\2"""
new_text, n = re.subn(pattern, repl, text, count=1)
if n != 1:
    raise SystemExit(f"replace count {n}")
p.write_text(new_text, encoding="utf-8")
print("OK module_quizzes[5]")
