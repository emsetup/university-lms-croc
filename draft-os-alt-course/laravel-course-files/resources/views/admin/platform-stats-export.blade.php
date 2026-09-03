<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Статистика портала — PDF</title>
    <link rel="stylesheet" href="{{ asset('css/platform-stats.css') }}?v={{ @filemtime(public_path('css/platform-stats.css')) ?: 1 }}">
    <link rel="stylesheet" href="{{ asset('css/platform-stats-export.css') }}?v={{ @filemtime(public_path('css/platform-stats-export.css')) ?: 1 }}">
</head>
<body class="ps-export-body">
    <div class="ps-export-toolbar" id="ps-export-toolbar">
        <div class="ps-export-toolbar__inner">
            <a class="ps-export-toolbar__back" href="{{ route('admin.platform-stats') }}">← К статистике</a>
            <div class="ps-export-toolbar__actions">
                <button type="button" class="ps-export-toolbar__btn" id="ps-pdf-btn">Скачать PDF</button>
                <button type="button" class="ps-export-toolbar__btn ps-export-toolbar__btn--ghost" id="ps-print-btn">Печать</button>
                <span class="ps-export-toolbar__status" id="ps-pdf-status" aria-live="polite"></span>
            </div>
        </div>
    </div>

    <div id="ps-export-root" class="ps-page ps-export-root">
        <div class="ps-export-chunk" data-ps-chunk>
            <header class="ps-hero ps-export-hero">
                <p class="ps-hero__kicker">Отчёт портала обучения</p>
                <h1 class="ps-hero__title">Статистика активности</h1>
                <p class="ps-hero__lead">
                    Сводка по пользователям, авторам, назначениям, прогрессу и доработкам портала.
                    В конце — реестр сотрудников с выданными ролями.
                </p>
                <div class="ps-hero__meta">
                    <span class="ps-pill">Сформировано {{ $stats['generated_at'] ?? '—' }}</span>
                    <span class="ps-pill">Вовлечённость {{ (int) ($stats['engagement_pct'] ?? 0) }}%</span>
                    <span class="ps-pill">Staff: {{ count($staffRoster ?? []) }}</span>
                </div>
            </header>
        </div>

        @include('admin.partials.platform-stats-body', [
            'stats' => $stats,
            'staticBars' => true,
        ])

        <div class="ps-export-chunk" data-ps-chunk>
            @include('admin.partials.platform-stats-staff-roster', [
                'staffRoster' => $staffRoster ?? [],
            ])
        </div>

        <div class="ps-export-chunk" data-ps-chunk>
            <footer class="ps-export-footer">
                <p>Учебный портал · отчёт сформирован автоматически · {{ $stats['generated_at'] ?? now()->format('d.m.Y H:i') }}</p>
            </footer>
        </div>
    </div>

    <script src="{{ asset('vendor/html2canvas/1.4.1/html2canvas.min.js') }}"></script>
    <script src="{{ asset('vendor/jspdf/2.5.1/jspdf.umd.min.js') }}"></script>
    <script>
    (function () {
        var root = document.getElementById('ps-export-root');
        var btn = document.getElementById('ps-pdf-btn');
        var printBtn = document.getElementById('ps-print-btn');
        var status = document.getElementById('ps-pdf-status');
        var busy = false;

        function setStatus(msg) {
            if (status) status.textContent = msg || '';
        }

        function finish(ok, errMsg) {
            document.body.classList.remove('ps-export-generating');
            if (btn) btn.disabled = false;
            if (printBtn) printBtn.disabled = false;
            busy = false;
            setStatus(ok ? '' : (errMsg || 'Не удалось собрать PDF — воспользуйтесь «Печать».'));
        }

        function stripAnimations(doc) {
            var style = doc.createElement('style');
            style.textContent = [
                '*{animation:none!important;transition:none!important;transform:none!important}',
                '.ps-kpi,.ps-card,.ps-hero,.ps-export-footer{opacity:1!important;visibility:visible!important}'
            ].join('');
            doc.head.appendChild(style);
            doc.querySelectorAll('.ps-kpi,.ps-card,.ps-hero').forEach(function (el) {
                el.style.opacity = '1';
                el.style.transform = 'none';
                el.style.visibility = 'visible';
            });
        }

        function captureEl(el) {
            return window.html2canvas(el, {
                scale: 1.75,
                useCORS: true,
                logging: false,
                backgroundColor: '#f3faf6',
                windowWidth: Math.max(root.scrollWidth, 980),
                scrollX: 0,
                scrollY: -window.scrollY,
                onclone: function (doc) {
                    stripAnimations(doc);
                }
            });
        }

        /**
         * Непрерывная укладка: короткие блоки идут друг за другом на одной странице,
         * длинные режутся только по фактическому остатку места.
         */
        function createPacker(pdf, marginMm, contentW, contentH) {
            var cursorY = marginMm;
            var pageOpen = true;
            var targetW = null;
            var sliceCanvas = document.createElement('canvas');
            var sliceCtx = sliceCanvas.getContext('2d');

            function ensurePage() {
                if (!pageOpen) {
                    pdf.addPage();
                    cursorY = marginMm;
                    pageOpen = true;
                }
            }

            function remainingMm() {
                return contentH - (cursorY - marginMm);
            }

            function placeSlice(srcCanvas, srcY, srcH, pxPerMm) {
                if (srcH <= 0) return;
                ensurePage();
                sliceCanvas.width = targetW;
                sliceCanvas.height = srcH;
                sliceCtx.fillStyle = '#f3faf6';
                sliceCtx.fillRect(0, 0, targetW, srcH);
                sliceCtx.drawImage(srcCanvas, 0, srcY, targetW, srcH, 0, 0, targetW, srcH);

                var mmH = srcH / pxPerMm;
                var imgData = sliceCanvas.toDataURL('image/jpeg', 0.92);
                pdf.addImage(imgData, 'JPEG', marginMm, cursorY, contentW, mmH);
                cursorY += mmH;
                if (remainingMm() < 1.2) {
                    pageOpen = false;
                }
            }

            function normalizeWidth(canvas) {
                if (targetW === null) {
                    targetW = canvas.width;
                    return canvas;
                }
                if (canvas.width === targetW) return canvas;
                var scaled = document.createElement('canvas');
                scaled.width = targetW;
                scaled.height = Math.max(1, Math.round(canvas.height * (targetW / canvas.width)));
                var ctx = scaled.getContext('2d');
                ctx.fillStyle = '#f3faf6';
                ctx.fillRect(0, 0, scaled.width, scaled.height);
                ctx.drawImage(canvas, 0, 0, scaled.width, scaled.height);
                return scaled;
            }

            return {
                addCanvas: function (rawCanvas) {
                    if (!rawCanvas || !rawCanvas.width || !rawCanvas.height) return;
                    var canvas = normalizeWidth(rawCanvas);
                    var pxPerMm = targetW / contentW;
                    var srcY = 0;

                    while (srcY < canvas.height - 0.5) {
                        var leftPx = canvas.height - srcY;
                        var roomMm = remainingMm();
                        if (roomMm < 8 && leftPx / pxPerMm > roomMm + 0.5) {
                            pageOpen = false;
                            ensurePage();
                            roomMm = contentH;
                        }
                        var takePx = Math.min(leftPx, Math.floor(roomMm * pxPerMm));
                        if (takePx < 1) {
                            pageOpen = false;
                            ensurePage();
                            continue;
                        }
                        placeSlice(canvas, srcY, takePx, pxPerMm);
                        srcY += takePx;
                    }
                }
            };
        }

        function generatePdf() {
            if (!root || !window.html2canvas || !window.jspdf || busy) return;
            busy = true;
            setStatus('Формируем PDF…');
            if (btn) btn.disabled = true;
            if (printBtn) printBtn.disabled = true;
            document.body.classList.add('ps-export-generating');

            var stamp = new Date().toISOString().slice(0, 10);
            var filename = 'portal-stats_' + stamp + '.pdf';
            var marginMm = 8;
            var contentW = 210 - marginMm * 2;
            var contentH = 297 - marginMm * 2;
            var chunks = Array.prototype.slice.call(root.querySelectorAll('[data-ps-chunk]'));
            if (!chunks.length) chunks = [root];

            var pdf = new window.jspdf.jsPDF({
                orientation: 'p',
                unit: 'mm',
                format: 'a4',
                compress: true
            });
            var packer = createPacker(pdf, marginMm, contentW, contentH);
            var i = 0;

            function next() {
                if (i >= chunks.length) {
                    pdf.save(filename);
                    finish(true);
                    return;
                }
                setStatus('Формируем PDF… ' + (i + 1) + '/' + chunks.length);
                var chunk = chunks[i++];
                captureEl(chunk).then(function (canvas) {
                    packer.addCanvas(canvas);
                    window.setTimeout(next, 30);
                }).catch(function () {
                    finish(false);
                });
            }

            window.setTimeout(next, 120);
        }

        if (btn) btn.addEventListener('click', generatePdf);
        if (printBtn) printBtn.addEventListener('click', function () { window.print(); });

        @if (! empty($autoDownload))
        window.addEventListener('load', function () {
            window.setTimeout(generatePdf, 700);
        });
        @endif
    })();
    </script>
</body>
</html>
