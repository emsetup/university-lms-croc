@php
    $nameClass = trim(($nameExtraClass ?? '') . ' cert-recipient-name');
    $issueStr = filled(trim((string) ($issueDate ?? ''))) ? trim((string) $issueDate) : '—';
    $serialStr = $serial ?: 'CROC-ALT-DRAFT';
@endphp
<div class="cert-paper">
    <div class="cert-paper__base" aria-hidden="true"></div>
    <div class="cert-paper__mesh" aria-hidden="true"></div>
    <div class="cert-paper__sheen" aria-hidden="true"></div>
    <div class="cert-paper__stripes" aria-hidden="true"></div>
    <div class="cert-paper__stripes2" aria-hidden="true"></div>
    <div class="cert-paper__accent-line cert-paper__accent-line--tr" aria-hidden="true"></div>
    <div class="cert-paper__accent-line cert-paper__accent-line--bl" aria-hidden="true"></div>
    <div class="cert-corner cert-corner--tl" aria-hidden="true"></div>
    <div class="cert-corner cert-corner--br" aria-hidden="true"></div>
    <div class="cert-paper__frame" aria-hidden="true"></div>
    <div class="cert-paper__frame-glow" aria-hidden="true"></div>

    <div class="cert-seal" aria-hidden="true">
        <div class="cert-seal__wax"></div>
        <div class="cert-seal__ring"></div>
        <div class="cert-seal__star">К</div>
        <div class="cert-seal__ribbon"></div>
        <div class="cert-seal__caption">учебный центр</div>
    </div>

    <div class="cert-paper__content">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <img src="{{ asset('croc-wordmark.svg') }}" alt="КРОК" style="height:38px;display:block">
                <div style="margin-top:12px;font-size:13px;letter-spacing:0.18em;text-transform:uppercase;color:#0f766e;font-weight:700">Образовательный сертификат</div>
            </div>
            <div style="text-align:right;font-size:14px;line-height:1.55">
                <div><strong>№ {{ $serialStr }}</strong></div>
                <div>Дата выдачи: {{ $issueStr }}</div>
            </div>
        </div>

        <div style="margin-top:92px;text-align:center">
            <div style="font-size:20px;color:#0f766e;letter-spacing:0.12em;text-transform:uppercase;font-weight:700">Настоящий сертификат подтверждает, что</div>
            <div style="margin-top:34px;font-size:52px;line-height:1.08;font-weight:800;color:#134e4a" class="{{ $nameClass }}">{{ $recipientName }}</div>
            <div style="margin-top:34px;font-size:24px;line-height:1.45;max-width:900px;margin-left:auto;margin-right:auto">
                успешно завершил(а) курс
                <strong>«Особенности ОС Альт»</strong>
                и подтвердил(а) практические навыки администрирования.
            </div>
        </div>
    </div>

    <div class="cert-paper__footer" style="position:absolute;left:72px;right:72px;bottom:74px;display:flex;justify-content:space-between;align-items:flex-end;gap:24px;z-index:1">
        <div style="font-size:15px;line-height:1.5;color:#334155">
            @php
                $tier = $certTier ?? ['key' => 'administrator', 'label' => 'ALT Linux Administrator'];
            @endphp
            Квалификация (сводно по курсу):<br>
            <strong style="font-size:16px;color:#0f172a">{{ $tier['label'] }}</strong><br>
            <span style="font-size:13px;opacity:0.92">Платформа: учебный курс КРОК</span>
        </div>
        <div style="text-align:right">
            <div style="font-size:14px;color:#334155;margin-bottom:8px">Руководитель программы</div>
            <div style="width:260px;border-top:1px solid #94a3b8;padding-top:8px;font-size:14px;color:#0f172a">КРОК, учебный центр</div>
        </div>
    </div>
</div>
