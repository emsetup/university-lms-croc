@extends('layouts.admin')

@section('title', 'Админ курса — просмотр сертификата')

@section('content')
    <div class="card" style="max-width: 1200px; margin: 0 auto 1rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div>
                <h1 style="margin:0 0 0.35rem">Просмотр сертификата</h1>
                <p class="muted" style="margin:0">
                    {{ $row->certificate_full_name ?: 'ФИО не указано' }} — {{ $row->learner->email ?? '—' }}
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                <button type="button" class="btn btn-primary js-admin-cert-pdf" data-cert-basename="{{ e(preg_replace('/[^\p{L}\p{N}\-_]+/u', '_', trim($row->certificate_full_name ?? '')) ?: 'certificate') }}">Скачать PDF</button>
                <a class="btn btn-ghost" href="{{ route('admin.certificates', $ap ?? []) }}">Назад к реестру</a>
            </div>
        </div>
    </div>

    @include('partials.certificate-design-css')

    <p class="muted" style="max-width:1240px;margin:0 auto 0.75rem;font-size:0.9rem">
        Для администратора: сводный процент курса <strong>{{ $certCoursePercent }}%</strong>, уровень на бланке — как у слушателя. Финальная лаба (лучший результат): <strong>{{ (int) $row->best_score }}/100</strong>.
    </p>

    <div class="cert-preview-wrap" style="max-width:1240px;margin:0 auto;background:#f1f5f9;padding:1rem;border-radius:12px">
        @include('partials.certificate-paper', [
            'serial' => $row->certificate_serial,
            'issueDate' => $row->certificateDisplayIssueDate()->format('d.m.Y'),
            'recipientName' => $row->certificate_full_name ?: 'Фамилия Имя Отчество',
            'nameExtraClass' => '',
            'certTier' => $certTier,
        ])
    </div>

    <script src="{{ asset('vendor/html2canvas/1.4.1/html2canvas.min.js') }}"></script>
    <script src="{{ asset('vendor/jspdf/2.5.1/jspdf.umd.min.js') }}"></script>
    <script>
        (function () {
            var btn = document.querySelector('.js-admin-cert-pdf');
            var wrap = document.querySelector('.cert-preview-wrap');
            var paper = wrap ? wrap.querySelector('.cert-paper') : null;
            if (!btn || !paper || !window.html2canvas || !window.jspdf) return;

            btn.addEventListener('click', function () {
                var base = (btn.getAttribute('data-cert-basename') || 'certificate').replace(/_+/g, '_');
                window.html2canvas(paper, {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#f0fdf9'
                }).then(function (canvas) {
                    var imgData = canvas.toDataURL('image/png');
                    var pdf = new window.jspdf.jsPDF({
                        orientation: 'landscape',
                        unit: 'pt',
                        format: 'a4'
                    });
                    var pageWidth = pdf.internal.pageSize.getWidth();
                    var pageHeight = pdf.internal.pageSize.getHeight();
                    pdf.addImage(imgData, 'PNG', 0, 0, pageWidth, pageHeight);
                    pdf.save('certificate_' + base + '.pdf');
                }).catch(function () {});
            });
        })();
    </script>
@endsection
