<?php

namespace App\Services;

use App\Models\PracticeImage;
use Illuminate\Support\Facades\File;

final class PracticeImageRecipeGenerator
{
    public static function recipeRootAbs(PracticeImage $img): string
    {
        return storage_path('app/practice-images/'.$img->id);
    }

    public static function recipeContextDirRel(PracticeImage $img): string
    {
        return 'practice-images/'.$img->id;
    }

    public function previewDockerfile(PracticeImage $img): string
    {
        return $this->dockerfileFor($img);
    }

    public function syncRecipeFiles(PracticeImage $img): void
    {
        $root = self::recipeRootAbs($img);
        File::ensureDirectoryExists($root.'/assets');

        $features = is_array($img->features) ? $img->features : [];
        $systemd = (bool) ($features['systemd_mode'] ?? false);

        $startup = (string) ($img->startup_script_text ?? '');
        if (trim($startup) === '') {
            $startup = "#!/usr/bin/env bash\nset -euo pipefail\n\n# TODO: prepare lab state here\n";
        }

        $check = $this->normalizeCheckScript((string) ($img->check_script_text ?? ''));
        if (trim($check) === '') {
            $check = "#!/bin/bash\nset -uo pipefail\n\necho \"===PRACTICE_RESULT_JSON===\"\necho '{\"score\":0,\"max\":100}'\nexit 1\n";
        }

        $this->writeUnixScript($root.'/assets/startup.sh', $startup);

        $this->writeUnixScript($root.'/assets/entrypoint.sh', $this->entrypointScript($systemd));

        // Keep existing layout expected by current templates.
        File::ensureDirectoryExists($root.'/examples/practice-checks/custom');
        $this->writeUnixScript($root.'/examples/practice-checks/custom/check.sh', $check);

        File::put($root.'/Dockerfile', $this->dockerfileFor($img));
    }

    private function dockerfileFor(PracticeImage $img): string
    {
        $base = trim((string) ($img->base_image_ref ?? ''));
        if ($base === '') {
            $base = match ((string) ($img->base_os ?? 'alt')) {
                'alma', 'centos' => 'almalinux:9',
                default => 'registry.altlinux.org/alt/alt:p10',
            };
        }

        $os = (string) ($img->base_os ?? 'alt');
        $features = is_array($img->features) ? $img->features : [];
        ['add' => $adds, 'remove' => $rems] = $this->mergedPackageLists($img, $features);

        $pkgBlock = $this->packageInstallBlock($os, $adds, $rems);
        $featBlock = $this->featuresBlock($os, $features);

        return "FROM {$base}\n\n".
            "COPY assets/entrypoint.sh /entrypoint.sh\n".
            "COPY assets/startup.sh /opt/lab/startup.sh\n".
            "COPY examples/practice-checks/custom/check.sh /opt/lab-check/check.sh\n\n".
            "RUN chmod +x /entrypoint.sh /opt/lab/startup.sh /opt/lab-check/check.sh\n\n".
            ($pkgBlock !== '' ? $pkgBlock."\n\n" : '').
            ($featBlock !== '' ? $featBlock."\n\n" : '').
            "ENTRYPOINT [\"/entrypoint.sh\"]\n";
    }

    /**
     * @return array{add:list<string>,remove:list<string>}
     */
    private function mergedPackageLists(PracticeImage $img, array $features): array
    {
        $userAdd = is_array($img->package_add) ? array_values(array_filter(array_map('strval', $img->package_add))) : [];
        $remove = is_array($img->package_remove) ? array_values(array_filter(array_map('strval', $img->package_remove))) : [];

        $add = array_values(array_unique(array_merge($this->featurePackages($features), $userAdd)));
        if ($remove !== []) {
            $removeSet = array_fill_keys($remove, true);
            $add = array_values(array_filter($add, static fn (string $p): bool => ! isset($removeSet[$p])));
        }

        return ['add' => $add, 'remove' => array_values(array_unique($remove))];
    }

    /**
     * @return list<string>
     */
    private function featurePackages(array $features): array
    {
        $systemd = (bool) ($features['systemd_mode'] ?? false);
        $sshd = (bool) ($features['sshd'] ?? false);
        $locale = trim((string) ($features['locale'] ?? ''));

        $cu = is_array($features['create_user'] ?? null) ? $features['create_user'] : [];
        $cuEnabled = (bool) ($cu['enabled'] ?? false);
        $cuSudo = (bool) ($cu['sudo'] ?? true);
        $needSudo = $cuEnabled && $cuSudo;

        $pkgs = [];
        if ($systemd) {
            $pkgs[] = 'systemd';
        }
        if ($sshd) {
            $pkgs[] = 'openssh-server';
        }
        if ($needSudo) {
            $pkgs[] = 'sudo';
        }
        if ($locale !== '') {
            $pkgs[] = 'glibc-locales';
        }

        return $pkgs;
    }

