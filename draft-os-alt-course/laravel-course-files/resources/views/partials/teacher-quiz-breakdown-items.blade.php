@php
    use App\Support\TeacherQuizLabels;
@endphp
@if (empty($items) || ! is_array($items))
    <p class="muted">Нет сохранённого разбора по вопросам для этой попытки.</p>
@else
    <div style="overflow:auto">
        <table class="teacher-report-table teacher-quiz-items">
            <thead>
            <tr>
                <th>№</th>
                <th>Вопрос</th>
                <th>Варианты</th>
                <th>Эталон / выбор</th>
                <th>Итог</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($items as $it)
                @php
                    $n = (int) ($it['n'] ?? 0);
                    $bank = ($n >= 1 && isset($questionBank[$n - 1])) ? $questionBank[$n - 1] : null;
                    $matchDrag = ! empty($it['match_drag']);
                    $opts = $it['options'] ?? ($bank['a'] ?? []);
                    $exp = $it['expected'] ?? ($bank['c'] ?? null);
                    $multi = ! empty($it['multi']);
                    $hasChosenKey = array_key_exists('chosen', $it);
                    $chosen = $hasChosenKey ? $it['chosen'] : null;
                    $legacyChoice = ! $hasChosenKey;
                @endphp
                <tr>
                    <td class="teacher-report-nowrap">{{ $n }}</td>
                    <td>{{ $it['question'] ?? ($bank['q'] ?? '—') }}</td>
                    <td>
                        @if ($matchDrag)
                            @php
                                $mLeft = $it['left'] ?? [];
                                $mRight = $it['right'] ?? [];
                                $chOrd = $it['chosen_order'] ?? [];
                            @endphp
                            <div class="muted small" style="max-width:32rem">
                                <div><span class="muted">Тип:</span> сопоставление (порядок справа)</div>
                                <ol style="margin:0.35rem 0 0;padding-left:1.1rem">
                                    @foreach ($mLeft as $ri => $lbl)
                                        <li style="margin-bottom:0.2rem"><code style="font-size:0.78rem">{{ $lbl }}</code></li>
                                    @endforeach
                                </ol>
                                <div style="margin-top:0.35rem"><span class="muted">Ответ (порядок индексов описаний сверху вниз):</span>
                                    {{ is_array($chOrd) && $chOrd !== [] ? implode(', ', array_map('intval', $chOrd)) : '—' }}</div>
                            </div>
                        @else
                            <ul class="muted teacher-quiz-items__opts" style="margin:0;padding-left:1rem;max-width:28rem">
                                @foreach ($opts as $oi => $label)
                                    @php
                                        $isExp = $multi
                                            ? (is_array($exp) && in_array((int) $oi, array_map('intval', $exp), true))
                                            : (int) $oi === (int) $exp;
                                        $isCh = false;
                                        if (! $legacyChoice && $chosen !== null) {
                                            $isCh = $multi
                                                ? (is_array($chosen) && in_array((int) $oi, array_map('intval', $chosen), true))
                                                : (int) $oi === (int) $chosen;
                                        }
                                    @endphp
                                    <li>
                                        <strong>{{ TeacherQuizLabels::letter((int) $oi) }})</strong>
                                        @if ($isExp)<span class="teacher-tag teacher-tag--exp">эталон</span>@endif
                                        @if ($isCh)<span class="teacher-tag teacher-tag--ch">выбор</span>@endif
                                        {{ $label }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td class="small">
                        @if ($matchDrag)
                            <span class="muted">Эталон порядка:</span> {{ implode(', ', range(0, max(0, count($mLeft) - 1))) }}
                            <br>
                            <span class="muted">Выбор:</span> {{ is_array($chOrd) && $chOrd !== [] ? implode(', ', array_map('intval', $chOrd)) : '—' }}
                        @elseif ($legacyChoice)
                            <span class="muted">Выбор не сохранён (старая версия приложения).</span><br>
                            <span class="muted">Эталон:</span> {{ TeacherQuizLabels::lettersList($exp) }}
                            <br>
                            <span class="muted">Выбор:</span>
                            —
                        @else
                            <span class="muted">Эталон:</span> {{ TeacherQuizLabels::lettersList($exp) }}
                            <br>
                            <span class="muted">Выбор:</span>
                            @if ($multi)
                                {{ is_array($chosen) && $chosen !== [] ? TeacherQuizLabels::lettersList($chosen) : '— (пусто)' }}
                            @else
                                {{ $chosen !== null && $chosen !== '' ? TeacherQuizLabels::lettersList((int) $chosen) : '—' }}
                            @endif
                        @endif
                    </td>
                    <td class="teacher-report-nowrap">
                        @if (! empty($it['skipped']))
                            <span class="tqi-pill tqi-pill--skip" title="Вопрос пропущен">—</span>
                        @elseif (! empty($it['correct']))
                            <span class="tqi-pill tqi-pill--ok" title="Верно">✓</span>
                        @else
                            <span class="tqi-pill tqi-pill--bad" title="Ошибка">✕</span>
                        @endif
                        @if (isset($it['points']))
                            <div class="tqi-pts" title="Баллы за вопрос"><span class="tqi-pts__n">{{ (int) ($it['earned_points'] ?? 0) }}</span><span class="tqi-pts__d">/</span><span class="tqi-pts__m">{{ (int) $it['points'] }}</span></div>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
<style>
    .teacher-quiz-items__opts li { margin-bottom: 0.2rem; }
    .teacher-tag { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; margin-left: 0.25rem; padding: 0.08rem 0.28rem; border-radius: 4px; vertical-align: middle; }
    .teacher-tag--exp { background: #e8fdf6; color: var(--croc-600); }
    .teacher-tag--ch { background: #eef2ff; color: #3b5bdb; }
    .tqi-pill{
        display:inline-flex;align-items:center;justify-content:center;width:1.85rem;height:1.85rem;border-radius:50%;
        font-size:0.78rem;font-weight:800;
    }
    .tqi-pill--ok{background:#d9f5e4;color:#0d5c2f;border:2px solid #9dd9b5}
    .tqi-pill--bad{background:#fde8e6;color:#9b1c1c;border:2px solid #e8b4b0}
    .tqi-pill--skip{background:#eef1f5;color:#5c6b76;border:2px solid #d5dbe4}
    .tqi-pts{margin-top:0.35rem;display:inline-flex;align-items:center;gap:0.08rem;padding:0.15rem 0.45rem;border-radius:999px;background:#f4f7fa;border:1px solid #dde3ea;font-size:0.72rem;font-weight:700}
    .tqi-pts__n{color:#0f172a}.tqi-pts__d{color:#94a3b8;font-weight:600}.tqi-pts__m{color:#64748b}
</style>
