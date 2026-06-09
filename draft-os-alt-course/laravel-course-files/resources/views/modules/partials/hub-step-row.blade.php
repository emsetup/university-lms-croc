@php
    use App\Models\CourseSection;
    use App\Support\SectionProgress;

    $legacy = $section->legacyTypeKey();
    $sole = isset($sectionService) ? $sectionService->isSoleSectionOfType($section) : true;
    $routeName = $section->learnerRouteName();
    $openTag = ($accessible && ! $waived && $routeName) ? 'a' : 'div';
    $closeTag = ($accessible && ! $waived && $routeName) ? 'a' : 'div';
    $href = ($accessible && ! $waived && $routeName)
        ? route($routeName, $section->learnerRouteParams((int) ($courseId ?? session('course_id')), (int) $module))
        : null;
    $tqPassedEffective = ($section->type === CourseSection::TYPE_QUIZ && isset($sectionService))
        ? $sectionService->isSectionQuizPassed($p, $section, $sole)
        : (bool) ($p->theory_quiz_passed ?? false);
    $quizSt = SectionProgress::quizState($p, $section, $sole);
@endphp
<li>
@if ($openTag === 'a')
    <a class="hub-row section-card" href="{{ $href }}">
@else
    <div class="hub-row section-card hub-row--disabled locked" role="group" aria-label="{{ $title }}: недоступно">
