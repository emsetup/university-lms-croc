@php
    $colKey = (string) ($colKey ?? '');
    $cell = is_array($cell ?? null) ? $cell : [];
    $hasSection = (bool) ($cell['has_section'] ?? false);
    $filled = (bool) ($cell['filled'] ?? false);
    $meta = (string) ($cell['meta'] ?? '');
    $previewUrl = $cell['preview_url'] ?? null;
    $trackPreviewUrl = $cell['track_preview_url'] ?? null;
    $previewTitle = (string) ($cell['preview_title'] ?? 'Просмотр');
    $statsUrl = $cell['stats_url'] ?? null;
    $statsLabel = (string) ($cell['stats_label'] ?? 'Ответы');
    $colType = (string) ($cell['col_type'] ?? ($colType ?? ''));
    $module = (int) ($module ?? 0);
    $isReadOnly = (bool) ($isReadOnly ?? false);
    $adminLabStates = is_array($adminLabStates ?? null) ? $adminLabStates : [];
    $imageStatsByImage = is_array($imageStatsByImage ?? null) ? $imageStatsByImage : [];
    $rp = is_array($rp ?? null) ? $rp : [];
    $dockerImage = (string) ($cell['docker_image'] ?? ($dockerImage ?? ''));
    $showDocker = $colType === 'practice' || $colKey === 'docker';
@endphp

@if ($showDocker && $colKey !== 'docker' && ! $hasSection)
    <td class="content-icon-cell"><span class="content-icon-muted">@include('partials.ap-icon', ['name' => 'minus', 'size' => 'sm'])</span></td>
@elseif ($colKey === 'docker' || ($showDocker && $hasSection))
    <td>
        @if ($hasSection)
            @if ($filled || ($colType === 'practice' && $meta !== '' && $meta !== 'нет текста'))
                @if ($colType !== 'docker' && $meta !== '' && $meta !== 'нет текста')
                    <span class="content-icon-ok">@include('partials.ap-icon', ['name' => 'check-circle', 'size' => 'sm'])</span>
                    <div class="cell-meta">{{ $meta }}</div>
                    @if ($previewUrl || $trackPreviewUrl)
                        <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;justify-content:center;">
                            @if ($previewUrl)
                                <button type="button" class="btn btn-secondary btn-sm btn-admin-content-preview js-admin-content-preview" data-preview-title="{{ $previewTitle }}" data-preview-url="{{ $previewUrl }}">
                                    @include('partials.ap-icon', ['name' => 'eye', 'size' => 'sm'])
                                    <span>Просмотр</span>
                                </button>
                            @endif
                            @if ($trackPreviewUrl)
                                @include('partials.course-preview-launch', [
                                    'previewUrl' => $trackPreviewUrl,
                                    'previewLabel' => 'В курсе',
                                    'previewClass' => 'btn btn-secondary btn-sm',
                                    'previewShowIcon' => true,
                                    'previewIcon' => 'eye',
                                ])
                            @endif
                        </div>
                    @endif
                @endif
                @if ($dockerImage !== '')
                    @php
                        $ls = $adminLabStates[$module] ?? null;
                        $st = is_array($imageStatsByImage[$dockerImage] ?? null) ? $imageStatsByImage[$dockerImage] : null;
                    @endphp
                    <span class="docker-tag" title="{{ $dockerImage }}">{{ $dockerImage }}</span>
                    @if ($st)
                        <div class="docker-meta">
                            {{ $st['size_human'] ?? '—' }}@if (! empty($st['layers_count'])) · {{ (int) $st['layers_count'] }} слоёв@endif
                        </div>
                    @endif
                    @if (! $isReadOnly)
                        <div class="content-docker-actions">
                            @if ($ls && ! empty($ls['lab_id']))
                                @if (! empty($ls['terminal_url']))
                                    <a class="btn btn-secondary btn-sm" href="{{ $ls['terminal_url'] }}" target="_blank" rel="noopener">Открыть</a>
                                @endif
                                <form method="post" action="{{ route('admin.theory.container.finish', array_merge($rp, ['module' => $module])) }}" class="admin-inline-form">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">Завершить</button>
                                </form>
                            @else
                                <form method="post" action="{{ route('admin.theory.container.start', array_merge($rp, ['module' => $module])) }}" class="admin-inline-form">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">Запустить</button>
                                </form>
                            @endif
                        </div>
                    @endif
                @elseif ($colKey === 'docker' || $colType === 'practice')
                    <span class="content-docker-unset">Не задан</span>
                @endif
            @else
                <span class="content-icon-muted">@include('partials.ap-icon', ['name' => 'minus', 'size' => 'sm'])</span>
                @if ($meta !== '' && $colType !== 'docker')
                    <div class="cell-meta">{{ $meta }}</div>
                @endif
            @endif
        @else
            <span class="content-icon-muted">@include('partials.ap-icon', ['name' => 'minus', 'size' => 'sm'])</span>
        @endif
    </td>
@else
    <td class="content-icon-cell">
        @if ($hasSection)
            @if ($filled)
                <span class="content-icon-ok">@include('partials.ap-icon', ['name' => 'check-circle', 'size' => 'sm'])</span>
                @if ($meta !== '')
                    <div class="cell-meta">{{ $meta }}</div>
                @endif
                <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;justify-content:center;">
                    @if ($previewUrl)
                        <button type="button" class="btn btn-secondary btn-sm btn-admin-content-preview js-admin-content-preview" data-preview-title="{{ $previewTitle }}" data-preview-url="{{ $previewUrl }}">
                            @include('partials.ap-icon', ['name' => 'eye', 'size' => 'sm'])
                            <span>Просмотр</span>
                        </button>
                    @endif
                    @if ($trackPreviewUrl)
                        @include('partials.course-preview-launch', [
                            'previewUrl' => $trackPreviewUrl,
                            'previewLabel' => 'В курсе',
                            'previewClass' => 'btn btn-secondary btn-sm',
                            'previewShowIcon' => true,
                            'previewIcon' => 'eye',
                        ])
                    @endif
                    @if ($statsUrl)
                        <a class="btn btn-secondary btn-sm" href="{{ $statsUrl }}" target="_blank" rel="noopener">
                            @include('partials.ap-icon', ['name' => 'clipboard-check', 'size' => 'sm'])
                            <span>{{ $statsLabel }}</span>
                        </a>
                    @endif
                </div>
            @else
                <span class="content-icon-muted">@include('partials.ap-icon', ['name' => 'minus', 'size' => 'sm'])</span>
                @if ($meta !== '')
                    <div class="cell-meta">{{ $meta }}</div>
                @endif
                @if ($statsUrl)
                    <div style="margin-top:8px;">
                        <a class="btn btn-secondary btn-sm" href="{{ $statsUrl }}" target="_blank" rel="noopener">
                            @include('partials.ap-icon', ['name' => 'clipboard-check', 'size' => 'sm'])
                            <span>{{ $statsLabel }}</span>
                        </a>
                    </div>
                @endif
            @endif
        @else
            <span class="content-icon-muted">@include('partials.ap-icon', ['name' => 'minus', 'size' => 'sm'])</span>
        @endif
    </td>
@endif
