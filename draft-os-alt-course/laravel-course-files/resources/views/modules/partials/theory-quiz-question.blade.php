@php
    $q = is_array($q ?? null) ? $q : [];
    $matchDrag = ! empty($q['match_drag']);
    $openText = ! empty($q['open_text']);
    $multi = ! $matchDrag && ! $openText && isset($q['c']) && is_array($q['c']);
    $opts = is_array($q['a'] ?? null) ? $q['a'] : [];
    $inputPrefix = $inputPrefix ?? 'q';
    $preview = ! empty($preview);
@endphp
<div class="quiz-q">
    <div class="quiz-q-text quiz-q-text--md">
        <span class="quiz-q-num">{{ $i + 1 }}.</span>
        {!! \App\Support\CourseContentMarkdown::toHtml((string) ($q['q'] ?? '')) !!}
    </div>
    @if ($openText)
        <p class="muted small" style="margin:0.35rem 0 0.5rem">Введите развёрнутый ответ.</p>
        @if ($preview)
            <p class="muted small" style="margin:0">Тип вопроса: открытый ответ.</p>
        @else
            <textarea
                name="{{ $inputPrefix }}{{ $i }}"
                rows="4"
                class="input"
                style="width:100%;max-width:40rem"
                @if (! empty($q['placeholder'])) placeholder="{{ $q['placeholder'] }}" @endif
                @if (! empty($q['max_length'])) maxlength="{{ (int) $q['max_length'] }}" @endif
            ></textarea>
        @endif
    @elseif ($matchDrag)
        @php
            $mLeft = is_array($q['left'] ?? null) ? $q['left'] : [];
            $mRight = is_array($q['right'] ?? null) ? $q['right'] : [];
            $mN = count($mLeft);
            $mPerm = range(0, max(0, $mN - 1));
            if (! $preview) {
                shuffle($mPerm);
            }
        @endphp
        @if ($preview)
            <p class="muted small" style="margin:0.35rem 0 0.5rem">Тип вопроса: сопоставление (перетаскивание).</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:start">
                <div>
                    <p class="muted small" style="margin:0 0 0.35rem">Слева</p>
                    <ol style="margin:0;padding-left:1.2rem">
                        @foreach ($mLeft as $cell)
                            <li>{!! \App\Support\CourseContentMarkdown::inlineHtml((string) $cell) !!}</li>
                        @endforeach
                    </ol>
                </div>
                <div>
                    <p class="muted small" style="margin:0 0 0.35rem">Справа (эталон)</p>
                    <ol style="margin:0;padding-left:1.2rem">
                        @foreach ($mRight as $cell)
                            <li>{!! \App\Support\CourseContentMarkdown::inlineHtml((string) $cell) !!}</li>
                        @endforeach
                    </ol>
                </div>
            </div>
        @else
            <p class="muted small" style="margin:0.35rem 0 0.5rem">Перетащите блоки справа в нужный порядок напротив строк слева.</p>
            <div class="module-exam-match">
                <div class="module-exam-match__left" aria-label="Фиксированные подписи">
                    @foreach ($mLeft as $li => $label)
                        <div class="module-exam-match__row">
                            <span class="module-exam-match__ln">{{ $li + 1 }}</span>
                            <div class="module-exam-match__cell">{!! \App\Support\CourseContentMarkdown::inlineHtml((string) $label) !!}</div>
                        </div>
                    @endforeach
                </div>
                <div class="module-exam-match__right">
                    <div class="muted small" style="margin:0 0 0.35rem">Описания (перетащите в нужный порядок):</div>
                    <ul class="module-exam-match__list js-match-drag-list" id="tq-match-drag-{{ $i }}" data-q="{{ $i }}">
                        @foreach ($mPerm as $descIdx)
                            <li draggable="true" class="module-exam-match__card" data-desc-idx="{{ (int) $descIdx }}">{!! \App\Support\CourseContentMarkdown::inlineHtml((string) ($mRight[$descIdx] ?? '')) !!}</li>
                        @endforeach
                    </ul>
                    <input type="hidden" name="{{ $inputPrefix }}{{ $i }}_order" class="js-match-order" value="{{ implode(',', $mPerm) }}" autocomplete="off">
                </div>
            </div>
        @endif
    @elseif ($multi)
        <p class="muted small" style="margin:0.35rem 0 0.5rem">Отметьте все верные варианты.</p>
        @foreach ($opts as $j => $opt)
            <label class="choice">
                <input type="checkbox" name="{{ $preview ? 'preview_'.$inputPrefix : $inputPrefix }}{{ $i }}[]" value="{{ $j }}">
                <span>{!! \App\Support\CourseContentMarkdown::inlineHtml((string) $opt) !!}</span>
            </label>
        @endforeach
    @else
        @forelse ($opts as $j => $opt)
            <label class="choice">
                <input type="radio" name="{{ $preview ? 'preview_'.$inputPrefix : $inputPrefix }}{{ $i }}" value="{{ $j }}" @if (! $preview && $loop->first) required @endif>
                <span>{!! \App\Support\CourseContentMarkdown::inlineHtml((string) $opt) !!}</span>
            </label>
        @empty
            <p class="muted small" style="margin:0">Варианты ответа для этого вопроса не настроены.</p>
        @endforelse
    @endif
</div>
