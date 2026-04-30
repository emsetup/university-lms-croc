@extends('layouts.course')

@section('title', 'Итоги обучения')

@section('content')
    @php $isNameLocked = ! empty($final->certificate_full_name); @endphp
    <div class="cert-hero" style="margin-bottom:1rem">
        <div class="tag" style="margin-bottom:0.75rem">Итоговая страница</div>
        <h1 style="margin:0 0 0.5rem">Сертификат по курсу "Особенности ОС Альт"</h1>
        <p class="muted" style="margin:0">Корпоративная почта</p>
        <div style="font-size:1.1rem;font-weight:700;margin:0.35rem 0 1rem">{{ $learner->email }}</div>
        <div class="cert-score">{{ $grand }}</div>
        <p class="muted" style="margin:0.35rem 0 0">Суммарные баллы (модули + финальная лаба)</p>
    </div>

    <div class="card" style="margin-top:1rem">
        <h2 style="margin-top:0">Данные сертификата</h2>
        <form method="post" action="{{ route('certificate.recipient') }}" style="display:grid;gap:0.75rem;max-width:760px" id="certificate-recipient-form">
            @csrf
            <label for="certificate_full_name" style="font-weight:700">Фамилия Имя Отчество</label>
            <input
                id="certificate_full_name"
                name="certificate_full_name"
                type="text"
                required
                maxlength="120"
                value="{{ old('certificate_full_name', $final->certificate_full_name) }}"
                placeholder="Иванов Иван Иванович"
                @disabled($isNameLocked)
                style="width:100%;padding:0.65rem 0.75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:1rem"
            >
            @error('certificate_full_name')
                <div class="flash err" style="margin:0">{{ $message }}</div>
            @enderror
            @if ($isNameLocked)
                <p class="muted" style="margin:0">ФИО уже сохранено и больше не редактируется.</p>
            @endif
            <div style="display:flex;gap:0.6rem;flex-wrap:wrap">
                <button type="submit" class="btn btn-primary" @disabled($isNameLocked)>Сохранить ФИО</button>
                <button type="button" class="btn btn-ghost js-cert-preview" @disabled(! $final->certificate_full_name)>Просмотр PDF</button>
                <button type="button" class="btn btn-ghost js-cert-download" @disabled(! $final->certificate_full_name)>Скачать PDF</button>
            </div>
        </form>
        <p class="muted" style="margin:0.85rem 0 0">
            Номер сертификата:
            <strong>{{ $final->certificate_serial ?: 'будет присвоен после сохранения ФИО' }}</strong>
        </p>
    </div>

    <div class="card" style="margin-top:1rem">
        <h2 style="margin-top:0">Разбивка</h2>
        <p class="muted">Модули: <strong>{{ $modulePoints }}</strong> из {{ $modulePointsMax }}. Финальная лаба: <strong>{{ $finalPoints }}</strong> из 100. Попыток финальной: <strong>{{ $final->attempts }}</strong>.</p>
    </div>

    <div class="card" style="margin-top:1rem">
        <h2 style="margin-top:0">Где были сложности</h2>
        @php
            $labels = [
                'theory' => 'теория',
                'theory_quiz' => 'тест по теории',
                'practice' => 'практика',
                'module_exam' => 'итоговый тест модуля',
            ];
        @endphp
        <ul class="muted" style="padding-left:1.1rem">
            @forelse ($moduleReport as $row)
                @php
                    $flags = $row['difficulties'] ?? [];
                    $active = array_filter($flags);
                @endphp
                @if (count($active))
                    @php $meta = config('course.modules.'.$row['module_id']); @endphp
                    <li style="margin:0.35rem 0">
                        <strong>{{ $meta['letter'] }}</strong> - {{ $meta['title'] }}:
                        @foreach ($active as $k => $_)
                            <span class="pill">{{ $labels[$k] ?? $k }}</span>
                        @endforeach
                    </li>
                @endif
            @empty
            @endforelse
        </ul>
        @php
            $any = false;
            foreach ($moduleReport as $row) {
                if (count(array_filter($row['difficulties'] ?? []))) {
                    $any = true;
                    break;
                }
            }
        @endphp
        @if (! $any)
            <p class="muted">Отметок о сложностях нет - или вы не отмечали этапы в модулях.</p>
        @endif
    </div>

    <div class="card" style="margin-top:1rem">
        <h2 style="margin-top:0">Справка по баллам</h2>
        <p class="muted" style="margin:0">Модули: до 100 баллов каждый (учитываются попытки тестов по теории и итогового теста). Финальная лаба: до 100 баллов с учетом числа попыток. Итог - сумма.</p>
    </div>

    <div id="certificate-render" style="position:fixed;left:-10000px;top:-10000px;width:1240px;background:#fff;padding:0">
        <div style="position:relative;width:1240px;height:877px;box-sizing:border-box;padding:64px 72px;border:14px solid #0f6f64;background:linear-gradient(160deg,#f7fffb 0%,#ffffff 58%,#edf8f5 100%);font-family:Manrope,Arial,sans-serif;color:#0f172a;">
            <div style="position:absolute;inset:22px;border:2px solid #93c5bd;pointer-events:none"></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <div>
                    <img src="{{ asset('croc-wordmark.svg') }}" alt="КРОК" style="height:38px;display:block">
                    <div style="margin-top:12px;font-size:13px;letter-spacing:0.18em;text-transform:uppercase;color:#0f766e;font-weight:700">Образовательный сертификат</div>
                </div>
                <div style="text-align:right;font-size:14px;line-height:1.55">
                    <div><strong>№ {{ $final->certificate_serial ?: 'CROC-ALT-DRAFT' }}</strong></div>
                    <div>Дата выдачи: {{ optional($final->certificate_issued_at)->format('d.m.Y') ?: now()->format('d.m.Y') }}</div>
                </div>
            </div>

            <div style="margin-top:92px;text-align:center">
                <div style="font-size:20px;color:#0f766e;letter-spacing:0.12em;text-transform:uppercase;font-weight:700">Настоящий сертификат подтверждает, что</div>
                <div style="margin-top:34px;font-size:52px;line-height:1.08;font-weight:800;color:#134e4a" class="js-cert-fullname">{{ $final->certificate_full_name ?: 'Фамилия Имя Отчество' }}</div>
                <div style="margin-top:34px;font-size:24px;line-height:1.45;max-width:900px;margin-left:auto;margin-right:auto">
                    успешно завершил(а) курс
                    <strong>"Особенности ОС Альт"</strong>
                    и подтвердил(а) практические навыки администрирования.
                </div>
            </div>

            <div style="position:absolute;left:72px;right:72px;bottom:74px;display:flex;justify-content:space-between;align-items:flex-end;gap:24px">
                <div style="font-size:15px;line-height:1.5;color:#334155">
                    Итоговый балл: <strong>{{ $grand }}</strong><br>
                    Финальная лабораторная: <strong>{{ $finalPoints }}/100</strong><br>
                    Платформа: учебный курс КРОК
                </div>
                <div style="text-align:right">
                    <div style="font-size:14px;color:#334155;margin-bottom:8px">Руководитель программы</div>
                    <div style="width:260px;border-top:1px solid #94a3b8;padding-top:8px;font-size:14px;color:#0f172a">КРОК, учебный центр</div>
                </div>
            </div>
        </div>
    </div>

    <dialog id="certificate-confirm-dialog" style="max-width:620px;border:none;border-radius:12px;padding:0;box-shadow:0 20px 60px rgba(2,6,23,.25);">
        <div style="padding:1rem 1rem 0.25rem;font-weight:800;font-size:1.1rem">Подтверждение сохранения ФИО</div>
        <div style="padding:0.25rem 1rem 1rem;color:#334155;line-height:1.5">
            Проверьте ФИО еще раз. После подтверждения изменить данные в сертификате будет нельзя.
        </div>
        <div style="display:flex;justify-content:flex-end;gap:0.5rem;padding:0 1rem 1rem">
            <button type="button" class="btn btn-ghost js-cert-cancel-confirm">Отмена</button>
            <button type="button" class="btn btn-primary js-cert-apply-confirm">Подтвердить и сохранить</button>
        </div>
    </dialog>

    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script>
        (function () {
            const previewBtn = document.querySelector('.js-cert-preview');
            const downloadBtn = document.querySelector('.js-cert-download');
            const certBox = document.getElementById('certificate-render');
            const fullNameInput = document.getElementById('certificate_full_name');
            const fullNameNode = certBox ? certBox.querySelector('.js-cert-fullname') : null;
            const recipientForm = document.getElementById('certificate-recipient-form');
            const confirmDialog = document.getElementById('certificate-confirm-dialog');
            const confirmCancel = document.querySelector('.js-cert-cancel-confirm');
            const confirmApply = document.querySelector('.js-cert-apply-confirm');

            if (!previewBtn || !downloadBtn || !certBox || !window.html2canvas || !window.jspdf) {
                return;
            }

            function syncName() {
                if (!fullNameNode || !fullNameInput) return;
                const val = (fullNameInput.value || '').trim();
                fullNameNode.textContent = val || 'Фамилия Имя Отчество';
            }

            async function makePdfBlob() {
                syncName();
                const canvas = await window.html2canvas(certBox.firstElementChild, {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff'
                });
                const imgData = canvas.toDataURL('image/png');
                const pdf = new window.jspdf.jsPDF({
                    orientation: 'landscape',
                    unit: 'pt',
                    format: 'a4'
                });
                const pageWidth = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();
                pdf.addImage(imgData, 'PNG', 0, 0, pageWidth, pageHeight);
                return pdf.output('blob');
            }

            async function openPreview() {
                const blob = await makePdfBlob();
                const url = URL.createObjectURL(blob);
                window.open(url, '_blank', 'noopener');
                setTimeout(() => URL.revokeObjectURL(url), 120000);
            }

            async function downloadPdf() {
                const blob = await makePdfBlob();
                const a = document.createElement('a');
                const url = URL.createObjectURL(blob);
                const safeName = (fullNameInput.value || 'certificate').trim().replace(/[^\p{L}\p{N}\-_ ]/gu, '').replace(/\s+/g, '_');
                a.href = url;
                a.download = `certificate_${safeName || 'course'}.pdf`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(url), 30000);
            }

            previewBtn.addEventListener('click', () => { openPreview().catch(() => {}); });
            downloadBtn.addEventListener('click', () => { downloadPdf().catch(() => {}); });
            fullNameInput && fullNameInput.addEventListener('input', syncName);

            if (recipientForm && confirmDialog && fullNameInput && !fullNameInput.disabled) {
                recipientForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const val = (fullNameInput.value || '').trim();
                    if (!val) {
                        fullNameInput.focus();
                        return;
                    }
                    confirmDialog.showModal();
                });
                confirmCancel && confirmCancel.addEventListener('click', function () {
                    confirmDialog.close();
                });
                confirmApply && confirmApply.addEventListener('click', function () {
                    confirmDialog.close();
                    HTMLFormElement.prototype.submit.call(recipientForm);
                });
            }
            syncName();
        })();
    </script>
@endsection
