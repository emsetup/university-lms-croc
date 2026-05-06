<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\PracticeSession;
use App\Support\PracticeCheckOutputParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

final class PracticeLabService
{
    public function __construct(
        private ?PracticeLabDaemonClient $client
    ) {}

    public static function make(): self
    {
        return new self(PracticeLabDaemonClient::fromConfig());
    }

    public function isConfigured(): bool
    {
        return config('practice_lab.enabled')
            && $this->client !== null
            && config('practice_lab.daemon_secret') !== '';
    }

    /**
     * @param  int  $sessionModuleId  course_modules.id или {@see PracticeSession::FINAL_LAB_SESSION_MODULE_ID}
     */
    public function getOrCreateSessionModel(Learner $learner, int $courseId, int $sessionModuleId): PracticeSession
    {
        return PracticeSession::firstOrCreate(
            ['learner_id' => $learner->id, 'course_id' => $courseId, 'module_id' => $sessionModuleId],
            ['status' => 'none']
        );
    }

    public function imageForModule(int $moduleId): ?string
    {
        $key = (string) $moduleId;
        $images = config('practice_lab.images', []);

        return isset($images[$key]) ? (string) $images[$key] : null;
    }

    /**
     * @param  int  $sessionModuleId  строка practice_sessions (course_modules.id или 0 для финальной лабы)
     * @param  int  $daemonImageKey  ключ в practice_lab.images и для lab-daemon (content_source_index или 10)
     * @return array{session: PracticeSession, message?: string}
     */
    public function startLab(Learner $learner, int $courseId, int $sessionModuleId, int $daemonImageKey): array
    {
        if (! $this->isConfigured()) {
            return ['session' => $this->getOrCreateSessionModel($learner, $courseId, $sessionModuleId), 'message' => 'Лаборатория отключена в конфигурации.'];
        }
        $image = $this->imageForModule($daemonImageKey);
        if ($image === null || $image === '') {
            return ['session' => $this->getOrCreateSessionModel($learner, $courseId, $sessionModuleId), 'message' => 'Для этого модуля не задан Docker-образ.'];
        }

        $session = $this->getOrCreateSessionModel($learner, $courseId, $sessionModuleId);

        if ($session->daemon_lab_id && $session->isActive()) {
            return ['session' => $session, 'message' => 'Стенд уже выделен.'];
        }

        if ($session->daemon_lab_id) {
            try {
                $this->client->destroyLab((string) $session->daemon_lab_id);
            } catch (\Throwable $e) {
                Log::warning('practice_lab destroy before start: '.$e->getMessage());
            }
        }

        $ttl = (int) config('practice_lab.session_ttl_minutes', 480);

        try {
            $resp = $this->client->createLab($learner->id, $daemonImageKey, $image);
        } catch (\Throwable $e) {
            Log::error('practice_lab create: '.$e->getMessage());

            throw new \RuntimeException('Не удалось создать контейнер: '.$e->getMessage(), 0, $e);
        }

        $session->daemon_lab_id = $resp['lab_id'] ?? null;
        $session->status = 'ready';
        $session->terminal_url = $resp['terminal_url'] ?? null;
        $session->expires_at = isset($resp['expires_at'])
            ? Carbon::parse($resp['expires_at'])
            : now()->addMinutes($ttl);
        $session->last_check_log = null;
        $session->last_check_passed = null;
        $session->last_check_score = null;
        $session->last_check_max_score = null;
        $session->last_check_hints = null;
        $session->last_check_at = null;
        $session->accepted_at = null;
        $session->accepted_check_log = null;
        $session->save();

        return ['session' => $session];
    }

