@extends('layouts.course')

@section('title', 'Итоги обучения')

@section('content')
    @php
        $isNameLocked = ! empty($final->certificate_full_name);
        $grandMax = (int) $modulePointsMax + 100;
    @endphp
    <div class="cert-hero" style="margin-bottom:1rem">
        <div class="tag" style="margin-bottom:0.75rem">Итоговая страница</div>
        <h1 style="margin:0 0 0.5rem">Сертификат по курсу "Особенности ОС Альт"</h1>
        <p class="muted" style="margin:0">Корпоративная почта</p>
        <div style="font-size:1.1rem;font-weight:700;margin:0.35rem 0 1rem">{{ $learner->email }}</div>
        <div style="text-align:center;margin:0.35rem 0 0">
            <div class="cert-score" style="font-size:clamp(1.1rem,3.5vw,1.45rem);line-height:1.35;font-weight:800;display:inline-block;max-width:36rem">{{ $certTier['label'] }}</div>
        </div>
        <p style="margin:0.75rem 0 0;font-size:1rem;line-height:1.5;color:#334155">
            <strong>Сумма баллов по курсу:</strong> {{ $grand }} из {{ $grandMax }}
            (модули <strong>{{ $modulePoints }}</strong> + финальная лаба <strong>{{ $finalPoints }}</strong>).
            В процентах от максимума: <strong>{{ $certCoursePercent }}%</strong>.
        </p>
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
                <button type="button" class="btn btn-ghost js-cert-preview" @disabled(! $final->certificate_full_name)>Открыть PNG</button>
                <button type="button" class="btn btn-ghost js-cert-download" @disabled(! $final->certificate_full_name)>Скачать PNG</button>
            </div>
            <p class="muted" style="margin:0;font-size:0.88rem">Файл — <strong>PNG</strong> (картинка): на нём только уровень и ФИО; сумму баллов см. выше. Так сложнее подделать документ в текстовом редакторе.</p>
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
        <p class="muted" style="margin:0">Модули: до 100 баллов каждый (учитываются попытки тестов по теории и итогового теста). Финальная лаба: до 100 баллов с учётом попыток. Сводный итог курса — сумма баллов; от доли от максимума берётся <strong>уровень для бланка</strong>: 90–100% — ALT Linux Administrator — Expert; 70–89% — ALT Linux Administrator; ниже 70% — «Пересдача». Итоговые баллы и процент смотрите в блоке выше и в «Разбивке»; в файле <strong>PNG</strong> они не дублируются — только уровень.</p>
    </div>

    @include('partials.certificate-design-css')

    <div id="certificate-render" style="position:fixed;left:-10000px;top:-10000px;width:1240px;background:#f8fafc;padding:0">
        @include('partials.certificate-paper', [
            'serial' => $final->certificate_serial,
            'issueDate' => $final->certificateDisplayIssueDate()->format('d.m.Y'),
            'recipientName' => $final->certificate_full_name ?: 'Фамилия Имя Отчество',
            'nameExtraClass' => 'js-cert-fullname',
            'certTier' => $certTier,
        ])
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
    <script>
        (function () {
            const previewBtn = document.querySelector('.js-cert-preview');
            const downloadBtn = document.querySelector('.js-cert-download');
            const certBox = document.getElementById('certificate-render');
            const fullNameInput = document.getElementById('certificate_full_name');
            const fullNameNode = certBox ? certBox.querySelector('.js-cert-fullname') : null;
            const certPaper = certBox ? certBox.querySelector('.cert-paper') : null;
            const recipientForm = document.getElementById('certificate-recipient-form');
            const confirmDialog = document.getElementById('certificate-confirm-dialog');
            const confirmCancel = document.querySelector('.js-cert-cancel-confirm');
            const confirmApply = document.querySelector('.js-cert-apply-confirm');

            if (!previewBtn || !downloadBtn || !certBox || !window.html2canvas) {
                return;
            }

            function syncName() {
                if (!fullNameNode || !fullNameInput) return;
                const val = (fullNameInput.value || '').trim();
                fullNameNode.textContent = val || 'Фамилия Имя Отчество';
            }

            function canvasToPngBlob(canvas) {
                return new Promise(function (resolve, reject) {
                    canvas.toBlob(function (blob) {
                        if (blob) {
                            resolve(blob);
                        } else {
                            reject(new Error('toBlob'));
                        }
                    }, 'image/png', 0.95);
                });
            }

            async function makePngBlob() {
                syncName();
                const canvas = await window.html2canvas(certPaper || certBox.firstElementChild, {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#f0fdf9'
                });
                return canvasToPngBlob(canvas);
            }

            async function openPreview() {
                const blob = await makePngBlob();
                const url = URL.createObjectURL(blob);
                window.open(url, '_blank', 'noopener');
                setTimeout(function () { URL.revokeObjectURL(url); }, 120000);
            }

            async function downloadPng() {
                const blob = await makePngBlob();
                const a = document.createElement('a');
                const url = URL.createObjectURL(blob);
                const safeName = (fullNameInput.value || 'certificate').trim().replace(/[^\p{L}\p{N}\-_ ]/gu, '').replace(/\s+/g, '_');
                a.href = url;
                a.download = `certificate_${safeName || 'course'}.png`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(function () { URL.revokeObjectURL(url); }, 30000);
            }

            previewBtn.addEventListener('click', function () { openPreview().catch(function () {}); });
            downloadBtn.addEventListener('click', function () { downloadPng().catch(function () {}); });
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