@endif
        <div class="hub-row__left">
            <span class="hub-idx" aria-hidden="true">{{ $idx }}</span>
            <span class="hub-title-wrap"><span class="hub-title">{{ $title }}</span></span>
        </div>
        <div class="hub-meta">
            @if ($section->type === CourseSection::TYPE_TEXT)
                @php $textDone = SectionProgress::isTextRead($p, $section, $sole); @endphp
                <div class="hub-line1">
                    <div class="hub-track" title="Этап: просмотр теории">
                        <div class="hub-track__fill{{ $textDone ? '' : ' hub-track__fill--muted' }}" style="width: {{ $textDone ? 100 : 0 }}%"></div>
                    </div>
                    <span class="hub-pct hub-pct--muted">{{ $textDone ? '100' : '0' }}%</span>
                    @if ($textDone)
                        <span class="hub-badge hub-badge--ok badge-done">Готово</span>
                    @else
                        <span class="hub-badge hub-badge--wait">Дальше</span>
                    @endif
                </div>
                <p class="hub-line2">{{ $textDone ? 'Материал просмотрен' : 'Откройте материал и отметьте просмотр' }}</p>
            @elseif ($section->type === CourseSection::TYPE_QUIZ)
                @php
                    $tqAtt = (int) ($quizSt['attempts'] ?? 0);
                    $tqBest = (int) ($quizSt['best_score'] ?? 0);
                    $tqBar = $tqAtt > 0 ? min(100, $tqBest) : 0;
                    $tqLast = is_array($quizSt['last_result'] ?? null) ? $quizSt['last_result'] : [];
                    $tqParts = [];
                    if ($tqAtt > 0 && isset($tqLast['correct_count'], $tqLast['total'])) {
                        $tqParts[] = (int) $tqLast['correct_count'].'/'.(int) $tqLast['total'].' верных';
                    }
                    if ($tqAtt > 0) {
                        $tqParts[] = 'попыток: '.$tqAtt;
                    }
                    if (! empty($tqLast['penalty_points'])) {
                        $tqParts[] = 'штраф −'.(int) $tqLast['penalty_points'].' п.п.';
                    }
                    $tqLine2 = $tqAtt > 0 ? implode(' · ', $tqParts) : 'Порог зачёта '.$th.'% — после попытки здесь появится результат';
                @endphp
                <div class="hub-line1">
                    <div class="hub-track" title="Лучший результат, порог {{ $th }}%">
                        <span class="hub-track__tick" style="left: {{ $th }}%"></span>
                        <div class="hub-track__fill{{ $tqBar >= $th ? '' : ' hub-track__fill--muted' }}" style="width: {{ (int) $tqBar }}%"></div>
                    </div>
                    <span class="hub-pct">{{ $tqAtt > 0 ? $tqBest : '—' }}@if($tqAtt > 0)%@endif</span>
                    @if ($tqPassedEffective)
                        <span class="hub-badge hub-badge--counted badge-counted">Зачтён</span>
                    @elseif ($tqAtt > 0)
                        <span class="hub-badge hub-badge--no badge-fail">Ниже {{ $th }}%</span>
                    @else
                        <span class="hub-badge hub-badge--wait">Тест</span>
                    @endif
                </div>
                <p class="hub-line2">{{ $tqLine2 }}</p>
            @elseif ($section->type === CourseSection::TYPE_PRACTICE)
                @if ($waived)
                    <div class="hub-line1">
                        <div class="hub-track" title="Нет этапа">
                            <div class="hub-track__fill hub-track__fill--muted" style="width: 0%"></div>
                        </div>
                        <span class="hub-pct hub-pct--muted">—</span>
                        <span class="hub-badge hub-badge--na">Нет</span>
                    </div>
                    <p class="hub-line2 muted" style="margin:0">Практика в этом модуле не входит в курс.</p>
                @else
                    @php $prPct = SectionProgress::practicePercent($p, $section, $sole); @endphp
                    <div class="hub-line1">
                        <div class="hub-track" title="Автопроверка стенда">
                            <div class="hub-track__fill{{ $prPct >= 100 ? '' : ($prPct > 0 ? '' : ' hub-track__fill--muted') }}" style="width: {{ (int) min(100, $prPct) }}%"></div>
                        </div>
                        <span class="hub-pct">{{ $prPct > 0 ? (int) $prPct.'%' : '—' }}</span>
                        @if (SectionProgress::isPracticeDone($p, $section, $sole))
                            <span class="hub-badge hub-badge--ok badge-done">Готово</span>
                        @else
                            <span class="hub-badge hub-badge--wait">Стенд</span>
                        @endif
                    </div>
                    <p class="hub-line2">{{ SectionProgress::isPracticeDone($p, $section, $sole) ? 'Зачтено' : 'После автопроверки стенда — процент по чек-листу' }}</p>
                @endif
            @elseif ($section->type === CourseSection::TYPE_EXAM)
                @php
                    $exAtt = (int) ($quizSt['attempts'] ?? 0);
                    $exBest = (int) ($quizSt['best_score'] ?? 0);
                    $exBar = $exAtt > 0 ? min(100, $exBest) : 0;
                    $exLast = is_array($quizSt['last_result'] ?? null) ? $quizSt['last_result'] : [];
                    $exParts = [];
                    if ($exAtt > 0) {
                        $exParts[] = 'попытка '.$exAtt.'/'.$exMax;
                    }
                    if ($exAtt > 0 && isset($exLast['correct_count'], $exLast['total'])) {
                        $exParts[] = (int) $exLast['correct_count'].'/'.(int) $exLast['total'].' верных';
                    }
                    if ($exAtt > 0 && ! empty($exLast['earned_points']) && ! empty($exLast['max_points'])) {
                        $exParts[] = (int) $exLast['earned_points'].'/'.(int) $exLast['max_points'].' баллов';
                    }
                    if ($exAtt > 0 && ! empty($exLast['penalty_applied'])) {
                        $exParts[] = 'пересдача −'.(int) ($exLast['penalty_points'] ?? 10).' п.п.';
                    }
                    if ($exAtt > 0 && isset($exLast['raw_percent']) && (int) $exLast['raw_percent'] !== $exBest) {
                        $exParts[] = 'сырой '.(int) $exLast['raw_percent'].'%';
                    }
                    $exLine2 = $exAtt > 0 ? implode(' · ', $exParts) : 'Порог '.$thEx.'% · до '.$exMax.' попыток';
                    $exPassed = isset($sectionService)
                        ? $sectionService->isSectionExamPassed($p, $section, $sole)
                        : (bool) ($p->module_exam_passed ?? false);
                @endphp
                <div class="hub-line1">
                    <div class="hub-track" title="Итог последней попытки, порог {{ $thEx }}%">
                        <span class="hub-track__tick" style="left: {{ $thEx }}%"></span>
                        <div class="hub-track__fill{{ $exBar >= $thEx ? '' : ' hub-track__fill--muted' }}" style="width: {{ (int) $exBar }}%"></div>
                    </div>
                    <span class="hub-pct">{{ $exAtt > 0 ? $exBest : '—' }}@if($exAtt > 0)%@endif</span>
                    @if ($exPassed)
                        <span class="hub-badge hub-badge--counted badge-counted">Зачтён</span>
                    @elseif ($exAtt > 0)
                        <span class="hub-badge hub-badge--warn">Ещё раз</span>
                    @else
                        <span class="hub-badge hub-badge--wait">Экзамен</span>
                    @endif
                </div>
                <p class="hub-line2">{{ $exLine2 }}</p>
            @elseif ($section->type === CourseSection::TYPE_SURVEY)
                @php
                    $surveyDone = isset($sectionService)
                        ? $sectionService->isSurveyCompleteForSection($p, (int) $section->id)
                        : false;
                @endphp
                <div class="hub-line1">
                    <div class="hub-track" title="Опрос">
                        <div class="hub-track__fill{{ $surveyDone ? '' : ' hub-track__fill--muted' }}" style="width: {{ $surveyDone ? 100 : 0 }}%"></div>
                    </div>
                    <span class="hub-pct hub-pct--muted">{{ $surveyDone ? '100' : '0' }}%</span>
                    @if ($surveyDone)
                        <span class="hub-badge hub-badge--ok badge-done">Готово</span>
                    @else
                        <span class="hub-badge hub-badge--wait">Далее</span>
                    @endif
                </div>
                <p class="hub-line2">{{ $surveyDone ? 'Ответы сохранены' : 'Заполните опрос и отправьте ответы' }}</p>
            @endif
        </div>
        <span class="hub-row__go" aria-hidden="true">@include('partials.ap-icon', ['name' => 'chevron-right'])</span>
@if ($closeTag === 'a')
    </a>
@else
    </div>
@endif
</li>
