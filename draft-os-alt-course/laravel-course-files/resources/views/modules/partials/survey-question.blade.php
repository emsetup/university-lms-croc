@php
    $i = (int) ($i ?? 0);
    $q = is_array($q ?? null) ? $q : [];
    $preview = ! empty($preview);
    $stepHidden = ! empty($stepHidden);
    $inputPrefix = $preview ? 'preview_q' : 'q';
@endphp
<div class="survey-step @if($preview) survey-step--preview @endif" data-step="{{ $i }}" @if ($stepHidden) hidden @endif role="group" aria-label="Вопрос {{ $i + 1 }}">
    <div class="survey-step__head">
        <span class="survey-step__badge">{{ $i + 1 }}</span>
        <div class="survey-step__text">{!! \App\Support\CourseContentMarkdown::toHtml($q['q'] ?? '') !!}</div>
    </div>

    <div class="survey-step__body">
        @if (!empty($q['open_text']))
            <label class="survey-input-line-wrap" for="survey-{{ $inputPrefix }}{{ $i }}-input">
                <textarea
                    id="survey-{{ $inputPrefix }}{{ $i }}-input"
                    class="survey-input-line js-survey-input"
                    name="{{ $inputPrefix }}{{ $i }}"
                    rows="1"
                    data-q="{{ $i }}"
                    @if(!empty($q['max_length'])) maxlength="{{ (int) $q['max_length'] }}" @endif
                    @if($preview) disabled @else required @endif
                >{{ $preview ? '' : old('q'.$i) }}</textarea>
                <span class="survey-input-line-placeholder">{{ $q['placeholder'] ?? 'Напишите ответ здесь…' }}</span>
            </label>
            @unless ($preview)
                <p class="survey-step__hint">Чтобы добавить абзац, нажмите <kbd>Shift</kbd> + <kbd>Enter</kbd></p>
            @endunless
        @elseif (!empty($q['match_drag']))
            @php $left = $q['left'] ?? []; $right = $q['right'] ?? []; @endphp
            @unless ($preview)
                <input type="hidden" name="q{{ $i }}_order" id="survey-order-{{ $i }}" value="">
            @endunless
            @foreach ($left as $li => $ltxt)
                <div class="survey-match-row">
                    <span class="survey-match-row__label">{!! \App\Support\CourseContentMarkdown::inlineHtml($ltxt) !!}</span>
                    <select class="survey-match-select js-survey-input" data-q="{{ $i }}" @if($preview) disabled @else required @endif>
                        <option value="">— выберите —</option>
                        @foreach ($right as $ri => $rtxt)
                            <option value="{{ $ri }}">{{ preg_replace('/!\[[^\]]*\]\(\/media\/[^)]+\)/', '[картинка] ', $rtxt) }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        @elseif (!empty($q['multi_other']))
            <p class="survey-step__hint">Можно выбрать несколько вариантов и/или вписать свой</p>
            <div class="survey-options" role="group" data-mixed="1">
                @foreach ($q['a'] ?? [] as $j => $opt)
                    <label class="survey-option">
                        <input type="checkbox" class="js-survey-input" name="{{ $inputPrefix }}{{ $i }}[]" value="{{ $j }}" data-q="{{ $i }}" @if($preview) disabled @endif>
                        <span class="survey-option__letter">{{ chr(65 + $j) }}</span>
                        <span class="survey-option__text">{!! \App\Support\CourseContentMarkdown::inlineHtml($opt) !!}</span>
                    </label>
                @endforeach
            </div>
            <div class="survey-other-block">
                <span class="survey-other-label">Свой вариант</span>
                <label class="survey-input-line-wrap" for="survey-{{ $inputPrefix }}{{ $i }}-other">
                    <textarea
                        id="survey-{{ $inputPrefix }}{{ $i }}-other"
                        class="survey-input-line js-survey-input survey-other-input"
                        name="{{ $inputPrefix }}{{ $i }}_other"
                        rows="1"
                        data-q="{{ $i }}"
                        @if(!empty($q['max_length'])) maxlength="{{ (int) $q['max_length'] }}" @endif
                        @if($preview) disabled @endif
                    >{{ $preview ? '' : old('q'.$i.'_other') }}</textarea>
                    <span class="survey-input-line-placeholder">{{ $q['placeholder'] ?? 'Впишите свой вариант…' }}</span>
                </label>
            </div>
        @else
            @php $multi = isset($q['c']) && is_array($q['c']); @endphp
            @if ($multi)
                <p class="survey-step__hint">Можно выбрать несколько вариантов</p>
            @endif
            <div class="survey-options" role="{{ $multi ? 'group' : 'radiogroup' }}">
                @foreach ($q['a'] ?? [] as $j => $opt)
                    <label class="survey-option">
                        @if ($multi)
                            <input type="checkbox" class="js-survey-input" name="{{ $inputPrefix }}{{ $i }}[]" value="{{ $j }}" data-q="{{ $i }}" @if($preview) disabled @endif>
                        @else
                            <input type="radio" class="js-survey-input" name="{{ $inputPrefix }}{{ $i }}" value="{{ $j }}" data-q="{{ $i }}" @if($preview) disabled @elseif($loop->first) required @endif>
                        @endif
                        <span class="survey-option__letter">{{ chr(65 + $j) }}</span>
                        <span class="survey-option__text">{!! \App\Support\CourseContentMarkdown::inlineHtml($opt) !!}</span>
                    </label>
                @endforeach
            </div>
        @endif
    </div>
</div>
