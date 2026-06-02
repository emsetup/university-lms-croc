<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesPracticeImagesForStaff;
use App\Models\Course;
use App\Models\PracticeImage;
use App\Models\CourseModulePracticeSetting;
use App\Services\PracticeImageRecipeBootstrap;
use App\Support\LegacyAltPracticeImageCatalog;
use App\Support\PracticeImageWizardCatalog;
use App\Services\PracticeImageBuildService;
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
    use ScopesPracticeImagesForStaff;

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

        $items = $this->scopePracticeImagesForStaff(PracticeImage::query())
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

        $row = PracticeImage::query()->create($this->withPracticeImageOwner([
            'title' => (string) $data['title'],
            'slug' => $slug,
            'docker_tag' => $dockerTag,
            'base_template' => (string) $data['template'],
            'dockerfile_text' => '',
            'check_script_text' => '',
            'is_built' => false,
        ]));
        $this->recipeBootstrap->initFromTemplate($row);

        return redirect()
            ->to($this->practiceImageEditUrl((int) $row->id))
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
        $preset = trim((string) $request->query('preset', ''));

        return view('admin.practice-image-edit', array_merge(
            $this->wizardViewData($request, new PracticeImage([
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
            ]), true),
            ['wizardPreset' => $preset]
        ));
    }

    public function cloneFrom(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_id' => 'required|integer|exists:practice_images,id',
            'title' => 'required|string|max:200',
            'docker_tag' => 'required|string|max:200',
        ]);

        $src = PracticeImage::query()->findOrFail((int) $data['source_id']);
        $this->assertCanEditPracticeImage($src);

        $slug = Str::slug((string) $data['title']);
        if ($slug === '') {
            $slug = 'copy-'.Str::lower(Str::random(6));
        }
        if (PracticeImage::query()->where('slug', $slug)->exists()) {
            $slug = $slug.'-'.Str::lower(Str::random(4));
        }

        $row = PracticeImage::query()->create($this->withPracticeImageOwner([
            'title' => (string) $data['title'],
            'slug' => $slug,
            'docker_tag' => $this->suggestCopyDockerTag((string) $data['docker_tag']),
            'description' => $src->description,
            'base_template' => (string) $src->base_template,
            'base_os' => (string) ($src->base_os ?? 'alt'),
            'base_image_ref' => (string) ($src->base_image_ref ?? ''),
            'package_add' => is_array($src->package_add) ? $src->package_add : [],
            'package_remove' => is_array($src->package_remove) ? $src->package_remove : [],
            'features' => is_array($src->features) ? $src->features : [],
            'startup_script_text' => (string) ($src->startup_script_text ?? ''),
            'dockerfile_text' => (string) ($src->dockerfile_text ?? ''),
            'check_script_text' => (string) ($src->check_script_text ?? ''),
            'is_built' => false,
            'last_build_status' => null,
            'last_build_log' => null,
        ]));

        app(PracticeImageRecipeGenerator::class)->syncRecipeFiles($row);
        $recipeRoot = $this->recipeBootstrap->recipeRootAbs($src);
        $destRoot = $this->recipeBootstrap->recipeRootAbs($row);
        if (is_dir($recipeRoot) && $recipeRoot !== $destRoot) {
            try {
                \Illuminate\Support\Facades\File::copyDirectory($recipeRoot, $destRoot);
            } catch (\Throwable) {
                // рецепт уже синхронизирован из полей БД
            }
        }

        return redirect()
            ->to($this->practiceImageEditUrl((int) $row->id, $request).'#step-review')
            ->with('ok', 'Копия создана из «'.$src->title.'». Проверьте тег и соберите образ.');
    }

    public function reimportTemplate(Request $request, int $id): RedirectResponse
    {
        $row = PracticeImage::query()->findOrFail($id);
        $this->assertCanEditPracticeImage($row);
        $this->recipeBootstrap->initFromTemplate($row);
        $row->refresh();
        app(PracticeImageRecipeGenerator::class)->syncRecipeFiles($row);

        return redirect()
            ->to($this->practiceImageEditUrl((int) $row->id, $request))
            ->with('ok', 'Скрипты и Dockerfile снова загружены из шаблона '.$row->base_template.'.');
    }

    public function recipePreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base_os' => 'required|in:alt,redos,astra,centos,alma',
            'base_image_ref' => 'nullable|string|max:200',
            'package_add_text' => 'nullable|string|max:200000',
            'package_remove_text' => 'nullable|string|max:200000',
            'startup_script_text' => 'nullable|string|max:200000',
            'features' => 'nullable|array',
        ]);

        $row = new PracticeImage([
            'base_os' => (string) $data['base_os'],
            'base_image_ref' => (string) ($data['base_image_ref'] ?? ''),
            'package_add' => $this->linesToList((string) ($data['package_add_text'] ?? '')),
            'package_remove' => $this->linesToList((string) ($data['package_remove_text'] ?? '')),
            'startup_script_text' => (string) ($data['startup_script_text'] ?? ''),
            'features' => $this->sanitizeFeatures($data['features'] ?? null),
        ]);

        $gen = app(PracticeImageRecipeGenerator::class);
        $dockerfile = $gen->previewDockerfile($row);
        $startup = trim((string) ($row->startup_script_text ?? ''));
        if ($startup === '') {
            $startup = "#!/usr/bin/env bash\nset -euo pipefail\n\n# TODO: prepare lab state here\n";
        }
        $check = trim((string) ($request->input('check_script_text') ?? ''));
        if ($check === '') {
            $check = "#!/bin/bash\nset -uo pipefail\n\necho \"===PRACTICE_RESULT_JSON===\"\necho '{\"score\":0,\"max\":100}'\nexit 1\n";
        }

        return response()->json([
            'ok' => true,
            'dockerfile' => $dockerfile,
            'startup' => $startup,
            'check' => $check,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
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

        $row = PracticeImage::query()->create($this->withPracticeImageOwner([
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
        ]));

        $init = ((string) ($data['init_from_template'] ?? '1')) === '1';
        if ($init) {
            $this->recipeBootstrap->initFromTemplate($row);
            $row->refresh();
        }
        try {
            app(PracticeImageRecipeGenerator::class)->syncRecipeFiles($row);
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
            }
            throw $e;
        }

        if ($request->wantsJson()) {
            $buildUrl = $this->practiceImageBuildUrl((int) $row->id, $request);

            return response()->json([
                'ok' => true,
                'id' => $row->id,
                'edit_url' => $this->practiceImageEditUrl((int) $row->id, $request).'#step-review',
                'build_url' => $buildUrl,
                'daemon_configured' => PracticeLabDaemonClient::fromConfig() !== null,
            ]);
        }

        return redirect()
            ->to($this->practiceImageEditUrl((int) $row->id, $request))
            ->with('ok', 'Образ создан. Теперь можно собрать.');
    }

    public function edit(Request $request, int $id): View
    {
        $row = PracticeImage::query()->findOrFail($id);
        $this->assertCanEditPracticeImage($row);

        return view('admin.practice-image-edit', $this->wizardViewData(
            $request,
            $row,
            false,
            ['wizardPreset' => '']
        ));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $row = PracticeImage::query()->findOrFail($id);
        $this->assertCanEditPracticeImage($row);

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
                ->to($this->practiceImageEditUrl((int) $row->id))
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
            ->to($this->practiceImageEditUrl((int) $row->id, $request))
            ->with('ok', 'Сохранено.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $row = PracticeImage::query()->findOrFail($id);
        $this->assertCanEditPracticeImage($row);
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
            ->to($this->practiceImagesListUrl($request))
            ->with('ok', 'Удалено.');
    }

    public function build(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $row = PracticeImage::query()->findOrFail($id);
        $this->assertCanEditPracticeImage($row);
        $client = PracticeLabDaemonClient::fromConfig();
        if (! $client) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'error' => 'Lab-daemon не настроен (PRACTICE_LAB_DAEMON_URL / SECRET).'], 503);
            }

            return redirect()
                ->to($this->practiceImageEditUrl((int) $row->id, $request))
                ->with('err', 'Lab-daemon не настроен (PRACTICE_LAB_DAEMON_URL / SECRET).');
        }

        $result = app(PracticeImageBuildService::class)->build($row, $client);
        Cache::forget($this->imageStatsCacheKey((string) $row->docker_tag));

        if ($request->wantsJson()) {
            return response()->json(array_merge($result, [
                'id' => $row->id,
                'redirect' => $this->practiceImageEditUrl((int) $row->id, $request).'#step-review',
            ]), $result['ok'] ? 200 : 422);
        }

        return redirect()
            ->to($this->practiceImageEditUrl((int) $row->id, $request))
            ->with($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'Сборка завершена.' : 'Сборка завершилась с ошибкой (см. лог).');
    }

    public function export(Request $request, int $id): RedirectResponse
    {
        $row = PracticeImage::query()->findOrFail($id);
        $this->assertCanEditPracticeImage($row);
        $client = PracticeLabDaemonClient::fromConfig();
        if (! $client) {
            return redirect()
                ->to($this->practiceImageEditUrl((int) $row->id, $request))
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
                ->to($this->practiceImageEditUrl((int) $row->id, $request))
                ->with('err', 'Не удалось экспортировать: '.$e->getMessage());
        }

        $ok = (bool) ($resp['ok'] ?? false);
        if ($ok && ! empty($resp['out_path'])) {
            $row->export_path = (string) $resp['out_path'];
            $row->save();
        }

        return redirect()
            ->to($this->practiceImageEditUrl((int) $row->id, $request))
            ->with($ok ? 'ok' : 'err', $ok ? 'Экспорт выполнен: '.$row->export_path : 'Экспорт завершился с ошибкой (см. лог daemon).');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function wizardViewData(Request $request, PracticeImage $row, bool $isNew = true, array $extra = []): array
    {
        $libraryImages = $this->scopePracticeImagesForStaff(PracticeImage::query())
            ->when($row->exists, fn ($q) => $q->where('id', '!=', (int) $row->id))
            ->orderByDesc('updated_at')
            ->limit(36)
            ->get(['id', 'title', 'docker_tag', 'base_template', 'base_os', 'is_built', 'slug']);

        return array_merge([
            'piRouteScope' => $this->practiceImageRouteScope($request),
            'row' => $row,
            'isNew' => $isNew,
            'templates' => $this->templatesList(),
            'wizardSteps' => PracticeImageWizardCatalog::wizardSteps(),
            'wizardHelp' => PracticeImageWizardCatalog::wizardHelp(),
            'builtinTemplates' => PracticeImageWizardCatalog::builtinTemplates(),
            'osChoices' => PracticeImageWizardCatalog::osChoices(),
            'packageGroups' => PracticeImageWizardCatalog::packageGroups(),
            'featureToggles' => PracticeImageWizardCatalog::featureToggles(),
            'startupPresets' => PracticeImageWizardCatalog::startupPresets(),
            'startupCategories' => PracticeImageWizardCatalog::startupPresetCategories(),
            'checkPresets' => PracticeImageWizardCatalog::checkPresets(),
            'checkCategories' => PracticeImageWizardCatalog::checkPresetCategories(),
            'checkTaskTypes' => PracticeImageWizardCatalog::checkTaskTypes(),
            'checkExampleGrids' => PracticeImageWizardCatalog::checkExampleGrids(),
            'checkCommonServices' => PracticeImageWizardCatalog::checkCommonServices(),
            'checkServiceStates' => PracticeImageWizardCatalog::checkServiceStates(),
            'libraryImages' => $libraryImages,
            'daemonConfigured' => PracticeLabDaemonClient::fromConfig() !== null,
        ], $extra);
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
        return array_map(static function (array $entry): array {
            return [
                'module_key' => (int) $entry['module_key'],
                'title' => (string) $entry['title'],
                'template' => (string) $entry['template'],
                'docker_tag' => (string) $entry['docker_tag'],
            ];
        }, LegacyAltPracticeImageCatalog::entries());
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

    private function practiceImageRouteScope(?Request $request = null): string
    {
        if ($request !== null && $request->routeIs('admin.docker.*')) {
            return 'docker';
        }

        return $this->adminCourseRouteParams() !== [] ? 'course' : 'docker';
    }

    private function practiceImageEditUrl(int $id, ?Request $request = null): string
    {
        if ($this->practiceImageRouteScope($request) === 'docker') {
            return route('admin.docker.library.edit', ['id' => $id]);
        }

        return $this->adminCourseRoute('admin.practice.images.edit', ['id' => $id]);
    }

    private function practiceImageBuildUrl(int $id, ?Request $request = null): string
    {
        if ($this->practiceImageRouteScope($request) === 'docker') {
            return route('admin.docker.library.build', ['id' => $id]);
        }

        return $this->adminCourseRoute('admin.practice.images.build', ['id' => $id]);
    }

    private function practiceImagesListUrl(?Request $request = null): string
    {
        if ($this->practiceImageRouteScope($request) === 'docker') {
            return route('admin.docker.library');
        }

        return $this->adminCourseRoute('admin.practice.images.index');
    }

    private function safeBack(string $back): string
    {
        $b = trim($back);
        if ($b !== '' && str_starts_with($b, '/')) {
            return $b;
        }

        return $this->practiceImagesListUrl();
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

