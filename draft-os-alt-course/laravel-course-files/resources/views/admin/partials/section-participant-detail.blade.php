@php
    /** @var array $detail */
@endphp
<div class="ap-sp-detail-card">
    @if (!empty($detail['learner']))
        <h2 class="ap-sp-detail-card__title">{{ $detail['learner']['display_name'] ?? '' }}</h2>
        @if (!empty($detail['learner']['email']))
            <p class="ap-muted small">{{ $detail['learner']['email'] }}</p>
        @endif
    @else
        <h2 class="ap-sp-detail-card__title">Результат</h2>
    @endif
    <p>
        <span class="ap-section-participants__status">{{ $detail['status_label'] ?? '' }}</span>
        @if (!empty($detail['completed_at']))
            · {{ $detail['completed_at'] }}
        @endif
    </p>

    @if (!empty($detail['survey']))
        @php $sv = $detail['survey']; @endphp
        @if (!empty($sv['anonymous']))
            <p class="ap-muted">Анонимный опрос — текст ответов скрыт.</p>
        @elseif (empty($sv['submitted']))
            <p class="ap-muted">Ещё не отправил ответы.</p>
        @elseif (empty($sv['items']))
            <p class="ap-muted">Ответов нет.</p>
        @else
            <ul class="ap-report-survey-answers">
                @foreach ($sv['items'] as $it)
                    <li class="ap-report-survey-answers__item">
                        <p class="ap-report-survey-answers__q">{{ $it['question'] ?? '' }}</p>
                        <p class="ap-report-survey-answers__a">{{ $it['answer'] ?? '—' }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    @elseif (!empty($detail['quiz']))
        @php $qz = $detail['quiz']; @endphp
        <p class="ap-muted small">Лучший результат: {{ (int) ($qz['best_score'] ?? 0) }}% · попыток: {{ (int) ($qz['attempts'] ?? 0) }}</p>
        @if (!empty($qz['items']))
            <ul class="ap-report-survey-answers">
                @foreach ($qz['items'] as $i => $it)
                    @php
                        $q = $it['question_text'] ?? $it['question'] ?? ('Вопрос '.($i + 1));
                        $a = $it['display'] ?? $it['answer'] ?? $it['chosen_label'] ?? '—';
                    @endphp
                    <li class="ap-report-survey-answers__item">
                        <p class="ap-report-survey-answers__q">{{ $q }}</p>
                        <p class="ap-report-survey-answers__a">{{ is_array($a) ? json_encode($a, JSON_UNESCAPED_UNICODE) : $a }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    @elseif (!empty($detail['simple']))
        <p>{{ $detail['simple']['message'] ?? '' }}</p>
    @endif
</div>
