@php
    /** @var string $column */
    /** @var string $label */
    /** @var string $staffSort */
    /** @var string $staffDir */
    /** @var string $staffSearch */
    $isActive = $staffSort === $column;
    $defaultDir = $column === 'login' ? 'desc' : 'asc';
    $nextDir = $isActive ? ($staffDir === 'asc' ? 'desc' : 'asc') : $defaultDir;
    $params = array_filter([
        'sort' => $column,
        'dir' => $nextDir,
        'q' => ($staffSearch ?? '') !== '' ? $staffSearch : null,
    ], fn ($v) => $v !== null && $v !== '');
    $href = route('admin.staff.index', $params);
    $ariaSort = $isActive ? ($staffDir === 'asc' ? 'ascending' : 'descending') : 'none';
    $title = $isActive
        ? ($staffDir === 'asc' ? 'Сортировка по возрастанию — нажмите для убывания' : 'Сортировка по убыванию — нажмите для возрастания')
        : 'Сортировать: '.$label;
@endphp
<th @if(!empty($class)) class="{{ $class }}" @endif scope="col" aria-sort="{{ $ariaSort }}">
    <a href="{{ $href }}" class="ap-table-sort{{ $isActive ? ' is-active is-'.$staffDir : '' }}" title="{{ $title }}">
        <span class="ap-table-sort__label">{{ $label }}</span>
        <span class="ap-table-sort__icons" aria-hidden="true">
            <svg class="ap-table-sort__caret ap-table-sort__caret--up" width="10" height="6" viewBox="0 0 10 6" fill="currentColor"><path d="M5 0 10 6H0z"/></svg>
            <svg class="ap-table-sort__caret ap-table-sort__caret--down" width="10" height="6" viewBox="0 0 10 6" fill="currentColor"><path d="M5 6 0 0h10z"/></svg>
        </span>
    </a>
</th>