    /**
     * @return array{session: PracticeSession, message?: string}
     */
    public function runCheck(Learner $learner, int $courseId, int $sessionModuleId): array
    {
        $session = $this->getOrCreateSessionModel($learner, $courseId, $sessionModuleId);
        if (! $this->client || ! $this->isConfigured()) {
            return ['session' => $session, 'message' => 'Лаборатория отключена в конфигурации.'];
        }
        if (! $session->daemon_lab_id || ! $session->isActive()) {
            return ['session' => $session, 'message' => 'Сначала выделите стенд.'];
        }

        try {
            $resp = $this->client->checkLab((string) $session->daemon_lab_id);
        } catch (\Throwable $e) {
            Log::error('practice_lab check: '.$e->getMessage());
            $session->last_check_log = 'Ошибка связи с сервисом проверки: '.$e->getMessage();
            $session->last_check_passed = false;
            $session->last_check_score = null;
            $session->last_check_max_score = null;
            $session->last_check_hints = null;
            $session->last_check_at = now();
            $session->status = 'check_fail';
            $session->save();

            return ['session' => $session, 'message' => $session->last_check_log];
        }

        $exit = (int) ($resp['exit_code'] ?? -1);
        $stdout = (string) ($resp['stdout'] ?? '');
        $passedDaemon = (bool) ($resp['passed'] ?? ($exit === 0));

        $parsed = PracticeCheckOutputParser::parse($stdout);
        $minAccept = (int) config('practice_lab.min_accept_score', 50);

        $session->last_check_log = $stdout;
        $session->last_check_hints = $parsed['hints'] !== [] ? $parsed['hints'] : null;

        if ($parsed['has_json'] && $parsed['score'] !== null) {
            $session->last_check_score = $parsed['score'];
            $session->last_check_max_score = $parsed['max'];
            $full = $parsed['score'] >= $parsed['max'];
            $session->last_check_passed = $full;
            if ($full) {
                $session->status = 'check_pass';
            } elseif ($parsed['score'] >= $minAccept) {
                $session->status = 'check_partial';
            } else {
                $session->status = 'check_fail';
            }
        } else {
            $session->last_check_score = null;
            $session->last_check_max_score = null;
            $session->last_check_passed = $passedDaemon;
            $session->status = $passedDaemon ? 'check_pass' : 'check_fail';
        }

        $session->last_check_at = now();
        $this->appendTerminalSnapshotFromCheckResponse($session, $resp, 'check');
        $session->save();

        return ['session' => $session];
    }

    public function canAcceptPractice(PracticeSession $session): bool
    {
        if ($session->last_check_score !== null) {
            return true;
        }

        return (bool) $session->last_check_passed;
    }

    public function destroyLab(Learner $learner, int $courseId, int $sessionModuleId): PracticeSession
    {
        $session = $this->getOrCreateSessionModel($learner, $courseId, $sessionModuleId);
        if ($session->daemon_lab_id && $this->client) {
            try {
                $this->fetchAndAppendBashHistory($session, 'lab_destroy');
                $session->save();
            } catch (\Throwable $e) {
                Log::debug('practice_lab bash_history before destroy: '.$e->getMessage());
            }
            try {
                $this->client->destroyLab($session->daemon_lab_id);
            } catch (\Throwable $e) {
                Log::warning('practice_lab destroy: '.$e->getMessage());
            }
        }
        $session->daemon_lab_id = null;
        $session->status = 'destroyed';
        $session->terminal_url = null;
        $session->expires_at = null;
        $session->save();

        return $session;
    }

