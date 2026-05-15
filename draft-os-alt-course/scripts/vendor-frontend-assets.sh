#!/usr/bin/env bash
# Скачивает фронтенд-зависимости в laravel-course-files/public/vendor и public/fonts.
# Запуск из корня draft-os-alt-course: bash scripts/vendor-frontend-assets.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
PUB="${ROOT}/laravel-course-files/public"
V="${PUB}/vendor"
F="${PUB}/fonts"

mkdir -p "${V}/sortablejs/1.15.2" \
  "${V}/easymde/2.18.0" \
  "${V}/html2canvas/1.4.1" \
  "${V}/jspdf/2.5.1" \
  "${V}/mermaid/10" \
  "${V}/marked/12.0.2" \
  "${F}/manrope" \
  "${F}/jetbrains-mono"

dl() {
  local url="$1" dest="$2"
  echo "  -> ${dest}"
  curl -fsSL --retry 3 --retry-delay 2 -o "${dest}" "${url}"
}

echo "[vendor] JS/CSS libraries (jsDelivr npm mirrors)"
dl "https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" \
  "${V}/sortablejs/1.15.2/Sortable.min.js"
dl "https://cdn.jsdelivr.net/npm/easymde@2.18.0/dist/easymde.min.js" \
  "${V}/easymde/2.18.0/easymde.min.js"
dl "https://cdn.jsdelivr.net/npm/easymde@2.18.0/dist/easymde.min.css" \
  "${V}/easymde/2.18.0/easymde.min.css"
dl "https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" \
  "${V}/html2canvas/1.4.1/html2canvas.min.js"
dl "https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js" \
  "${V}/jspdf/2.5.1/jspdf.umd.min.js"
dl "https://cdn.jsdelivr.net/npm/mermaid@10.9.3/dist/mermaid.esm.min.mjs" \
  "${V}/mermaid/10/mermaid.esm.min.mjs"
dl "https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js" \
  "${V}/marked/12.0.2/marked.min.js"

echo "[vendor] Manrope v20 (Google gstatic, как fonts.googleapis.com)"
dl "https://fonts.gstatic.com/s/manrope/v20/xn7_YHE41ni1AdIRqAuZuw1Bx9mbZk79FO_F.ttf" \
  "${F}/manrope/manrope-latin-400.ttf"
dl "https://fonts.gstatic.com/s/manrope/v20/xn7_YHE41ni1AdIRqAuZuw1Bx9mbZk4jE-_F.ttf" \
  "${F}/manrope/manrope-latin-600.ttf"
dl "https://fonts.gstatic.com/s/manrope/v20/xn7_YHE41ni1AdIRqAuZuw1Bx9mbZk4aE-_F.ttf" \
  "${F}/manrope/manrope-latin-700.ttf"
for w in 400 500 600; do
  dl "https://cdn.jsdelivr.net/fontsource/fonts/jetbrains-mono@5.2.8/latin-${w}-normal.woff2" \
    "${F}/jetbrains-mono/jetbrains-mono-latin-${w}.woff2"
done

echo "[vendor] готово: ${V} и ${F}"
