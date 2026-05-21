                {{-- 7 Check — конфигуратор автопроверки --}}
                <section class="piwiz-panel js-piwiz-panel" data-panel="check" hidden data-headline="{{ $wizardSteps[6]['headline'] }}" data-lead="{{ $wizardSteps[6]['lead'] }}">
                    <div class="piwiz-check">
                        <div class="piwiz-tabs piwiz-check-tabs" role="tablist">
                            <button type="button" class="piwiz-tab is-active js-piwiz-check-tab" data-check-tab="tasks">Конструктор заданий</button>
                            <button type="button" class="piwiz-tab js-piwiz-check-tab" data-check-tab="packs">Пакеты и шаблоны</button>
                        </div>

                        <div class="piwiz-check-pane js-piwiz-check-pane" data-check-pane="tasks">
                            <p class="piwiz-check__lead">Соберите таблицу заданий: тип задаёт поля параметров. «Сгенерировать» обновит таблицу по числу заданий и запишет <code>check.sh</code> в редактор ниже.</p>

                            <div class="piwiz-check__examples">
                                <span class="piwiz-check__examples-label">Готовые примеры:</span>
                                @foreach ($checkExampleGrids as $ex)
                                    <button type="button" class="piwiz-check__example-btn js-pi-check-example" data-example-title="{{ e($ex['title']) }}">{{ $ex['title'] }}</button>
                                @endforeach
                            </div>

                            <div class="piwiz-task-toolbar">
                                <label class="piwiz-task-count">Заданий <input type="number" id="pi-check-task-num" min="1" max="20" value="4" class="piwiz-task-count__input"></label>
                                <label class="piwiz-task-count">MAX <input type="number" id="pi-check-max" min="1" max="1000" value="100" class="piwiz-task-count__input"></label>
                                <button type="button" class="piwiz-btn piwiz-btn--ghost piwiz-btn--sm" id="pi-check-split-points">Поровну</button>
                                <button type="button" class="piwiz-btn piwiz-btn--primary piwiz-btn--sm" id="pi-check-generate">Сгенерировать check.sh</button>
                                <button type="button" class="piwiz-btn piwiz-btn--ghost piwiz-btn--sm" id="pi-check-add-row">+ Задание</button>
                            </div>
                            <p class="piwiz-check__gen-status" id="pi-check-gen-status" hidden role="status"></p>

                            <div class="piwiz-task-table-wrap">
                                <table class="piwiz-task-table" id="pi-check-task-table">
                                    <thead>
                                        <tr>
                                            <th>№</th>
                                            <th>Баллы</th>
                                            <th>Тип</th>
                                            <th id="pi-check-th-param">Параметр</th>
                                            <th id="pi-check-th-extra">Доп.</th>
                                            <th>HINT</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="pi-check-task-body"></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="piwiz-check-pane js-piwiz-check-pane" data-check-pane="packs" hidden>
                            <p class="piwiz-check__lead">Пакеты добавляют строки в таблицу. Вспомогательные функции — в начало скрипта. Готовый скрипт заменяет редактор.</p>
                            <div class="piwiz-scenario-toolbar">
                                <button type="button" class="piwiz-btn piwiz-btn--primary piwiz-btn--sm" id="pi-check-apply-packs">Добавить пакеты</button>
                                <button type="button" class="piwiz-btn piwiz-btn--ghost piwiz-btn--sm" id="pi-check-apply-helpers">Вставить функции</button>
                            </div>
                            @foreach ($checkCategories as $catId => $catLabel)
                                @php $catItems = array_values(array_filter($checkPresets, static fn ($p) => ($p['category'] ?? '') === $catId)); @endphp
                                @if ($catItems !== [])
                                    <div class="piwiz-scenario-group">
                                        <h3 class="piwiz-scenario-group__title">{{ $catLabel }}</h3>
                                        <div class="piwiz-scenario-grid">
                                            @foreach ($catItems as $cp)
                                                @if (($cp['type'] ?? '') === 'pack')
                                                    <label class="piwiz-scenario-card">
                                                        <input type="checkbox" class="js-pi-check-pack" value="{{ $cp['id'] }}">
                                                        <span class="piwiz-scenario-card__inner">
                                                            <span class="piwiz-scenario-card__title">{{ $cp['title'] }}</span>
                                                            <span class="piwiz-scenario-card__desc">{{ $cp['description'] }}</span>
                                                        </span>
                                                    </label>
                                                @elseif (($cp['type'] ?? '') === 'helper')
                                                    <label class="piwiz-scenario-card">
                                                        <input type="checkbox" class="js-pi-check-helper" value="{{ $cp['id'] }}">
                                                        <span class="piwiz-scenario-card__inner">
                                                            <span class="piwiz-scenario-card__title">{{ $cp['title'] }}</span>
                                                            <span class="piwiz-scenario-card__desc">{{ $cp['description'] }}</span>
                                                        </span>
                                                    </label>
                                                @else
                                                    <button type="button" class="piwiz-scenario-card piwiz-scenario-card--btn js-pi-check-full" data-id="{{ $cp['id'] }}">
                                                        <span class="piwiz-scenario-card__inner">
                                                            <span class="piwiz-scenario-card__title">{{ $cp['title'] }}</span>
                                                            <span class="piwiz-scenario-card__desc">{{ $cp['description'] }}</span>
                                                        </span>
                                                    </button>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <div class="piwiz-check__editor">
                            <div class="piwiz-check__editor-head">
                                <div>
                                    <strong>check.sh</strong>
                                    <span class="piwiz-check__editor-hint">скрипт автопроверки в контейнере</span>
                                </div>
                                <button type="button" class="piwiz-btn piwiz-btn--ghost piwiz-btn--sm" id="pi-check-reset-editor">Очистить</button>
                            </div>
                            <textarea class="piwiz-code piwiz-code--editor" name="check_script_text" id="pi-check" rows="22" spellcheck="false">{{ old('check_script_text', $row->check_script_text) }}</textarea>
                            <p class="piwiz-field__tip"><code>===PRACTICE_RESULT_JSON===</code> и JSON <code>score</code>/<code>max</code> обязательны.</p>
                        </div>
                    </div>
                </section>
