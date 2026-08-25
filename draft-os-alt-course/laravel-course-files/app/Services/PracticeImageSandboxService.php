<?php

namespace App\Services;

use App\Models\PracticeImage;
use App\Support\PracticeCheckOutputParser;
use App\Support\PracticeTerminalUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Тестовый Docker-стенд для образа из библиотеки (отладка check.sh и startup в контейнере).
 * Состояние в кэше по admin session (как проверочные стенды в AdminTheoryController).
 */
final class PracticeImageSandboxService
{
    private const TTL_MINUTES = 120;

    public function __construct(
        private ?PracticeLabDaemonClient $client
    ) {}

    public static function make(): self
    {
        return new self(PracticeLabDaemonClient::fromConfig());
    }

    public function isDaemonReady(): bool
    {
        return $this->client !== null;
    }

    public function getState(int $imageId): ?array
    {
        $state = Cache::get($this->stateCacheKey($imageId));
        if (! is_array($state)) {
            return null;
        }
        if (isset($state['terminal_url'])) {
            $state['terminal_url'] = (string) (PracticeTerminalUrl::toHttpsProxy((string) $state['terminal_url']) ?? '');
        }

        return $state;
    }

    /**
     * @return array{ok: bool, error?: string, state?: array}
     */
    public function start(PracticeImage $row): array
    {
        if (! $this->client) {
            return ['ok' => false, 'error' => 'Lab-daemon не настроен (PRACTICE_LAB_DAEMON_URL / SECRET).'];
        }

        $tag = trim((string) $row->docker_tag);
        if ($tag === '') {
            return ['ok' => false, 'error' => 'Не задан тег Docker-образа.'];
        }

        $imageId = (int) $row->id;
        $existing = $this->getState($imageId);
        if (is_array($existing) && ! empty($existing['lab_id'])) {
            try {
                $this->client->destroyLab((string) $existing['lab_id']);
            } catch (\Throwable $e) {
                Log::warning('practice_image_sandbox destroy before start: '.$e->getMessage());
            }
            Cache::forget($this->stateCacheKey($imageId));
        }

        $moduleKey = self::daemonModuleKeyForImage($row);

        try {
            $resp = $this->client->createLab($this->adminPseudoLearnerId(), $moduleKey, $tag);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Не удалось запустить контейнер: '.$e->getMessage()];
        }

        $state = [
            'practice_image_id' => $imageId,
            'lab_id' => (string) ($resp['lab_id'] ?? ''),
            'terminal_url' => (string) (PracticeTerminalUrl::toHttpsProxy((string) ($resp['terminal_url'] ?? '')) ?? ''),
            'image' => $tag,
            'module_key' => $moduleKey,
            'started_at' => now()->toIso8601String(),
            'expires_at' => (string) ($resp['expires_at'] ?? ''),
            'last_check_at' => null,
            'last_check_log' => null,
            'last_check_passed' => null,
            'last_check_score' => null,
            'last_check_max_score' => null,
            'last_check_hints' => null,
        ];

        Cache::put($this->stateCacheKey($imageId), $state, now()->addMinutes(self::TTL_MINUTES));

        return ['ok' => true, 'state' => $state];
    }

    /**
     * @return array{ok: bool, error?: string, state?: array}
     */
    public function runCheck(int $imageId): array
    {
        if (! $this->client) {
            return ['ok' => false, 'error' => 'Lab-daemon не настроен.'];
        }

        $state = $this->getState($imageId);
        if (! is_array($state) || empty($state['lab_id'])) {
            return ['ok' => false, 'error' => 'Сначала запустите тестовый стенд для этого образа.'];
        }

        try {
            $resp = $this->client->checkLab((string) $state['lab_id']);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Ошибка проверки: '.$e->getMessage()];
        }

        $stdout = (string) ($resp['stdout'] ?? '');
        $exit = (int) ($resp['exit_code'] ?? -1);
        $passedDaemon = (bool) ($resp['passed'] ?? ($exit === 0));
        $parsed = PracticeCheckOutputParser::parse($stdout);

        $state['last_check_log'] = $stdout;
        $state['last_check_at'] = now()->toIso8601String();
        $state['last_check_exit_code'] = $exit;
        $state['last_check_hints'] = $parsed['hints'] !== [] ? array_values(array_unique($parsed['hints'])) : null;

        if ($parsed['has_json'] && $parsed['score'] !== null) {
            $state['last_check_score'] = $parsed['score'];
            $state['last_check_max_score'] = $parsed['max'];
            $state['last_check_passed'] = $parsed['score'] >= $parsed['max'];
        } else {
            $state['last_check_score'] = null;
            $state['last_check_max_score'] = null;
            $state['last_check_passed'] = $passedDaemon;
        }

        Cache::put($this->stateCacheKey($imageId), $state, now()->addMinutes(self::TTL_MINUTES));

        return [
            'ok' => true,
            'state' => $state,
            'check' => [
                'exit_code' => $exit,
                'passed' => (bool) $state['last_check_passed'],
                'score' => $state['last_check_score'],
                'max' => $state['last_check_max_score'],
            ],
        ];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function stop(int $imageId): array
    {
        $state = $this->getState($imageId);
        if ($this->client && is_array($state) && ! empty($state['lab_id'])) {
            try {
                $this->client->destroyLab((string) $state['lab_id']);
            } catch (\Throwable $e) {
                Log::warning('practice_image_sandbox destroy: '.$e->getMessage());
            }
        }
        Cache::forget($this->stateCacheKey($imageId));

        return ['ok' => true];
    }

    public static function daemonModuleKeyForImage(PracticeImage $row): int
    {
        $features = is_array($row->features) ? $row->features : [];
        if ((bool) ($features['systemd_mode'] ?? false)) {
            return 8;
        }

        $tpl = strtolower(trim((string) $row->base_template));
        $tag = strtolower(trim((string) $row->docker_tag));

        foreach ([$tpl, $tag] as $src) {
            if ($src === '') {
                continue;
            }
            if (preg_match('/lab-m(\d+)/', $src, $m)) {
                return max(1, min(10, (int) $m[1]));
            }
            if (str_contains($src, 'final-lab') || str_contains($src, 'final_lab')) {
                return 10;
            }
        }

        if (str_contains($tpl, 'systemd') || str_contains($tag, '-systemd')) {
            if (preg_match('/m(\d+)/', $tpl.$tag, $m)) {
                return max(1, min(10, (int) $m[1]));
            }

            return 8;
        }

        return 1;
    }

    private function stateCacheKey(int $imageId): string
    {
        return 'admin_image_sandbox:'.sha1($this->cachePrefix()).':'.$imageId;
    }

    private function cachePrefix(): string
    {
        $lid = (int) session('learner_id', 0);
        if ($lid > 0) {
            return 'lid:'.$lid;
        }

        $sid = (string) session()->getId();

        return $sid !== '' ? 'sid:'.$sid : 'sid:anon';
    }

    private function adminPseudoLearnerId(): int
    {
        $v = abs((int) crc32('admin:'.$this->cachePrefix()));

        return 900000 + ($v % 99999);
    }
}