    /**
     * Пользователь подтверждает результат проверки: фиксируем в БД и отмечаем практику выполненной.
     * Контейнер при этом не удаляется — для этого отдельно «Завершить работу со стендом».
     */
    /**
     * @return int|null зафиксированный балл (или 100 при старой проверке без шкалы)
     */
    public function acceptPracticeResult(Learner $learner, int $courseId, int $courseModuleId): ?int
    {
        $session = $this->getOrCreateSessionModel($learner, $courseId, $courseModuleId);
        if (! $this->canAcceptPractice($session)) {
            throw new \RuntimeException(
                'Сначала выполните автопроверку (кнопка «Проверить результат») и дождитесь журнала проверки.'
            );
        }
        if ($learner->progressFor($courseModuleId, $courseId)->practice_done_at) {
            throw new \RuntimeException('Результат уже принят, практика зачтена.');
        }

        $acceptedScore = $session->last_check_score !== null
            ? $session->last_check_score
            : ($session->last_check_passed ? 100 : null);

        DB::transaction(function () use ($learner, $courseId, $courseModuleId, $session, $acceptedScore): void {
            $p = $learner->progressFor($courseModuleId, $courseId);
            $k = 'practice_segment_start_'.$courseModuleId;
            $ts = Session::pull($k);
            if ($ts !== null && is_numeric($ts)) {
                $elapsed = max(0, min(86400 * 14, now()->getTimestamp() - (int) $ts));
                if ($elapsed > 0) {
                    $p->seconds_practice = (int) ($p->seconds_practice ?? 0) + $elapsed;
                }
            }
            $p->practice_done_at = now();
            $p->practice_lab_percent = $this->labPercentForDisplay($acceptedScore, $session->last_check_max_score);
            $p->save();
            $session->accepted_at = now();
            $session->accepted_check_log = $session->last_check_log;
            $session->accepted_practice_score = $acceptedScore;
            $session->save();
        });

        return $acceptedScore;
    }

    public function expireStaleSessions(): int
    {
        if (! $this->client) {
            return 0;
        }
        $n = 0;
        $q = PracticeSession::query()
            ->whereNotNull('daemon_lab_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereIn('status', ['ready', 'check_pass', 'check_partial', 'check_fail', 'provisioning']);

        /** @var PracticeSession $s */
        foreach ($q->cursor() as $s) {
            try {
                $this->fetchAndAppendBashHistory($s, 'expire');
            } catch (\Throwable) {
                // ignore
            }
            try {
                $this->client->destroyLab((string) $s->daemon_lab_id);
            } catch (\Throwable) {
                // ignore
            }
            $s->daemon_lab_id = null;
            $s->status = 'destroyed';
            $s->terminal_url = null;
            $s->save();
            $n++;
        }

        return $n;
    }

    /**
     * Процент для отображения в хабе (0–100): балл проверки относительно максимума чек-листа.
     */
    private function labPercentForDisplay(?int $acceptedScore, ?int $maxScore): ?int
    {
        if ($acceptedScore === null) {
            return null;
        }
        $max = (int) ($maxScore ?? 100);
        if ($max <= 0) {
            return min(100, max(0, $acceptedScore));
        }

        return min(100, max(0, (int) round(100 * $acceptedScore / $max)));
    }

    private function fetchAndAppendBashHistory(PracticeSession $session, string $source): void
    {
        if (! $this->client || ! $session->daemon_lab_id) {
            return;
        }
        $json = $this->client->getBashHistory((string) $session->daemon_lab_id);
        $this->appendTerminalSnapshotFromCheckResponse($session, $json, $source);
    }

    /**
     * @param  array<string, mixed>  $resp
     */
    private function appendTerminalSnapshotFromCheckResponse(PracticeSession $session, array $resp, string $source): void
    {
        $text = trim((string) ($resp['bash_history'] ?? ''));
        $this->appendTerminalSnapshot($session, $text, $source);
    }

    private function appendTerminalSnapshot(PracticeSession $session, string $bashHistory, string $source): void
    {
        if ($bashHistory === '') {
            return;
        }
        $hist = $session->terminal_snapshots ?? [];
        $hist[] = [
            'at' => now()->toIso8601String(),
            'source' => $source,
            'line_count' => substr_count($bashHistory, "\n") + 1,
            'content' => $bashHistory,
        ];
        if (count($hist) > 100) {
            $hist = array_slice($hist, -100);
        }
        $session->terminal_snapshots = $hist;
    }
}
