#!/bin/bash
set -uo pipefail

PASS=0
FAIL=0
TOTAL_SCORE=0

check() {
    local task="$1"
    local points="$2"
    local result="$3"
    local message="${4:-}"

    if [ "$result" = "ok" ]; then
        echo "TASK${task}:PASS"
        PASS=$((PASS + 1))
        TOTAL_SCORE=$((TOTAL_SCORE + points))
    else
        echo "TASK${task}:FAIL:${message}"
        FAIL=$((FAIL + 1))
    fi
}

# ===== TASK 1: inventory =====
if test -s /root/exam-report.txt; then
    check 1a 5 ok
else
    check 1a 5 fail "file /root/exam-report.txt is missing or empty"
fi

if grep -qi 'alt' /root/exam-report.txt 2>/dev/null; then
    check 1b 5 ok
else
    check 1b 5 fail "report does not contain OS name/version"
fi

# ===== TASK 2: CUS =====
if systemctl is-active alteratord &>/dev/null; then
    check 2a 7 ok
else
    check 2a 7 fail "service alteratord is not active"
fi

if systemctl is-enabled alteratord &>/dev/null; then
    check 2b 3 ok
else
    check 2b 3 fail "service alteratord is not enabled"
fi

if systemctl is-active ahttpd &>/dev/null; then
    check 2c 5 ok
else
    check 2c 5 fail "service ahttpd is not active"
fi

if systemctl is-enabled ahttpd &>/dev/null; then
    check 2d 3 ok
else
    check 2d 3 fail "service ahttpd is not enabled"
fi

# ===== TASK 3: password policy =====
min_line="$(grep '^min=' /etc/passwdqc.conf 2>/dev/null | tr -d '\r' || true)"
min_first="$(echo "$min_line" | cut -d= -f2 | cut -d, -f1)"
if [ "$min_first" = "disabled" ]; then
    check 3a 10 ok
else
    check 3a 10 fail "min must start with disabled, got: ${min_line:-<empty>}"
fi

enforce_line="$(grep '^enforce=' /etc/passwdqc.conf 2>/dev/null | tr -d '\r' || true)"
if [ "$enforce_line" = "enforce=everyone" ]; then
    check 3b 10 ok
else
    check 3b 10 fail "expected enforce=everyone, got: ${enforce_line:-<empty>}"
fi

# ===== TASK 4: RPM integrity (nano + sed) =====
result_nano="$(rpm -V nano 2>/dev/null || true)"
if [ -z "$result_nano" ]; then
    check 4a 10 ok
else
    check 4a 10 fail "/usr/bin/nano still differs from RPM baseline"
fi

result_sed="$(rpm -V sed 2>/dev/null | grep '/bin/sed' || true)"
if [ -z "$result_sed" ]; then
    check 4b 10 ok
else
    check 4b 10 fail "/bin/sed still differs from RPM baseline"
fi

# ===== TASK 5: Polkit delegation =====
if getent group netops >/dev/null 2>&1; then
    check 5a 2 ok
else
    check 5a 2 fail "group netops does not exist"
fi

if id -nG student 2>/dev/null | tr ' ' '\n' | grep -qx 'netops'; then
    check 5b 2 ok
else
    check 5b 2 fail "user student is not in group netops"
fi

if test -f /etc/polkit-1/rules.d/10-netops.rules; then
    check 5c 2 ok
else
    check 5c 2 fail "file /etc/polkit-1/rules.d/10-netops.rules is missing"
fi

if grep -q 'org.freedesktop.systemd1.manage-units' /etc/polkit-1/rules.d/10-netops.rules 2>/dev/null \
    && grep -q 'netops' /etc/polkit-1/rules.d/10-netops.rules 2>/dev/null \
    && grep -q 'polkit.Result.YES' /etc/polkit-1/rules.d/10-netops.rules 2>/dev/null; then
    check 5d 6 ok
else
    check 5d 6 fail "polkit rule does not contain required action/group/allow clauses"
fi

# ===== TASK 6: control sudowheel =====
sw="$(control sudowheel 2>/dev/null | tr -d '\r' || true)"
if [ "$sw" = "enabled" ]; then
    check 6 20 ok
else
    check 6 20 fail "control sudowheel must be enabled, now: ${sw:-<empty>}"
fi

echo "RESULT:${PASS}:${FAIL}"
echo "SCORE:${TOTAL_SCORE}:100"
if [ "$TOTAL_SCORE" -ge 70 ]; then
    echo "OUTCOME:PASS"
else
    echo "OUTCOME:FAIL"
fi

echo "===PRACTICE_RESULT_JSON==="
echo "{\"score\":${TOTAL_SCORE},\"max\":100,\"tasks_passed\":${PASS},\"tasks_failed\":${FAIL}}"

exit 0
