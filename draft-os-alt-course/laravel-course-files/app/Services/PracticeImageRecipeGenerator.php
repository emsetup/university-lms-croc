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

        $check = (string) ($img->check_script_text ?? '');
        if (trim($check) === '') {
            $check = "#!/bin/bash\nset -uo pipefail\n\necho \"===PRACTICE_RESULT_JSON===\"\necho '{\"score\":0,\"max\":100}'\nexit 1\n";
        }

        File::put($root.'/assets/startup.sh', $startup);
        @chmod($root.'/assets/startup.sh', 0755);

        File::put($root.'/assets/entrypoint.sh', $this->entrypointScript($systemd));
        @chmod($root.'/assets/entrypoint.sh', 0755);

        // Keep existing layout expected by current templates.
        File::ensureDirectoryExists($root.'/examples/practice-checks/custom');
        File::put($root.'/examples/practice-checks/custom/check.sh', $check);
        @chmod($root.'/examples/practice-checks/custom/check.sh', 0755);

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

        $adds = is_array($img->package_add) ? array_values(array_filter(array_map('strval', $img->package_add))) : [];
        $rems = is_array($img->package_remove) ? array_values(array_filter(array_map('strval', $img->package_remove))) : [];

        $features = is_array($img->features) ? $img->features : [];
        $featBlock = $this->featuresBlock((string) ($img->base_os ?? 'alt'), $features);

        $pkgBlock = $this->packageInstallBlock((string) ($img->base_os ?? 'alt'), $adds, $rems);

        return "FROM {$base}\n\n".
            "COPY assets/entrypoint.sh /entrypoint.sh\n".
            "COPY assets/startup.sh /opt/lab/startup.sh\n".
            "COPY examples/practice-checks/custom/check.sh /opt/lab-check/check.sh\n\n".
            "RUN chmod +x /entrypoint.sh /opt/lab/startup.sh /opt/lab-check/check.sh\n\n".
            ($featBlock !== '' ? $featBlock."\n\n" : '').
            ($pkgBlock !== '' ? $pkgBlock."\n\n" : '').
            "ENTRYPOINT [\"/entrypoint.sh\"]\n";
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

    private function entrypointScript(bool $systemd): string
    {
        if ($systemd) {
            return <<<'SH'
#!/usr/bin/env bash
set -euo pipefail

if [ -x /opt/lab/startup.sh ]; then
  /opt/lab/startup.sh || true
fi

exec /sbin/init
SH;
        }

        return <<<'SH'
#!/usr/bin/env bash
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

        $pkgs = [];
        if ($systemd) $pkgs[] = 'systemd';
        if ($sshd) $pkgs[] = 'openssh-server';
        if ($needSudo) $pkgs[] = 'sudo';
        if ($locale !== '') $pkgs[] = 'glibc-locales';

        if ($pkgs !== []) {
            $lines[] = $this->packageInstallBlock($os, $pkgs, []);
        }

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

