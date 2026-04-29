#!/usr/bin/env python3
"""Replace module 8 practice heredoc with require snippet in course.php."""
import sys

path = sys.argv[1] if len(sys.argv) > 1 else "/var/www/os-alt-lab/config/course.php"
t = open(path, encoding="utf-8").read()
anchor = "'theory' => '@snippet:module_08_theory.md',"
i = t.find(anchor)
if i < 0:
    sys.exit("anchor not found")
j = t.find("        ],\n        9 =>", i)
if j < 0:
    sys.exit("module 8 end not found")
seg = t[i:j]
if "module_08_practice_lab.php" in seg:
    print("already patched")
    sys.exit(0)
if "<<<'MD'" not in seg:
    sys.exit("unexpected: no heredoc in segment")
k = seg.find("'practice' =>")
if k < 0:
    sys.exit("practice key not found")
rest = seg[k:]
endm = rest.find("MD,")
if endm < 0:
    sys.exit("MD, not found")
# include 'MD,\n' line
end_line = rest.find("\n", endm)
if end_line < 0:
    sys.exit("newline after MD,")
new_seg = (
    seg[:k]
    + "'practice' => require __DIR__.'/snippets/module_08_practice_lab.php',\n"
)
t2 = t[:i] + new_seg + t[j:]
open(path, "w", encoding="utf-8").write(t2)
print("patched", path)
