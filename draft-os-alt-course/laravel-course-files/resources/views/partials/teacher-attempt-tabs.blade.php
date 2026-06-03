@php
    $attempts = is_array($attempts ?? null) ? $attempts : [];
    $groupId = (string) ($groupId ?? 'attempts');
    $attemptNoKey = (string) ($attemptNoKey ?? 'attempt_no');
    $passedKey = (string) ($passedKey ?? 'passed');
    $penaltyFlagKey = (string) ($penaltyFlagKey ?? '');
    $questionBank = $questionBank ?? [];
    $svgThOk = $svgThOk ?? '';
    $svgThFail = $svgThFail ?? '';
@endphp
@if (count($attempts) === 0)
    <p class="muted" style="margin:0;font-size:14px">Пока нет завершённых попыток.</p>
@else
    <div class="ap-attempt-tabs" id="ap-attempt-tabs-{{ $groupId }}" data-ap-attempt-tabs>
        <div class="ap-attempt-tabs__list" role="tablist" aria-label="Попытки">
            @foreach ($attempts as $i => $att)
                @php
                    $no = (int) ($att[$attemptNoKey] ?? ($i + 1));
                    $pct = (int) ($att['final_percent'] ?? 0);
                    $passed = ! empty($att[$passedKey]);
                    $failed = array_key_exists($passedKey, $att) && ! $att[$passedKey];
                @endphp
                <button
                    type="button"
                    class="ap-attempt-tabs__btn{{ $loop->last ? ' is-active' : '' }}"
                    role="tab"
                    id="ap-attempt-tab-{{ $groupId }}-{{ $i }}"
                    aria-controls="ap-attempt-panel-{{ $groupId }}-{{ $i }}"
                    aria-selected="{{ $loop->last ? 'true' : 'false' }}"
                    tabindex="{{ $loop->last ? '0' : '-1' }}"
                    data-ap-attempt-tab="{{ $i }}"
                >
                    <span class="ap-attempt-tabs__btn-label">Попытка {{ $no }}</span>
                    <span class="ap-attempt-tabs__btn-pct">{{ $pct }}%</span>
                    @if ($passed)
                        <span class="badge-threshold badge-threshold--ok ap-attempt-tabs__btn-badge">порог{!! $svgThOk !!}</span>
                    @elseif ($failed)
                        <span class="badge-threshold badge-threshold--fail ap-attempt-tabs__btn-badge">порог{!! $svgThFail !!}</span>
                    @endif
                </button>
            @endforeach
        </div>
        @foreach ($attempts as $i => $att)
            @php
                $no = (int) ($att[$attemptNoKey] ?? ($i + 1));
                $hasPenalty = $penaltyFlagKey !== ''
                    ? ! empty($att[$penaltyFlagKey])
                    : ! empty($att['penalty_points']);
                $penaltyPts = (int) ($att['penalty_points'] ?? 10);
            @endphp
            <div
                class="ap-attempt-tabs__panel{{ $loop->last ? ' is-active' : '' }}"
                role="tabpanel"
                id="ap-attempt-panel-{{ $groupId }}-{{ $i }}"
                aria-labelledby="ap-attempt-tab-{{ $groupId }}-{{ $i }}"
                @if (! $loop->last) hidden @endif
                data-ap-attempt-panel="{{ $i }}"
            >
                <div class="attempt-card attempt-card--tabbed">
                    <div class="attempt-header">
                        <span>Попытка {{ $no }}</span>
                        @if (! empty($att[$passedKey]))
                            <span class="badge-threshold badge-threshold--ok">порог{!! $svgThOk !!}</span>
                        @elseif (array_key_exists($passedKey, $att) && ! $att[$passedKey])
                            <span class="badge-threshold badge-threshold--fail">порог{!! $svgThFail !!}</span>
                        @endif
                    </div>
                    <p class="attempt-meta">
                        Итог: {{ (int) ($att['final_percent'] ?? 0) }}%
                        @if (isset($att['raw_percent']))
                            <span class="muted">&nbsp;·&nbsp;</span>Сырой: {{ (int) $att['raw_percent'] }}%
                        @endif
                    </p>
                    <p class="attempt-meta" style="margin-bottom:0">
                        @if (! empty($att['recorded_at'])){{ $att['recorded_at'] }}@endif
                        @if ($hasPenalty)<span> · штраф −{{ $penaltyPts }} п.п.</span>@endif
                    </p>
                    @include('partials.teacher-quiz-breakdown-items', [
                        'items' => $att['items'] ?? [],
                        'questionBank' => $questionBank,
                    ])
                </div>
            </div>
        @endforeach
    </div>
@endif
