<?php

namespace App\Services;

use App\Models\PracticeImage;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class PracticeImageBuildService
{
    public function __construct(
        private PracticeImageRecipeBootstrap $recipeBootstrap,
        private PracticeImageRecipeGenerator $recipeGenerator,
    ) {}

    /**
     * @return array{ok:bool,log:string,error?:string,phases:list<array{id:string,label:string,status:string}>}
     */
    public function build(PracticeImage $row, PracticeLabDaemonClient $client): array
    {
        $phases = $this->phaseTemplate();

        try {
            $this->ensureStorageWritable();
            $this->setPhase($phases, 'files', 'active');
            $this->recipeGenerator->syncRecipeFiles($row);
            $this->setPhase($phases, 'files', 'done');

            $row->last_build_status = 'running';
            $row->last_build_log = null;
            $row->save();

            $this->setPhase($phases, 'pull', 'active');
            $this->setPhase($phases, 'build', 'active');

            $resp = $client->imageBuild([
                'context_dir' => $this->recipeBootstrap->contextDirRel($row),
                'dockerfile_rel' => 'Dockerfile',
                'tags' => [(string) $row->docker_tag],
                'build_args' => null,
            ]);

            $log = is_string($resp['log'] ?? null) ? (string) $resp['log'] : '';
            $ok = (bool) ($resp['ok'] ?? false);

            $this->applyLogPhases($phases, $log);
            if ($ok) {
                $this->setPhase($phases, 'pull', 'done');
                $this->setPhase($phases, 'build', 'done');
                $this->setPhase($phases, 'done', 'done');
            } else {
                $this->setPhase($phases, 'pull', 'done');
                $this->setPhase($phases, 'build', 'error');
                $this->setPhase($phases, 'done', 'error');
            }

            $row->is_built = $ok;
            $row->last_build_status = $ok ? 'ok' : 'fail';
            $row->last_build_log = $log !== '' ? $log : null;
            $row->last_built_at = now();
            $row->save();

            return [
                'ok' => $ok,
                'log' => $log,
                'phases' => $phases,
                'error' => $ok ? null : ($resp['error'] ?? 'Сборка завершилась с ошибкой'),
            ];
        } catch (\Throwable $e) {
            $row->last_build_status = 'fail';
            $row->last_build_log = $e->getMessage();
            $row->save();

            foreach (['files', 'pull', 'build', 'done'] as $id) {
                if ($this->phaseStatus($phases, $id) === 'active') {
                    $this->setPhase($phases, $id, 'error');
                    break;
                }
            }

            return [
                'ok' => false,
                'log' => $e->getMessage(),
                'phases' => $phases,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function ensureStorageWritable(): void
    {
        $base = storage_path('app/practice-images');
        if (! is_dir($base)) {
            File::ensureDirectoryExists($base, 0775, true);
        }
        if (! is_writable($base)) {
            throw new RuntimeException(
                'Нет прав на запись в storage/app/practice-images. На стенде: chown -R _php_fpm:_webserver storage/app/practice-images && chmod -R g+rws storage/app/practice-images'
            );
        }
    }

    /**
     * @return list<array{id:string,label:string,status:string}>
     */
    private function phaseTemplate(): array
    {
        return [
            ['id' => 'save', 'label' => 'Сохранение настроек образа', 'status' => 'pending'],
            ['id' => 'files', 'label' => 'Создание Dockerfile и скриптов', 'status' => 'pending'],
            ['id' => 'pull', 'label' => 'Загрузка базового образа из реестра', 'status' => 'pending'],
            ['id' => 'build', 'label' => 'Сборка образа на стенде', 'status' => 'pending'],
            ['id' => 'done', 'label' => 'Готово', 'status' => 'pending'],
        ];
    }

    /**
     * @param  list<array{id:string,label:string,status:string}>  $phases
     */
    private function setPhase(array &$phases, string $id, string $status): void
    {
        foreach ($phases as $i => $phase) {
            if ($phase['id'] === $id) {
                $phases[$i]['status'] = $status;
            }
        }
    }

    /**
     * @param  list<array{id:string,label:string,status:string}>  $phases
     */
    private function phaseStatus(array $phases, string $id): string
    {
        foreach ($phases as $phase) {
            if ($phase['id'] === $id) {
                return $phase['status'];
            }
        }

        return 'pending';
    }

    /**
     * @param  list<array{id:string,label:string,status:string}>  $phases
     */
    private function applyLogPhases(array &$phases, string $log): void
    {
        if ($log === '') {
            return;
        }
        if (preg_match('/pull|download|extracting/i', $log)) {
            $this->setPhase($phases, 'pull', 'done');
        }
        if (preg_match('/^Step \d+/m', $log) || str_contains($log, 'RUN ')) {
            $this->setPhase($phases, 'pull', 'done');
        }
    }
}
