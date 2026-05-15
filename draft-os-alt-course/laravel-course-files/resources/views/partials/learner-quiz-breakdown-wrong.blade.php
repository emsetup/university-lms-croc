@if (!empty($showBreakdown) && !empty($wrongItems))
    <div class="card" style="margin-top:1rem" data-breakdown-until="{{ $breakdownUntilTs ?? '' }}">
        <h2 style="margin-top:0">{{ $breakdownTitle ?? 'Разбор ответов' }}</h2>
        @if (!empty($breakdownUntilTs))
            <p class="muted small" id="quiz-breakdown-timer" data-until-ts="{{ (int) $breakdownUntilTs }}">
                Разбор доступен ограниченное время после попытки. Осталось: <strong class="quiz-breakdown-timer__left">—</strong>
            </p>
        @endif
        <p class="muted small">Показаны только вопросы с ошибкой или без ответа.</p>
        <ul class="muted" style="padding-left:1.1rem">
            @foreach ($wrongItems as $it)
                <li style="margin-bottom:0.75rem">
                    <strong>Вопрос {{ $it['n'] ?? '' }}.</strong>
                    <div class="module-exam-q--md" style="font-weight:600;margin-top:0.2rem">{!! \Illuminate\Support\Str::markdown($it['question'] ?? '') !!}</div>
                    @if (!empty($it['skipped']))
                        <br><span>Без ответа</span>
                    @else
                        <br><span>Ошибка</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
    @if (!empty($breakdownUntilTs))
        <script>
            (function () {
                var el = document.getElementById('quiz-breakdown-timer');
                if (!el) return;
                var until = parseInt(el.getAttribute('data-until-ts'), 10);
                var leftEl = el.querySelector('.quiz-breakdown-timer__left');
                if (!until || !leftEl) return;
                function tick() {
                    var sec = until - Math.floor(Date.now() / 1000);
                    if (sec <= 0) {
                        leftEl.textContent = '0:00';
                        window.location.reload();
                        return;
                    }
                    var m = Math.floor(sec / 60);
                    var s = sec % 60;
                    leftEl.textContent = m + ':' + (s < 10 ? '0' : '') + s;
                }
                tick();
                setInterval(tick, 1000);
            })();
        </script>
    @endif
@endif
