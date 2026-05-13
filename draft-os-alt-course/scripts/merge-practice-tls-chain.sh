#!/usr/bin/env bash
# Собрать fullchain для nginx: лист + промежуточный(е) CrocCA (PEM).
# Использование:
#   положите в один каталог: practice.croc.ru.pem, CrocCA.pem (или несколько PEM подряд)
#   bash scripts/merge-practice-tls-chain.sh /path/to/dir
# На выходе: practice-fullchain.crt (и проверка: grep -c BEGIN practice-fullchain.crt >= 2)
set -euo pipefail
DIR="${1:?Укажите каталог с PEM-файлами}"
LEAF="${DIR}/practice.croc.ru.pem"
CA="${DIR}/CrocCA.pem"
OUT="${DIR}/practice-fullchain.crt"
if [[ ! -f "$LEAF" ]]; then
  echo "Нет файла $LEAF" >&2
  exit 1
fi
if [[ ! -f "$CA" ]]; then
  echo "Нет файла $CA — экспортируйте из корпоративного PKI сертификат издателя (CrocCA) в PEM и сохраните как CrocCA.pem" >&2
  exit 1
fi
cat "$LEAF" "$CA" > "$OUT"
echo "OK: $OUT ($(grep -c 'BEGIN CERTIFICATE' "$OUT" || true) сертификатов)"
openssl verify -untrusted "$CA" "$LEAF" 2>/dev/null || true
