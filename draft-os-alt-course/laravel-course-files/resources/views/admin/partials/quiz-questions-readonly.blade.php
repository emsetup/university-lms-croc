{{-- Админ: только просмотр, с подсветкой верных ответов --}}
@foreach ($questions as $i => $q)
    <div class="admin-readonly-q" style="margin-bottom:1.5rem;padding-bottom:1.25rem;border-bottom:1px solid rgba(0,0,0,0.08)">
        <div class="module-exam-q--md" style="font-weight:600;margin-bottom:0.5rem">
            <span class="muted">{{ $i + 1 }}.</span>
            {!! \Illuminate\Support\Str::markdown($q['q'] ?? '') !!}
        </div>
        @if (! empty($q['match_drag']))
            @php
                $left = is_array($q['left'] ?? null) ? $q['left'] : [];
                $right = is_array($q['right'] ?? null) ? $q['right'] : [];
                $n = count($left);
            @endphp
            <p class="muted small" style="margin:0 0 0.5rem">Тип вопроса: сопоставление (перетаскивание). Ожидаемый порядок блоков справа: <strong>1…{{ max(1, $n) }}</strong> по строкам слева.</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:start">
                <div>
                    <p class="muted small" style="margin:0 0 0.35rem">Слева</p>
                    <ol style="margin:0;padding-left:1.2rem">
                        @foreach ($left as $cell)
                            <li>{{ $cell }}</li>
                        @endforeach
                    </ol>
                </div>
                <div>
                    <p class="muted small" style="margin:0 0 0.35rem">Справа (эталонный порядок)</p>
                    <ol style="margin:0;padding-left:1.2rem">
                        @foreach ($right as $cell)
                            <li>{{ $cell }}</li>
                        @endforeach
                    </ol>
                </div>
            </div>
        @else
            @php $multi = isset($q['c']) && is_array($q['c']); @endphp
            <ul class="admin-readonly-opts" style="list-style:none;padding:0;margin:0.35rem 0 0">
                @foreach ($q['a'] ?? [] as $j => $opt)
                    @php
                        $isCorrect = $multi ? in_array($j, $q['c'], true) : (int) ($q['c'] ?? -1) === $j;
                    @endphp
                    <li style="margin:0.2rem 0;padding:0.4rem 0.55rem;border-radius:6px;border:1px solid transparent;{{ $isCorrect ? 'background:rgba(22,101,52,0.09);border-color:rgba(22,101,52,0.25);' : '' }}">
                        <span class="muted" style="font-size:0.85rem">{{ $j }}.</span>
                        {{ $opt }}
                        @if ($isCorrect)
                            <strong style="color:#14532d;font-size:0.85rem"> — верно</strong>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if (isset($q['points']) && (int) $q['points'] > 0)
                <p class="muted small" style="margin:0.5rem 0 0">Вес вопроса: {{ (int) $q['points'] }} б.</p>
            @endif
        @endif
    </div>
@endforeach
