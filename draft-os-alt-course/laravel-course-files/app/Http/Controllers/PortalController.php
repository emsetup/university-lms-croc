<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\Learner;
use App\Services\CourseModuleService;
use App\Services\CourseScoringService;
use App\Support\CourseAudiencePlaque;
use App\Support\LearnerSsoDisplayNamePersistence;
use App\Support\OidcIdentityClaims;
use App\Support\LearnerPreviewContext;
use App\Support\PortalWelcomeInitials;
use App\Support\PortalWelcomeName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class PortalController extends Controller
{
    public function __construct(
        private CourseScoringService $scoring,
        private CourseModuleService $courseModules,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $learnerId = LearnerPreviewContext::learnerId($request);

        $courses = Course::query()
            ->where('is_published', true)
            ->where('is_archived', false)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $enrollmentsByCourseId = [];
        $progressByCourseId = [];
        $modulesProgressByCourseId = [];
        $portalWelcomeName = null;
        $learner = null;
        if ($learnerId > 0) {
            /** @var Learner $learner */
            $learner = Learner::query()
                ->with(['moduleProgresses', 'finalLabResults'])
                ->findOrFail($learnerId);

            $portalWelcomeName = PortalWelcomeName::forLearner($learner);
            LearnerSsoDisplayNamePersistence::syncIfPossible($learner);

            $enrollments = CourseEnrollment::query()
                ->where('learner_id', $learner->id)
                ->get();
            foreach ($enrollments as $e) {
                $enrollmentsByCourseId[(int) $e->course_id] = $e;
            }

            foreach ($courses as $c) {
                $courseId = (int) $c->id;
                $hasModules = Schema::hasTable('course_modules')
                    && CourseModule::query()->where('course_id', $courseId)->exists();
                $progressByCourseId[$courseId] = $hasModules
                    ? $this->scoring->certificateCoursePercent($learner, $courseId)
                    : 0;
                $modulesProgressByCourseId[$courseId] = $this->modulesPassedTotal($learner, $courseId);
            }
        }

        $identityDebugRows = [];
        if ($request->boolean('identity_debug') && $learner instanceof Learner) {
            $identityDebugRows = $this->buildIdentityDebugRows($request, $learner, $portalWelcomeName);
        }

        $catalogFilterTags = $courses
            ->pluck('tags')
            ->filter()
            ->flatten()
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $portalAudienceModal = false;
        foreach ($courses as $c) {
            $ap = CourseAudiencePlaque::forCourse($c);
            if ($ap !== null && $ap['hasModal']) {
                $portalAudienceModal = true;
                break;
            }
        }

        return view('portal.index', [
            'courses' => $courses,
            'portalAudienceModal' => $portalAudienceModal,
            'showLogin' => (bool) $request->query('login', false),
            'enrollmentsByCourseId' => $enrollmentsByCourseId,
            'progressByCourseId' => $progressByCourseId,
            'modulesProgressByCourseId' => $modulesProgressByCourseId,
            'portalWelcomeName' => $portalWelcomeName,
            'portalWelcomeInitials' => $learner instanceof Learner
                ? PortalWelcomeInitials::from($portalWelcomeName, (string) $learner->email)
                : '—',
            'learnerEmail' => $learner instanceof Learner ? $learner->email : null,
            'identityDebugRows' => $identityDebugRows,
            'catalogFilterTags' => $catalogFilterTags,
        ]);
    }

    /**
     * @return array{passed: int, total: int}
     */
    private function modulesPassedTotal(Learner $learner, int $courseId): array
    {
        if (! Schema::hasTable('course_modules')) {
            return ['passed' => 0, 'total' => 0];
        }
        $mods = $this->courseModules->orderedModulesForCourse($courseId);
        $total = $mods->count();
        if ($total === 0) {
            return ['passed' => 0, 'total' => 0];
        }
        $passed = 0;
        foreach ($mods as $mod) {
            $p = $learner->progressExisting((int) $mod->id, $courseId);
            if ($p && $p->module_exam_passed) {
                $passed++;
            }
        }

        return ['passed' => $passed, 'total' => $total];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function buildIdentityDebugRows(Request $request, Learner $learner, ?string $portalWelcomeName): array
    {
        $rows = [];

        $probe = session('oidc_identity_probe_claims');
        $hasFioClaims = is_array($probe) && OidcIdentityClaims::hasAnyNameLikeClaim($probe);
        $rows[] = [
            'label' => '[вывод] Есть ли в снимке «узнаваемые» claim’ы для ФИО (эвристика)',
            'value' => $hasFioClaims ? 'да — см. строки [OIDC claim] и блок [ожидание ФИО] ниже' : 'нет — см. блок [ожидание ФИО] ниже (там перечислены все проверяемые варианты)',
        ];

        foreach (OidcIdentityClaims::nameClaimsCoverageRows(is_array($probe) ? $probe : []) as $row) {
            $rows[] = $row;
        }

        $rows[] = ['label' => '[приветствие] portalWelcomeName (что пойдёт в «Добро пожаловать…», может быть null)', 'value' => $portalWelcomeName ?? '(null)'];
        $rows[] = ['label' => '[сессия] learner_id', 'value' => (string) (int) session('learner_id', 0)];
        $rows[] = ['label' => '[сессия] learner_name', 'value' => $this->formatIdentityDebugValue(session('learner_name'))];

        $email = trim((string) $learner->email);
        $localRaw = strtolower((string) (explode('@', $email, 2)[0] ?? ''));
        $rows[] = ['label' => '[БД] learner.id', 'value' => (string) (int) $learner->id];
        $rows[] = ['label' => '[БД] learner.email', 'value' => $email];
        $rows[] = ['label' => '[вычисление] локальная часть email (до @), lower', 'value' => $localRaw !== '' ? $localRaw : '(пусто)'];

        if (is_array($probe) && $probe !== []) {
            ksort($probe);
            foreach ($probe as $k => $v) {
                $key = (string) $k;
                $rows[] = ['label' => '[OIDC claim] '.$key, 'value' => $this->formatIdentityDebugValue($v)];
            }
        } else {
            $rows[] = ['label' => '[OIDC] снимок claim’ов в сессии', 'value' => '(пусто) — выполните «Выйти» и войдите через SSO ещё раз: после входа сюда попадёт полный набор claim’ов'];
        }

        foreach ($request->session()->all() as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            if ($k === 'oidc_identity_probe_claims') {
                continue;
            }
            if (in_array($k, ['learner_id', 'learner_name'], true)) {
                continue;
            }
            if (! str_starts_with($k, 'learner') && ! str_starts_with($k, 'oidc')) {
                continue;
            }
            $rows[] = ['label' => '[сессия] '.$k, 'value' => $this->formatIdentityDebugValue($v)];
        }

        return $rows;
    }

    private function formatIdentityDebugValue(mixed $v): string
    {
        if ($v === null) {
            return '(null)';
        }
        if (is_string($v)) {
            return strlen($v) > 800 ? substr($v, 0, 800).'…' : $v;
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }
        if (is_array($v)) {
            $json = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

            return is_string($json) && strlen($json) > 1200 ? substr($json, 0, 1200).'…' : (string) $json;
        }

        return '(тип: '.get_debug_type($v).')';
    }
}