    private function packageInstallBlock(string $os, array $add, array $remove): string
    {
        $add = array_values(array_unique($add));
        $remove = array_values(array_unique($remove));

        if ($add === [] && $remove === []) {
            return '';
        }

        if (in_array($os, ['alma', 'centos'], true)) {
            $lines = [];
            $lines[] = 'RUN dnf -y update';
            if ($remove !== []) {
                $lines[] = ' && dnf -y remove '.implode(' ', array_map('escapeshellarg', $remove));
            }
            if ($add !== []) {
                $lines[] = ' && dnf -y install '.implode(' ', array_map('escapeshellarg', $add));
            }
            $lines[] = ' && dnf clean all';

            return implode('', $lines);
        }

        // alt/redos/astra: apt-get family
        $lines = [];
        $lines[] = 'RUN apt-get -y update';
        if ($remove !== []) {
            $lines[] = ' && apt-get -y remove '.implode(' ', array_map('escapeshellarg', $remove));
        }
        if ($add !== []) {
            $lines[] = ' && apt-get -y install '.implode(' ', array_map('escapeshellarg', $add));
        }
        $lines[] = ' && apt-get clean';
        $lines[] = ' && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/*';

        return implode('', $lines);
    }

    private function writeUnixScript(string $path, string $content): void
    {
        File::put($path, str_replace(["\r\n", "\r"], "\n", $content));
        @chmod($path, 0755);
    }

    private function normalizeCheckScript(string $check): string
    {
        $check = str_replace(["\r\n", "\r"], "\n", $check);
        if (trim($check) === '') {
            return $check;
        }

        if (preg_match('/^(?:ok|fail_vis|hint)\s*\(\)/m', $check)) {
            return $check;
        }

        if (! preg_match('/\b(?:ok|fail_vis|hint)\s+"|\b(?:ok|fail_vis|hint)\s+\$/', $check)) {
            return $check;
        }

        $helpers = "hint() { echo \"HINT: \$*\"; }\nok() { echo \"OK: \$*\"; }\nfail_vis() { echo \"FAIL: \$*\"; }\n\n";
        if (preg_match('/^(#!.*\n)/', $check, $m)) {
            return $m[1].$helpers.substr($check, strlen($m[1]));
        }

        return "#!/bin/bash\n".$helpers.$check;
    }

    private function entrypointScript(bool $systemd): string
    {
        if ($systemd) {
            return <<<'SH'
#!/bin/bash
set -euo pipefail

if [ -x /opt/lab/startup.sh ]; then
  /opt/lab/startup.sh || true
fi

exec /lib/systemd/systemd
SH;
        }

        return <<<'SH'
#!/bin/bash
set -euo pipefail

if [ -x /opt/lab/startup.sh ]; then
  /opt/lab/startup.sh || true
fi

exec sleep infinity
SH;
    }

    private function featuresBlock(string $os, array $features): string
    {
        $os = $os ?: 'alt';
        $lines = [];

        $systemd = (bool) ($features['systemd_mode'] ?? false);
        $sshd = (bool) ($features['sshd'] ?? false);
        $locale = trim((string) ($features['locale'] ?? ''));

        $cu = is_array($features['create_user'] ?? null) ? $features['create_user'] : [];
        $cuEnabled = (bool) ($cu['enabled'] ?? false);
        $cuName = trim((string) ($cu['name'] ?? 'student')) ?: 'student';
        $cuPass = (string) ($cu['password'] ?? 'labstudy');
        $cuSudo = (bool) ($cu['sudo'] ?? true);

        $needSudo = $cuEnabled && $cuSudo;
        $needUser = $cuEnabled;

        if ($locale !== '') {
            $safe = preg_replace('/[^A-Za-z0-9_.@-]/', '', $locale) ?: 'C.UTF-8';
            $lines[] = "ENV LANG={$safe}\nENV LC_ALL={$safe}";
        }

        if ($needUser) {
            $passEsc = str_replace("'", "'\"'\"'", $cuPass);
            $lines[] = "RUN useradd -m -s /bin/bash {$cuName} || true \\\n && echo '{$cuName}:{$passEsc}' | chpasswd";
        }

        if ($needSudo) {
            $lines[] = "RUN mkdir -p /etc/sudoers.d \\\n && printf '%s\\n' '{$cuName} ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/{$cuName}-lab \\\n && chmod 440 /etc/sudoers.d/{$cuName}-lab";
        }

        if ($systemd) {
            $lines[] = "ENV container=docker\nSTOPSIGNAL SIGRTMIN+3";
        }

        return implode("\n\n", array_values(array_filter($lines)));
    }
}

