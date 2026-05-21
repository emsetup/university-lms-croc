<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseModulePracticeSetting;
use App\Models\PracticeImage;
use App\Services\LegacyAltPracticeImagesBootstrap;
use App\Services\PracticeImageRecipeBootstrap;
use App\Services\PracticeImageBuildService;
use App\Services\PracticeImageRecipeGenerator;
use App\Services\PracticeImageSandboxService;
use App\Services\PracticeLabDaemonClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AdminDockerLibraryController extends Controller
{
    private const IMAGE_STATS_TTL_MINUTES = 10;

    public function __construct(private PracticeImageRecipeBootstrap $recipeBootstrap) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->query('create')) {
            return redirect()->route('admin.docker.library.create');
        }

        LegacyAltPracticeImagesBootstrap::sync();

        $q = trim((string) $request->query('q', ''));

        $items = PracticeImage::query()
            ->with([
                'modulePracticeSettings' => static function ($rel) {
                    $rel->with(['courseModule' => static function ($cm) {
                        $cm->with(['course:id,title']);
                    }]);
                },
            ])
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('title', 'like', '%'.$q.'%')
                        ->orWhere('slug', 'like', '%'.$q.'%')
                        ->orWhere('docker_tag', 'like', '%'.$q.'%');
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $client = PracticeLabDaemonClient::fromConfig();
        $statsByTag = [];
        if ($client) {
            foreach ($items as $row) {
                $tag = (string) $row->docker_tag;
                if ($tag !== '') {
                    $statsByTag[$tag] = $this->cachedImageStats($client, $tag);
                }
            }
        }

        $sandbox = PracticeImageSandboxService::make();
        $sandboxById = [];
        foreach ($items as $row) {
            $sandboxById[(int) $row->id] = $sandbox->getState((int) $row->id);
        }

        return view('admin.docker-library', [
            'items' => $items,
            'q' => $q,
            'statsByTag' => $statsByTag,
            'sandboxById' => $sandboxById,
            'daemonConfigured' => $client !== null,
            'practiceLabEnabled' => (bool) config('practice_lab.enabled'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'docker_tag' => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
        ]);

        $slug = Str::slug((string) $data['title']);
        if ($slug === '') {
            $slug = 'img-'.Str::lower(Str::random(8));
        }
        if (PracticeImage::query()->where('slug', $slug)->exists()) {
            $slug = $slug.'-'.Str::lower(Str::random(4));
        }

        $row = PracticeImage::query()->create([
            'title' => (string) $data['title'],
            'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            'slug' => $slug,
            'docker_tag' => trim((string) $data['docker_tag']),
            'base_template' => 'lab-m1',
            'base_os' => 'alt',
            'base_image_ref' => '',
            'package_add' => [],
            'package_remove' => [],
            'features' => [],
            'startup_script_text' => '',
            'dockerfile_text' => '',
            'check_script_text' => '',
            'is_built' => false,
        ]);

        $this->recipeBootstrap->initFromTemplate($row);
        $row->refresh();
        app(PracticeImageRecipeGenerator::class)->syncRecipeFiles($row);

        return redirect()
            ->route('admin.docker.library')
            ->with('ok', 'Образ создан. Откройте конструктор курса, чтобы настроить рецепт и собрать образ.');
    }

    public function refreshStats(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tag' => 'required|string|max:200',
        ]);

        $tag = trim((string) $data['tag']);
        Cache::forget($this->imageStatsCacheKey($tag));

        $client = PracticeLabDaemonClient::fromConfig();
        if (! $client) {
            return redirect()
                ->route('admin.docker.library')
                ->with('err', 'Lab-daemon не настроен (PRACTICE_LAB_DAEMON_URL / SECRET).');
        }

        try {
            $st = $client->imageStats($tag);
            $ok = is_array($st);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.docker.library')
                ->with('err', 'Не удалось получить статус образа: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.docker.library')
            ->with($ok ? 'ok' : 'err', $ok ? 'Статус образа обновлён: '.$tag : 'Не удалось обновить статус образа.');
    }

    public function build(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $row = PracticeImage::query()->findOrFail($id);
        $client = PracticeLabDaemonClient::fromConfig();
        if (! $client) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'error' => 'Lab-daemon не настроен (PRACTICE_LAB_DAEMON_URL / SECRET).'], 503);
            }

            return redirect()
                ->route('admin.docker.library')
                ->with('err', 'Lab-daemon не настроен (PRACTICE_LAB_DAEMON_URL / SECRET).');
        }

        $result = app(PracticeImageBuildService::class)->build($row, $client);
        Cache::forget($this->imageStatsCacheKey((string) $row->docker_tag));

        if ($request->wantsJson()) {
            return response()->json(array_merge($result, [
                'id' => $row->id,
                'redirect' => route('admin.docker.library.edit', ['id' => $row->id]).'#step-review',
            ]), $result['ok'] ? 200 : 422);
        }

        return redirect()
            ->route('admin.docker.library')
            ->with($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'Сборка завершена.' : 'Сборка завершилась с ошибкой (см. лог в конструкторе образа).');
    }

    public function sandboxStatus(int $id): JsonResponse
    {
        $row = PracticeImage::query()->findOrFail($id);
        $state = PracticeImageSandboxService::make()->getState((int) $row->id);

        return response()->json([
            'ok' => true,
            'image_id' => $row->id,
            'docker_tag' => (string) $row->docker_tag,
            'is_built' => (bool) $row->is_built,
            'daemon_module_key' => PracticeImageSandboxService::daemonModuleKeyForImage($row),
            'state' => $state,
        ]);
    }

    public function sandboxStart(Request $request, int $id): JsonResponse
    {
        if ($this->isReadOnlyAccess($request)) {
            return response()->json(['ok' => false, 'error' => 'Режим модератора: запуск стенда недоступен.'], 403);
        }

        $row = PracticeImage::query()->findOrFail($id);
        $svc = PracticeImageSandboxService::make();
        if (! $svc->isDaemonReady()) {
            return response()->json(['ok' => false, 'error' => 'Lab-daemon не настроен.'], 503);
        }

        $result = $svc->start($row);

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    public function sandboxCheck(Request $request, int $id): JsonResponse
    {
        if ($this->isReadOnlyAccess($request)) {
            return response()->json(['ok' => false, 'error' => 'Режим модератора: проверка недоступна.'], 403);
        }

        $row = PracticeImage::query()->findOrFail($id);
        $svc = PracticeImageSandboxService::make();
        if (! $svc->isDaemonReady()) {
            return response()->json(['ok' => false, 'error' => 'Lab-daemon не настроен.'], 503);
        }

        $result = $svc->runCheck((int) $row->id);

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    public function sandboxStop(Request $request, int $id): JsonResponse
    {
        if ($this->isReadOnlyAccess($request)) {
            return response()->json(['ok' => false, 'error' => 'Режим модератора: остановка недоступна.'], 403);
        }

        PracticeImage::query()->findOrFail($id);
        $result = PracticeImageSandboxService::make()->stop($id);

        return response()->json($result);
    }

    public function destroy(int $id): RedirectResponse
    {
        $row = PracticeImage::query()->findOrFail($id);
        $usage = CourseModulePracticeSetting::query()->where('practice_image_id', $row->id)->count();
        $finalLabCourses = 0;
        if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'final_lab_practice_image_id')) {
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
            ->route('admin.docker.library')
            ->with('ok', 'Образ удалён.');
    }

    private function isReadOnlyAccess(Request $request): bool
    {
        return (bool) $request->attributes->get('course_admin_readonly', false);
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
}
