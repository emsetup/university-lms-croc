@php
    /** @var array $assessmentSnapshot */
    /** @var bool $allDone */
    $snap = $assessmentSnapshot;
    $rows = $snap['rows'] ?? [];
    $summaryParts = $snap['summary_parts'] ?? [];
    $th = (int) ($snap['pass_threshold'] ?? 70);
    $diffLabels = [
        'theory' => 'теория',
        'theory_quiz' => 'тест по теории',
        'practice' => 'практика',
        'module_exam' => 'итоговый тест',
    ];
    $summaryLabelList = implode(', ', array_map(static fn (array $sp): string => mb_strtolower((string) ($sp['label'] ?? '')), $summaryParts));
@endphp

<div class="dash-snap">
    <div class="dash-snap__intro">
        <p class="dash-snap__lead">
            @if ($summaryParts !== [])
                Ниже — лучшие проценты по каждому оцениваемому этапу модуля
                @if ($summaryLabelList !== '')
                    ({{ $summaryLabelList }})
                @endif
                и итоговый балл за модуль (до {{ \App\Services\CourseScoringService::MAX_POINTS_PER_MODULE }}).
                Состав и веса этапов совпадают с карточкой модуля на дашборде.
            @else
                Ниже — итоговые баллы по модулям курса.
            @endif
            Порог зачёта этапов: <strong>{{ $th }}%</strong>.
        </p>
        @if (! $allDone)
            <div class="dash-snap__banner dash-snap__banner--warn" role="status">
                Чтобы открыть полную страницу итоговой оценки, завершите <strong>все модули</strong> (итоговый оцениваемый этап в каждом). Ниже — текущие показатели по уже начатым модулям.
            </div>
        @endif
    </div>

    <section class="dash-snap__summary" aria-label="Сводка по курсу">
        <h3 class="dash-snap__h3">Средние проценты по курсу</h3>
        <div class="dash-snap__avg-grid">
            @foreach ($summaryParts as $sp)
                @php
                    $pct = (int) ($sp['pct'] ?? 0);
                    $colorKey = (string) ($sp['color_key'] ?? 'part');
                @endphp
                <div class="dash-snap__avg-card">
                    <span class="dash-snap__avg-label">{{ $sp['label'] ?? 'Этап' }}</span>
                    <span class="dash-snap__avg-num">{{ $pct }}%</span>
                    <div class="dash-snap__meter" aria-hidden="true"><span class="dash-snap__meter-fill dash-snap__meter-fill--{{ $colorKey }}" style="width: {{ min(100, max(0, $pct)) }}%"></span></div>
                </div>
            @endforeach
            <div class="dash-snap__avg-card dash-snap__avg-card--total">
                <span class="dash-snap__avg-label">Баллы по модулям</span>
                <span class="dash-snap__avg-num dash-snap__avg-num--xl">{{ $snap['total_points'] }}<span class="dash-snap__avg-of">/{{ $snap['max_points'] }}</span></span>
                <div class="dash-snap__meter dash-snap__meter--total" aria-hidden="true">
                    @php $tpPct = $snap['max_points'] > 0 ? (int) round(100 * $snap['total_points'] / $snap['max_points']) : 0; @endphp
                    <span class="dash-snap__meter-fill dash-snap__meter-fill--tot" style="width: {{ min(100, max(0, $tpPct)) }}%"></span>
                </div>
            </div>
        </div>
    </section>

    <div class="dash-snap__modules" role="list">
        @foreach ($rows as $row)
            @php
                $risk = ! empty($row['any_below_pass']);
                $modClass = 'dash-snap-mod' . ($risk ? ' dash-snap-mod--risk' : ' dash-snap-mod--ok');
                $modNum = (int) ($row['module_sequence'] ?? $row['module_id']);
                $parts = (array) ($row['parts'] ?? []);
                $attemptParts = array_values(array_filter($parts, static fn (array $p): bool => array_key_exists('attempts', $p) && $p['attempts'] !== null));
                $partLabelsByLegacy = [];
                foreach ($parts as $part) {
                    $lk = (string) ($part['legacy_key'] ?? '');
                    if ($lk !== '') {
                        $partLabelsByLegacy[$lk] = (string) ($part['label'] ?? $lk);
                    }
                }
            @endphp
            <article class="{{ $modClass }}" role="listitem">
                <header class="dash-snap-mod__head">
                    <div class="dash-snap-mod__badge" aria-hidden="true">{{ $row['letter'] }}</div>
                    <div class="dash-snap-mod__titles">
                        <h4 class="dash-snap-mod__h4">Модуль {{ $modNum }}</h4>
                        <p class="dash-snap-mod__title">{{ $row['title'] }}</p>
                    </div>
                    <div class="dash-snap-mod__points" title="Итоговый балл за модуль">
                        <span class="dash-snap-mod__points-val">{{ $row['points'] }}</span>
                        <span class="dash-snap-mod__points-max">/ {{ \App\Services\CourseScoringService::MAX_POINTS_PER_MODULE }}</span>
                    </div>
                </header>

                @if ($parts !== [])
                    <div class="dash-snap-mod__bars">
                        @foreach ($parts as $part)
                            @php
                                $p = (int) ($part['pct'] ?? 0);
                                $colorKey = (string) ($part['color_key'] ?? 'part');
                                $low = $p < $th;
                                $weak = ($colorKey === ($row['weak_key'] ?? ''));
                            @endphp
                            <div class="dash-snap-row @if ($low) dash-snap-row--low @endif @if ($weak) dash-snap-row--weak @endif">
                                <div class="dash-snap-row__label">{{ $part['label'] ?? 'Этап' }}</div>
                                <div class="dash-snap-row__track">
                                    <span class="dash-snap-row__fill dash-snap-row__fill--{{ $colorKey }}" style="width: {{ min(100, max(0, $p)) }}%"></span>
                                </div>
                                <div class="dash-snap-row__pct">{{ $p }}%</div>
                            </div>
                        @endforeach
                    </div>
                    @php
                        $legend = (array) ($row['weight_legend'] ?? []);
                    @endphp
                    @if ($legend !== [])
                        <p class="dash-snap-mod__weights muted small">
                            Веса этапов:
                            @foreach ($legend as $leg)
                                {{ $leg['label'] ?? '?' }} {{ $leg['pct'] ?? 0 }}%@if (! $loop->last) · @endif
                            @endforeach
                        </p>
                    @endif
                @else
                    <p class="muted small" style="margin:0.5rem 0 0">В модуле нет оцениваемых этапов.</p>
                @endif

                <footer class="dash-snap-mod__foot">
                    @if ($attemptParts !== [])
                        <span class="dash-snap-meta">Попыток:
                            @foreach ($attemptParts as $ap)
                                {{ $ap['label'] ?? 'Этап' }} — {{ (int) ($ap['attempts'] ?? 0) }}@if (! $loop->last); @endif
                            @endforeach
                        </span>
                    @endif
                    @php $flags = array_filter((array) ($row['difficulties'] ?? [])); @endphp
                    @if (count($flags))
                        <div class="dash-snap-tags">
                            <span class="dash-snap-meta">Отмечено сложным:</span>
                            @foreach ($flags as $k => $_)
                                <span class="dash-snap-tag">{{ $partLabelsByLegacy[$k] ?? ($diffLabels[$k] ?? $k) }}</span>
                            @endforeach
                        </div>
                    @endif
                    @if ($risk)
                        <p class="dash-snap-hint dash-snap-hint--risk">Есть этап ниже {{ $th }}% — при желании вернитесь в модуль и закрепите материал.</p>
                    @elseif (! empty($row['weak_key']))
                        @php
                            $weakLabel = '';
                            foreach ($parts as $pt) {
                                if (($pt['color_key'] ?? '') === ($row['weak_key'] ?? '')) {
                                    $weakLabel = (string) ($pt['label'] ?? '');
                                    break;
                                }
                            }
                        @endphp
                        @if ($weakLabel !== '')
                            <p class="dash-snap-hint">Относительно остальных этапов модуля ниже всего: {{ $weakLabel }}.</p>
                        @endif
                    @endif
                </footer>
            </article>
        @endforeach
    </div>
