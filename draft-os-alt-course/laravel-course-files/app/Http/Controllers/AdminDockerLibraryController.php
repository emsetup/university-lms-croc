<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseModulePracticeSetting;
use App\Models\PracticeImage;
use App\Services\PracticeImageRecipeBootstrap;
use App\Services\PracticeImageRecipeGenerator;
use App\Services\PracticeLabDaemonClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AdminDockerLibraryController extends Controller
{
    private const IMAGE_STATS_TTL_MINUTES = 10;

    public function __construct(private PracticeImageRecipeBootstrap $recipeBootstrap) {}

    public function index(Request $request): View
    {
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

        return view('admin.docker-library', [
            'items' => $items,
            'q' => $q,
            'statsByTag' => $statsByTag,
            'daemonConfigured' => $client !== null,
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

    public function build(Request $request, int $id): RedirectResponse
    {
        $row = PracticeImage::query()->findOrFail($id);
        $client = PracticeLabDaemonClient::fromConfig();
        if (! $client) {
            return redirect()
                ->route('admin.docker.library')
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
                ->route('admin.docker.library')
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
            ->route('admin.docker.library')
            ->with($ok ? 'ok' : 'err', $ok ? 'Сборка завершена.' : 'Сборка завершилась с ошибкой (см. лог в конструкторе образа).');
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
