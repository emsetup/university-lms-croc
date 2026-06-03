<?php

namespace App\Services;

use App\Models\PortalIncidentLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PortalServerStatsService
{
    public function snapshot(): array
    {
        $root = base_path();
        $diskFree = @disk_free_space($root);
        $diskTotal = @disk_total_space($root);

        return [
            'captured_at' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
            'memory' => [
                'usage_bytes' => memory_get_usage(true),
                'peak_bytes' => memory_get_peak_usage(true),
                'limit' => (string) ini_get('memory_limit'),
            ],
            'load_avg' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
            'disk' => [
                'path' => $root,
                'free_bytes' => is_numeric($diskFree) ? (int) $diskFree : null,
                'total_bytes' => is_numeric($diskTotal) ? (int) $diskTotal : null,
            ],
            'database' => $this->databaseStats(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseStats(): array
    {
        if (! Schema::hasTable('portal_incident_logs')) {
            return ['incidents_total' => null];
        }

        $out = [
            'incidents_total' => (int) PortalIncidentLog::query()->count(),
            'incidents_24h' => (int) PortalIncidentLog::query()
                ->where('occurred_at', '>=', now()->subDay())
                ->count(),
        ];

        try {
            $dbName = DB::connection()->getDatabaseName();
            if ($dbName !== '') {
                $row = DB::selectOne(
                    'SELECT SUM(data_length + index_length) AS size_bytes
                     FROM information_schema.tables
                     WHERE table_schema = ?',
                    [$dbName]
                );
                $out['size_bytes'] = isset($row->size_bytes) ? (int) $row->size_bytes : null;
            }
        } catch (\Throwable) {
            $out['size_bytes'] = null;
        }

        return $out;
    }
}
