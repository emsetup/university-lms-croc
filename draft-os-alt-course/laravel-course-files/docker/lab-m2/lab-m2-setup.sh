#!/bin/bash
set -euo pipefail

# --- ЗАДАНИЕ 3: установить пакет и испортить файл ---
apt-get update -y
apt-get install -y nano

# Портим исполняемый файл nano: меняем права и mtime.
chmod 777 /usr/bin/nano
touch -d '2001-01-01 00:00:00' /usr/bin/nano

# --- ЗАДАНИЕ 2: сломать sources.list ---
# Меняем http на htp в первом найденном .list с репозиторием rpm.
for f in /etc/apt/sources.list.d/*.list; do
  if [ -f "$f" ] && grep -q '^rpm' "$f"; then
    sed -i 's|http://|htp://|g' "$f"
    break
  fi
done

echo "=== Lab M2 setup complete ==="
