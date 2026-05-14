<?php

namespace App\Services;

use App\Models\PracticeImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Инициализация каталога рецепта practice-image из шаблона lab-m* (как в админке образов).
 */
final class PracticeImageRecipeBootstrap
{
    public function contextDirRel(PracticeImage $row): string
    {
        return 'practice-images/'.$row->id;
    }

    public function recipeRootAbs(PracticeImage $row): string
    {
        return storage_path('app/'.$this->contextDirRel($row));
    }

    public function initFromTemplate(PracticeImage $row): void
    {
        $tpl = $this->templateSourcePaths((string) $row->base_template);
        $dockerfile = File::get($tpl['dockerfile']);
        $check = File::get($tpl['check']);

        $row->dockerfile_text = $dockerfile;
        $row->check_script_text = $check;
        $row->save();

        $this->writeRecipeFiles($row, true);
    }

    /**
     * @return array{dockerfile:string,docker_dir:string,check:string,check_rel:string}
     */
    private function templateSourcePaths(string $template): array
    {
        $base = base_path();

        return match ($template) {
            'lab-m1' => [
                'dockerfile' => $base.'/docker/lab-m1/Dockerfile',
                'docker_dir' => $base.'/docker/lab-m1',
                'check' => $base.'/examples/practice-checks/module_01/check.sh',
                'check_rel' => 'examples/practice-checks/module_01/check.sh',
            ],
            'lab-m2' => [
                'dockerfile' => $base.'/docker/lab-m2/Dockerfile',
                'docker_dir' => $base.'/docker/lab-m2',
                'check' => $base.'/examples/practice-checks/module_02/check.sh',
                'check_rel' => 'examples/practice-checks/module_02/check.sh',
            ],
            'lab-m3' => [
                'dockerfile' => $base.'/docker/lab-m3/Dockerfile',
                'docker_dir' => $base.'/docker/lab-m3',
                'check' => $base.'/examples/practice-checks/module_03/check.sh',
                'check_rel' => 'examples/practice-checks/module_03/check.sh',
            ],
            'lab-m5' => [
                'dockerfile' => $base.'/docker/lab-m5/Dockerfile',
                'docker_dir' => $base.'/docker/lab-m5',
                'check' => $base.'/examples/practice-checks/module_05/check.sh',
                'check_rel' => 'examples/practice-checks/module_05/check.sh',
            ],
            'lab-m6' => [
                'dockerfile' => $base.'/docker/lab-m6/Dockerfile',
                'docker_dir' => $base.'/docker/lab-m6',
                'check' => $base.'/examples/practice-checks/module_06/check.sh',
                'check_rel' => 'examples/practice-checks/module_06/check.sh',
            ],
            'lab-m7' => [
                'dockerfile' => $base.'/docker/lab-m7/Dockerfile',
                'docker_dir' => $base.'/docker/lab-m7',
                'check' => $base.'/examples/practice-checks/module_07/check.sh',
                'check_rel' => 'examples/practice-checks/module_07/check.sh',
            ],
            'lab-m8', 'lab-m8-systemd' => [
                'dockerfile' => $base.'/docker/lab-m8/Dockerfile',
                'docker_dir' => $base.'/docker/lab-m8',
                'check' => $base.'/examples/practice-checks/module_08/check.sh',
                'check_rel' => 'examples/practice-checks/module_08/check.sh',
            ],
            'lab-m9' => [
                'dockerfile' => $base.'/docker/lab-m9/Dockerfile',
                'docker_dir' => $base.'/docker/lab-m9',
                'check' => $base.'/examples/practice-checks/module_09/check.sh',
                'check_rel' => 'examples/practice-checks/module_09/check.sh',
            ],
            'final-lab' => [
                'dockerfile' => $base.'/docker/final-lab/Dockerfile',
                'docker_dir' => $base.'/docker/final-lab',
                'check' => $base.'/examples/practice-checks/final_lab/check.sh',
                'check_rel' => 'examples/practice-checks/final_lab/check.sh',
            ],
            default => throw new \InvalidArgumentException('Unknown template: '.$template),
        };
    }

    private function dockerfileRelForTemplate(string $template): string
    {
        return match ($template) {
            'final-lab' => 'docker/final-lab/Dockerfile',
            'lab-m1' => 'docker/lab-m1/Dockerfile',
            'lab-m2' => 'docker/lab-m2/Dockerfile',
            'lab-m3' => 'docker/lab-m3/Dockerfile',
            'lab-m5' => 'docker/lab-m5/Dockerfile',
            'lab-m6' => 'docker/lab-m6/Dockerfile',
            'lab-m7' => 'docker/lab-m7/Dockerfile',
            'lab-m8', 'lab-m8-systemd' => 'docker/lab-m8/Dockerfile',
            'lab-m9' => 'docker/lab-m9/Dockerfile',
            default => 'Dockerfile',
        };
    }

    private function writeRecipeFiles(PracticeImage $row, bool $copyTemplateAssets = false): void
    {
        $root = $this->recipeRootAbs($row);
        File::ensureDirectoryExists($root);

        $tpl = $this->templateSourcePaths((string) $row->base_template);
        if ($copyTemplateAssets) {
            $dockerDir = (string) $tpl['docker_dir'];
            if (is_dir($dockerDir)) {
                File::copyDirectory($dockerDir, $root.'/'.Str::after($dockerDir, base_path().'/'));
            }
        }

        $dockerfileRel = $this->dockerfileRelForTemplate((string) $row->base_template);
        $dockerfileAbs = $root.'/'.$dockerfileRel;
        File::ensureDirectoryExists(dirname($dockerfileAbs));
        File::put($dockerfileAbs, (string) $row->dockerfile_text);

        $checkRel = (string) $tpl['check_rel'];
        $checkAbs = $root.'/'.$checkRel;
        File::ensureDirectoryExists(dirname($checkAbs));
        File::put($checkAbs, (string) $row->check_script_text);
        @chmod($checkAbs, 0755);
    }
}
