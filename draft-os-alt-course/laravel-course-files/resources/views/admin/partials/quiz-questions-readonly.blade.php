{{-- Админ: только просмотр, с подсветкой верных ответов --}}
@foreach ($questions as $i => $q)
    @php
        $q = is_array($q) ? $q : [];
        $opts = $q['a'] ?? [];
        if (! is_array($opts)) {
            $opts = [];
        }
        $correct = $q['c'] ?? null;
        $multi = is_array($correct);
    @endphp
    <div class="admin-readonly-q" style="margin-bottom:1.5rem;padding-bottom:1.25rem;border-bottom:1px solid rgba(0,0,0,0.08)">
        <div class="module-exam-q--md" style="font-weight:600;margin-bottom:0.5rem">
            <span class="muted">{{ $i + 1 }}.</span>
            {!! \App\Support\AdminContentMarkdown::toHtml((string) ($q['q'] ?? '')) !!}
        </div>
        @if (! empty($q['open_text']))
            <p class="muted small" style="margin:0 0 0.5rem">Тип вопроса: открытый ответ (текстовое поле).</p>
            @if (! empty($q['placeholder']))
                <p class="muted small" style="margin:0">Подсказка в поле: {{ $q['placeholder'] }}</p>
            @endif
        @elseif (! empty($q['match_drag']))
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
                            <li>{{ is_scalar($cell) ? $cell : json_encode($cell, JSON_UNESCAPED_UNICODE) }}</li>
                        @endforeach
                    </ol>
                </div>
                <div>
                    <p class="muted small" style="margin:0 0 0.35rem">Справа (эталонный порядок)</p>
                    <ol style="margin:0;padding-left:1.2rem">
                        @foreach ($right as $cell)
                            <li>{{ is_scalar($cell) ? $cell : json_encode($cell, JSON_UNESCAPED_UNICODE) }}</li>
                        @endforeach
                    </ol>
                </div>
            </div>
        @else
            <ul class="admin-readonly-opts" style="list-style:none;padding:0;margin:0.35rem 0 0">
                @foreach ($opts as $j => $opt)
                    @php
                        $isCorrect = $multi
                            ? in_array($j, $correct, true)
                            : (int) $correct === (int) $j;
                    @endphp
                    <li style="margin:0.2rem 0;padding:0.4rem 0.55rem;border-radius:6px;border:1px solid transparent;{{ $isCorrect ? 'background:rgba(22,101,52,0.09);border-color:rgba(22,101,52,0.25);' : '' }}">
                        <span class="muted" style="font-size:0.85rem">{{ $j }}.</span>
                        {{ is_scalar($opt) ? $opt : json_encode($opt, JSON_UNESCAPED_UNICODE) }}
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