</div>

<style>
    .dash-snap { --dash-snap-tq: #0d9488; --dash-snap-pr: #7c3aed; --dash-snap-ex: #c2410c; --dash-snap-tot: #0f766e; }
    .dash-snap__intro { margin-bottom: 1.25rem; }
    .dash-snap__lead { margin: 0; font-size: 0.92rem; line-height: 1.55; color: var(--muted, #5c6b76); }
    .dash-snap__banner { margin-top: 0.85rem; padding: 0.65rem 0.85rem; border-radius: 10px; font-size: 0.88rem; line-height: 1.45; }
    .dash-snap__banner--warn { background: linear-gradient(90deg, rgba(251, 191, 36, 0.2), rgba(254, 243, 199, 0.55)); border: 1px solid rgba(245, 158, 11, 0.45); color: #78350f; }
    .dash-snap__h3 { margin: 0 0 0.65rem; font-size: 1rem; color: var(--text, #0f172a); }
    .dash-snap__avg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; }
    .dash-snap__avg-card { border: 1px solid var(--line, #dfe8e4); border-radius: 12px; padding: 0.65rem 0.75rem; background: linear-gradient(165deg, #fafcfb, #fff); box-shadow: 0 2px 10px rgba(15, 42, 30, 0.04); }
    .dash-snap__avg-card--total { grid-column: span 1; border-color: rgba(15, 118, 110, 0.35); background: linear-gradient(165deg, #ecfdf5, #fff); }
    .dash-snap__avg-label { display: block; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted, #5c6b76); font-weight: 600; }
    .dash-snap__avg-num { display: block; font-size: 1.35rem; font-weight: 800; color: var(--text, #0f172a); margin: 0.2rem 0 0.35rem; }
    .dash-snap__avg-num--xl { font-size: 1.55rem; color: var(--dash-snap-tot); }
    .dash-snap__avg-of { font-size: 0.95rem; font-weight: 600; color: var(--muted, #5c6b76); }
    .dash-snap__meter { height: 6px; border-radius: 99px; background: rgba(15, 23, 42, 0.08); overflow: hidden; }
    .dash-snap__meter--total { height: 8px; }
    .dash-snap__meter-fill { display: block; height: 100%; border-radius: 99px; transition: width 0.85s cubic-bezier(0.22, 1, 0.36, 1); }
    .dash-snap__meter-fill--tq { background: linear-gradient(90deg, #0f766e, #2dd4bf); }
    .dash-snap__meter-fill--pr { background: linear-gradient(90deg, #5b21b6, #a78bfa); }
    .dash-snap__meter-fill--ex { background: linear-gradient(90deg, #9a3412, #fb923c); }
    .dash-snap__meter-fill--part { background: linear-gradient(90deg, #475569, #94a3b8); }
    .dash-snap__meter-fill--tot { background: linear-gradient(90deg, #065f46, #34d399); box-shadow: 0 0 12px rgba(52, 211, 153, 0.45); }
    .dash-snap__modules { display: flex; flex-direction: column; gap: 1rem; }
    .dash-snap-mod { border-radius: 14px; border: 1px solid var(--line, #dfe8e4); padding: 0.85rem 1rem 1rem; background: #fff; box-shadow: 0 2px 14px rgba(15, 23, 42, 0.04); transition: box-shadow 0.2s, border-color 0.2s, transform 0.2s; }
    .dash-snap-mod:hover { box-shadow: 0 8px 28px rgba(15, 23, 42, 0.08); transform: translateY(-1px); }
    .dash-snap-mod--ok { border-color: rgba(13, 148, 136, 0.25); }
    .dash-snap-mod--risk { border-color: rgba(220, 38, 38, 0.35); background: linear-gradient(180deg, #fffefe, #fff); box-shadow: 0 0 0 1px rgba(254, 202, 202, 0.6), 0 6px 22px rgba(185, 28, 28, 0.08); }
    .dash-snap-mod__head { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 0.65rem; margin-bottom: 0.75rem; }
    .dash-snap-mod__badge { flex-shrink: 0; width: 2.5rem; height: 2.5rem; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem; background: linear-gradient(145deg, #ecfdf5, #d1fae5); color: #065f46; border: 1px solid rgba(6, 95, 70, 0.2); }
    .dash-snap-mod--risk .dash-snap-mod__badge { background: linear-gradient(145deg, #fef2f2, #fee2e2); color: #991b1b; border-color: rgba(153, 27, 27, 0.2); }
    .dash-snap-mod__titles { flex: 1; min-width: 0; }
    .dash-snap-mod__h4 { margin: 0; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted, #5c6b76); font-weight: 700; }
    .dash-snap-mod__title { margin: 0.15rem 0 0; font-size: 0.95rem; font-weight: 700; color: var(--text, #0f172a); line-height: 1.35; }
    .dash-snap-mod__points { text-align: right; margin-left: auto; }
    .dash-snap-mod__points-val { font-size: 1.5rem; font-weight: 800; color: var(--dash-snap-tot); }
    .dash-snap-mod__points-max { font-size: 0.85rem; color: var(--muted, #5c6b76); font-weight: 600; }
    .dash-snap-mod__bars { display: flex; flex-direction: column; gap: 0.45rem; }
    .dash-snap-mod__weights { margin: 0.45rem 0 0; font-size: 0.76rem; }
    .dash-snap-row { display: grid; grid-template-columns: minmax(0, 7.5rem) 1fr 2.75rem; gap: 0.35rem 0.5rem; align-items: center; font-size: 0.82rem; }
    .dash-snap-row__label { color: var(--muted, #5c6b76); font-weight: 600; }
    .dash-snap-row__track { height: 10px; border-radius: 99px; background: rgba(15, 23, 42, 0.06); overflow: hidden; }
    .dash-snap-row__fill { display: block; height: 100%; border-radius: 99px; transition: width 0.9s cubic-bezier(0.22, 1, 0.36, 1); }
    .dash-snap-row__fill--tq { background: linear-gradient(90deg, #0f766e, #5eead4); }
    .dash-snap-row__fill--pr { background: linear-gradient(90deg, #6d28d9, #c4b5fd); }
    .dash-snap-row__fill--ex { background: linear-gradient(90deg, #c2410c, #fdba74); }
    .dash-snap-row__fill--part { background: linear-gradient(90deg, #475569, #94a3b8); }
    .dash-snap-row__pct { text-align: right; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--text, #0f172a); }
    .dash-snap-row--low .dash-snap-row__pct { color: #b45309; }
    .dash-snap-row--weak .dash-snap-row__label { color: #0f172a; }
    .dash-snap-row--weak .dash-snap-row__track { box-shadow: 0 0 0 1px rgba(251, 191, 36, 0.5); }
    .dash-snap-mod__foot { margin-top: 0.65rem; padding-top: 0.55rem; border-top: 1px dashed rgba(15, 23, 42, 0.1); font-size: 0.78rem; color: var(--muted, #5c6b76); }
    .dash-snap-meta { display: inline; }
    .dash-snap-tags { margin-top: 0.35rem; display: flex; flex-wrap: wrap; gap: 0.25rem; align-items: center; }
    .dash-snap-tag { display: inline-block; padding: 0.12rem 0.45rem; border-radius: 6px; background: rgba(59, 130, 246, 0.12); color: #1e40af; font-size: 0.75rem; font-weight: 600; }
    .dash-snap-hint { margin: 0.4rem 0 0; font-size: 0.78rem; color: var(--muted, #5c6b76); }
    .dash-snap-hint--risk { color: #9a3412; font-weight: 600; }
</style>
