<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdMailGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImportAdMailGroupsCommand extends Command
{
    protected $signature = 'portal:import-ad-mail-groups
                            {path : Path to ad-mail-groups.json}
                            {--deactivate-missing : Mark groups absent from dump as inactive}';

    protected $description = 'Импорт distribution-групп AD (mail) из JSON-дампа в ad_mail_groups';

    public function handle(): int
    {
        if (! Schema::hasTable('ad_mail_groups')) {
            $this->error('Таблица ad_mail_groups отсутствует. Сначала migrate.');

            return self::FAILURE;
        }

        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error('Файл не найден: '.$path);

            return self::FAILURE;
        }

        try {
            $raw = file_get_contents($path);
            $payload = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->error('Не удалось прочитать JSON: '.$e->getMessage());

            return self::FAILURE;
        }

        $groups = $payload['groups'] ?? null;
        if (! is_array($groups)) {
            $this->error('В JSON нет массива groups.');

            return self::FAILURE;
        }

        $now = now();
        $upserted = 0;
        $seenMails = [];

        DB::transaction(function () use ($groups, $now, &$upserted, &$seenMails) {
            foreach (array_chunk($groups, 200) as $chunk) {
                $rows = [];
                foreach ($chunk as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $mail = mb_strtolower(trim((string) ($item['mail'] ?? '')));
                    if ($mail === '' || ! filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }
                    $guid = trim((string) ($item['object_guid'] ?? ''));
                    $guid = trim($guid, '{}');
                    if ($guid === '') {
                        $guid = null;
                    }
                    $seenMails[$mail] = true;
                    $rows[] = [
                        'object_guid' => $guid,
                        'cn' => $this->nullableStr($item['cn'] ?? null, 255),
                        'display_name' => $this->nullableStr($item['display_name'] ?? ($item['cn'] ?? null), 255),
                        'mail' => $mail,
                        'sam_account_name' => $this->nullableStr($item['sam_account_name'] ?? null, 255),
                        'dn' => $this->nullableStr($item['dn'] ?? null, 1024),
                        'group_type' => (int) ($item['group_type'] ?? 0),
                        'is_active' => true,
                        'imported_at' => $now,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ];
                }
                if ($rows === []) {
                    continue;
                }
                AdMailGroup::query()->upsert(
                    $rows,
                    ['mail'],
                    ['object_guid', 'cn', 'display_name', 'sam_account_name', 'dn', 'group_type', 'is_active', 'imported_at', 'updated_at'],
                );
                $upserted += count($rows);
            }
        });

        $deactivated = 0;
        if ($this->option('deactivate-missing') && $seenMails !== []) {
            $deactivated = AdMailGroup::query()
                ->whereNotIn('mail', array_keys($seenMails))
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => $now]);
        }

        $this->info('Импортировано/обновлено: '.$upserted);
        if ($this->option('deactivate-missing')) {
            $this->info('Деактивировано отсутствующих: '.$deactivated);
        }
        $this->info('Активных в БД: '.AdMailGroup::query()->active()->count());

        return self::SUCCESS;
    }

    private function nullableStr(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        return mb_substr($s, 0, $max);
    }
}
