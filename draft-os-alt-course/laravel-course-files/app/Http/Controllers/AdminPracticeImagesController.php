<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\PracticeImage;
use App\Models\CourseModulePracticeSetting;
use App\Services\PracticeImageRecipeBootstrap;
use App\Services\PracticeImageRecipeGenerator;
use App\Services\PracticeLabDaemonClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AdminPracticeImagesController extends Controller
{
    private const IMAGE_STATS_TTL_MINUTES = 10;

    public function __construct(private PracticeImageRecipeBootstrap $recipeBootstrap) {}

    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'built'); // built|library
        if (! in_array($tab, ['built', 'library'], true)) {
            $tab = 'built';
        }

        $q = trim((string) $request->query('q', ''));
        $built = $request->query('built');

        $items = PracticeImage::query()
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('title', 'like', '%'.$q.'%')
                        ->orWhere('slug', 'like', '%'.$q.'%')
                        ->orWhere('docker_tag', 'like', '%'.$q.'%');
                });
            })
            ->when($built === '1', fn ($qb) => $qb->where('is_built', true))
            ->when($built === '0', fn ($qb) => $qb->where('is_built', false))
            ->when($tab === 'built', fn ($qb) => $qb->where('is_built', true))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $client = PracticeLabDaemonClient::fromConfig();
        $statsByTag = [];
        $systemImages = $this->systemAltImages();
        if ($client) {
            foreach ($items as $row) {
                $tag = (string) $row->docker_tag;
                if ($tag !== '') {
                    $statsByTag[$tag] = $this->cachedImageStats($client, $tag);
                }
            }
            foreach ($systemImages as $sys) {
                $tag = (string) ($sys['docker_tag'] ?? '');
                if ($tag !== '') {
                    $statsByTag[$tag] = $statsByTag[$tag] ?? $this->cachedImageStats($client, $tag);
                }
            }
        }

        return view('admin.practice-images-index', [
            'systemImages' => $systemImages,
            'items' => $items,
            'tab' => $tab,
            'q' => $q,
            'built' => is_string($built) ? $built : '',
            'statsByTag' => $statsByTag,
        ]);
    }

    public function copySystem(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'template' => 'required|string|max:40',
            'title' => 'required|string|max:200',
            'docker_tag' => 'required|string|max:200',
        ]);

        $slug = Str::slug((string) $data['title']);
        if ($slug === '') {
            $slug = 'img-'.Str::lower(Str::random(8));
        }
        if (PracticeImage::query()->where('slug', $slug)->exists()) {
            $slug = $slug.'-'.Str::lower(Str::random(4));
        }

        $dockerTag = $this->suggestCopyDockerTag((string) $data['docker_tag']);

        $row = PracticeImage::query()->create([
            'title' => (string) $data['title'],
            'slug' => $slug,
            'docker_tag' => $dockerTag,
            'base_template' => (string) $data['template'],
            'dockerfile_text' => '',
            'check_script_text' => '',
            'is_built' => false,
        ]);
        $this->recipeBootstrap->initFromTemplate($row);

        return redirect()
            ->route('admin.practice.images.edit', ['id' => $row->id])
            ->with('ok', 'Создана копия системного (Alt) рецепта. Обновите tag при необходимости и нажмите «Собрать».');
    }

    public function refreshStats(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tag' => 'required|string|max:200',
            'back' => 'nullable|string|max:200',
        ]);

        $tag = trim((string) $data['tag']);
        Cache::forget($this->imageStatsCacheKey($tag));

        $client = PracticeLabDaemonClient::fromConfig();
        if (! $client) {
            return redirect()
                ->to($this->safeBack($data['back'] ?? ''))
                ->with('err', 'Lab-daemon не настроен (PRACTICE_LAB_DAEMON_URL / SECRET).');
        }

        try {
            $st = $client->imageStats($tag);
            $ok = is_array($st);
        } catch (\Throwable $e) {
            return redirect()
                ->to($this->safeBack($data['back'] ?? ''))
                ->with('err', 'Не удалось получить статус образа: '.$e->getMessage());
        }

        return redirect()
            ->to($this->safeBack($data['back'] ?? ''))
            ->with($ok ? 'ok' : 'err', $ok ? 'Статус образа обновлён: '.$tag : 'Не удалось обновить статус образа.');
    }

    public function pkgSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $os = trim((string) $request->query('os', 'alt'));
        $base = trim((string) $request->query('base_image', ''));
        $limit = (int) $request->query('limit', 20);

        if ($q === '') {
            return response()->json(['ok' => false, 'error' => 'q is required'], 400);
        }
        if (! in_array($os, ['alt', 'redos', 'astra', 'alma', 'centos'], true)) {
            return response()->json(['ok' => false, 'error' => 'bad os'], 400);
        }

        $client = PracticeLabDaemonClient::fromConfig();
        if (! $client) {
            return response()->json(['ok' => false, 'error' => 'lab-daemon is not configured'], 400);
        }
        try {
            $data = $client->pkgSearch($os, $q, $base, $limit);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => true, 'data' => $data]);
    }

    public function create(Request $request): View
    {
        return view('admin.practice-image-edit', [
            'row' => new PracticeImage([
                'title' => '',
                'slug' => '',
                'docker_tag' => '',
                'base_template' => 'lab-m1',
                'base_os' => 'alt',
                'base_image_ref' => '',
                'package_add' => [],
                'package_remove' => [],
                'features' => [],
                'startup_script_text' => '',
                'dockerfile_text' => '',
                'check_script_text' => '',
            ]),
            'isNew' => true,
            'templates' => $this->templatesList(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'slug' => 'nullable|string|max:80',
            'docker_tag' => 'required|string|max:200',
            'base_template' => 'required|string|max:40',
            'base_os' => 'required|in:alt,redos,astra,centos,alma',
            'base_image_ref' => 'nullable|string|max:200',
            'package_add_text' => 'nullable|string|max:200000',
            'package_remove_text' => 'nullable|string|max:200000',
            'startup_script_text' => 'nullable|string|max:200000',
            'features' => 'nullable|array',
            'dockerfile_text' => 'nullable|string|max:200000',
            'check_script_text' => 'nullable|string|max:200000',
            'init_from_template' => 'nullable|in:0,1',
        ]);

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug((string) $data['title']);
        }
        if ($slug === '') {
            $slug = 'img-'.Str::lower(Str::random(8));
        }
        if (PracticeImage::query()->where('slug', $slug)->exists()) {
            $slug = $slug.'-'.Str::lower(Str::random(4));
        }

        $row = PracticeImage::query()->create([
            'title' => $data['title'],
            'slug' => $slug,
            'docker_tag' => (string) $data['docker_tag'],
            'base_template' => (string) $data['base_template'],
            'base_os' => (string) $data['base_os'],
            'base_image_ref' => (string) ($data['base_image_ref'] ?? ''),
            'package_add' => $this->linesToList((string) ($data['package_add_text'] ?? '')),
            'package_remove' => $this->linesToList((string) ($data['package_remove_text'] ?? '')),
            'startup_script_text' => (string) ($data['startup_script_text'] ?? ''),
            'features' => $this->sanitizeFeatures($data['features'] ?? null),
            'dockerfile_text' => (string) ($data['dockerfile_text'] ?? ''),
            'check_script_text' => (string) ($data['check_script_text'] ?? ''),
            'is_built' => false,
        ]);

        $init = ((string) ($data['init_from_template'] ?? '1')) === '1';
        if ($init) {
            $this->recipeBootstrap->initFromTemplate($row);
            $row->refresh();
        }
        app(PracticeImageRecipeGenerator::class)->syncRecipeFiles($row);

        return redirect()
            ->route('admin.practice.images.edit', ['id' => $row->id])
            ->with('ok', 'Образ создан. Теперь можно собрать.');
    }

    public function edit(Request $request, int $id): View
    {
        $row = PracticeImage::query()->findOrFail($id);

        return view('admin.practice-image-edit', [
            'row' => $row,
            'isNew' => false,
            'templates' => $this->templatesList(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $row = PracticeImage::query()->findOrFail($id);

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'slug' => 'required|string|max:80',
            'docker_tag' => 'required|string|max:200',
            'base_template' => 'required|string|max:40',
            'base_os' => 'required|in:alt,redos,astra,centos,alma',
            'base_image_ref' => 'nullable|string|max:200',
            'package_add_text' => 'nullable|string|max:200000',
            'package_remove_text' => 'nullable|string|max:200000',
            'startup_script_text' => 'nullable|string|max:200000',
            'features' => 'nullable|array',
            'dockerfile_text' => 'nullable|string|max:200000',
            'check_script_text' => 'nullable|string|max:200000',
        ]);

        $slug = Str::slug((string) $data['slug']);
        if ($slug === '') {
            $slug = $row->slug;
        }
        $exists = PracticeImage::query()
            ->where('slug', $slug)
            ->where('id', '!=', $row->id)
            ->exists();
        if ($exists) {
            return redirect()
                ->route('admin.practice.images.edit', ['id' => $row->id])
                ->with('err', 'Slug уже занят другим образом.');
        }

        $row->title = $data['title'];
        $row->slug = $slug;
        $row->docker_tag = (string) $data['docker_tag'];
        $row->base_template = (string) $data['base_template'];
        $row->base_os = (string) $data['base_os'];
        $row->base_image_ref = (string) ($data['base_image_ref'] ?? '');
        $row->package_add = $this->linesToList((string) ($data['package_add_text'] ?? ''));
        $row->package_remove = $this->linesToList((string) ($data['package_remove_text'] ?? ''));
        $row->startup_script_text = (string) ($data['startup_script_text'] ?? '');
        $row->features = $this->sanitizeFeatures($data['features'] ?? null);
        $row->dockerfile_text = (string) ($data['dockerfile_text'] ?? '');
        $row->check_script_text = (string) ($data['check_script_text'] ?? '');
        $row->save();

        app(PracticeImageRecipeGenerator::class)->syncRecipeFiles($row);

        return redirect()
            ->route('admin.practice.images.edit', ['id' => $row->id])
            ->with('ok', 'Сохранено.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $row = PracticeImage::query()->findOrFail($id);
        $usage = CourseModulePracticeSetting::query()->where('practice_image_id', $row->id)->count();
        $finalLabCourses = 0;
        if (Schema::hasColumn('courses', 'final_lab_practice_image_id')) {
            $finalLabCourses = (int) Course::query()->where('final_lab_practice_image_id', $row->id)->count();
        }
        $blocked = $usage + $finalLabCourses;
        abort_if(
            $blocked > 0,
            422,
            $usage > 0
                ? 'Образ привязан к практикам модулей ('.$usage.'). Сначала отключите его в настройках курса.'
                : 'Образ выбран для финальной лабораторной в '.$finalLabCourses.' курс(ах). Снимите привязку в настройках курса.'
        );
        $row->delete();

        return redirect()
            ->route('admin.practice.images.index')
            ->with('ok', 'Удалено.');
    }

    public function build(Request $request, int $id): RedirectResponse
    {
        $row = PracticeImage::query()->findOrFail($id);
        $client = PracticeLabDaemonClient::fromConfig();
        if (! $client) {
            return redirect()
                ->route('admin.practice.images.edit', ['id' => $row->id])
                ->with('err', 'Lab-daemon не настроен (PRACTICE_LAB_DAEMON_URL / SECRET).');
        }

        app(PracticeImageRecipeGenerator::class)->syncRecipeFiles($row);

        $row->last_build_status = 'running';
        $row->last_build_log = null;
        $row->save();

        $contextDir = $this->recipeBootstrap->contextDirRel($row);
        $dockerfileRel = 'Dockerfile';
        try {
            $resp = $client->imageBuild([
                'context_dir' => $contextDir,
                'dockerfile_rel' => $dockerfileRel,
                'tags' => [(string) $row->docker_tag],
                'build_args' => null,
            ]);
        } catch (\Throwable $e) {
            $row->last_build_status = 'fail';
            $row->last_build_log = 'Ошибка связи с lab-daemon: '.$e->getMessage();
            $row->save();

            return redirect()
                ->route('admin.practice.images.edit', ['id' => $row->id])
                ->with('err', 'Не удалось собрать: '.$e->getMessage());
        }

        $ok = (bool) ($resp['ok'] ?? false);
        $row->is_built = $ok;
        $row->last_build_status = $ok ? 'ok' : 'fail';
        $row->last_build_log = is_string($resp['log'] ?? null) ? (string) $resp['log'] : null;
        $row->last_built_at = now();
        $row->save();
        Cache::forget($this->imageStatsCacheKey((string) $row->docker_tag));

        return redirect()
            ->route('admin.practice.images.edit', ['id' => $row->id])
            ->with($ok ? 'ok' : 'err', $ok ? 'Сборка завершена.' : 'Сборка завершилась с ошибкой (см. лог).');
    }

    public function export(Request $request, int $id): RedirectResponse
    {
        $row = PracticeImage::query()->findOrFail($id);
        $client = PracticeLabDaemonClient::fromConfig();
        if (! $client) {
            return redirect()
                ->route('admin.practice.images.edit', ['id' => $row->id])
                ->with('err', 'Lab-daemon не настроен (PRACTICE_LAB_DAEMON_URL / SECRET).');
        }

        $name = $row->slug.'-'.now()->format('Ymd-His').'.tar';
        try {
            $resp = $client->imageExport([
                'tag' => (string) $row->docker_tag,
                'out_name' => $name,
            ]);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.practice.images.edit', ['id' => $row->id])
                ->with('err', 'Не удалось экспортировать: '.$e->getMessage());
        }

        $ok = (bool) ($resp['ok'] ?? false);
        if ($ok && ! empty($resp['out_path'])) {
            $row->export_path = (string) $resp['out_path'];
            $row->save();
        }

        return redirect()
            ->route('admin.practice.images.edit', ['id' => $row->id])
            ->with($ok ? 'ok' : 'err', $ok ? 'Экспорт выполнен: '.$row->export_path : 'Экспорт завершился с ошибкой (см. лог daemon).');
    }

    private function templatesList(): array
    {
        return [
            'lab-m1' => 'Alt · Модуль 1',
            'lab-m2' => 'Alt · Модуль 2',
            'lab-m3' => 'Alt · Модуль 3',
            'lab-m5' => 'Alt · Модуль 5',
            'lab-m6' => 'Alt · Модуль 6',
            'lab-m7' => 'Alt · Модуль 7',
            'lab-m8' => 'Alt · Модуль 8',
            'lab-m8-systemd' => 'Alt · Модуль 8 (systemd тег)',
            'lab-m9' => 'Alt · Модуль 9',
            'final-lab' => 'Alt · Финальная',
        ];
    }

    /**
     * @return list<array{module_key:int,title:string,template:string,docker_tag:string}>
     */
    private function systemAltImages(): array
    {
        $images = config('practice_lab.images', []);
        if (! is_array($images)) {
            return [];
        }

        $map = [
            1 => ['title' => 'Alt · Модуль 1', 'template' => 'lab-m1'],
            2 => ['title' => 'Alt · Модуль 2', 'template' => 'lab-m2'],
            3 => ['title' => 'Alt · Модуль 3', 'template' => 'lab-m3'],
            5 => ['title' => 'Alt · Модуль 5', 'template' => 'lab-m5'],
            6 => ['title' => 'Alt · Модуль 6', 'template' => 'lab-m6'],
            7 => ['title' => 'Alt · Модуль 7', 'template' => 'lab-m7'],
            8 => ['title' => 'Alt · Модуль 8', 'template' => 'lab-m8'],
            9 => ['title' => 'Alt · Модуль 9', 'template' => 'lab-m9'],
            10 => ['title' => 'Alt · Финальная', 'template' => 'final-lab'],
        ];

        $out = [];
        foreach ($map as $k => $meta) {
            $tag = isset($images[(string) $k]) ? trim((string) $images[(string) $k]) : '';
            if ($tag === '') {
                continue;
            }
            $out[] = [
                'module_key' => (int) $k,
                'title' => (string) $meta['title'],
                'template' => (string) $meta['template'],
                'docker_tag' => $tag,
            ];
        }

        return $out;
    }

    private function suggestCopyDockerTag(string $src): string
    {
        $s = trim($src);
        if ($s === '') {
            return 'practice-image:copy';
        }
        if (str_contains($s, ':')) {
            [$name, $tag] = explode(':', $s, 2);
            $name = trim($name);
            $tag = trim($tag);
            if ($name === '') {
                return $s.'-copy';
            }
            $tag = $tag !== '' ? $tag.'-copy' : 'copy';

            return $name.':'.$tag;
        }

        return $s.':copy';
    }

    private function imageStatsCacheKey(string $image): string
    {
        return 'admin_docker_image_stats:'.sha1($image);
    }

    private function cachedImageStats(PracticeLabDaemonClient $client, string $image): ?array
    {
        $key = $this->imageStatsCacheKey($image);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }
        try {
            $data = $client->imageStats($image);
            $ok = is_array($data) ? $data : null;
        } catch (\Throwable) {
            $ok = null;
        }
        Cache::put($key, $ok, now()->addMinutes(self::IMAGE_STATS_TTL_MINUTES));

        return $ok;
    }

    private function safeBack(string $back): string
    {
        $b = trim($back);
        if ($b !== '' && str_starts_with($b, '/')) {
            return $b;
        }

        return route('admin.practice.images.index');
    }

    /**
     * @return list<string>
     */
    private function linesToList(string $s): array
    {
        $lines = preg_split('/\R/u', $s) ?: [];
        $out = [];
        foreach ($lines as $ln) {
            $v = trim((string) $ln);
            if ($v !== '') {
                $out[] = $v;
            }
        }

        return array_values(array_unique($out));
    }

    private function sanitizeFeatures($features): array
    {
        $f = is_array($features) ? $features : [];

        $out = [
            'systemd_mode' => (bool) ($f['systemd_mode'] ?? false),
            'sshd' => (bool) ($f['sshd'] ?? false),
            'locale' => trim((string) ($f['locale'] ?? '')),
            'create_user' => [
                'enabled' => (bool) (($f['create_user']['enabled'] ?? false)),
                'name' => trim((string) ($f['create_user']['name'] ?? 'student')),
                'password' => (string) ($f['create_user']['password'] ?? 'labstudy'),
                'sudo' => (bool) (($f['create_user']['sudo'] ?? true)),
            ],
        ];

        if ($out['create_user']['name'] === '') {
            $out['create_user']['name'] = 'student';
        }

        return $out;
    }
}

