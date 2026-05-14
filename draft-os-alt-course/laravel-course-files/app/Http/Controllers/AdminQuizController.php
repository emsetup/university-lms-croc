<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseQuizBank;
use App\Services\CourseContentService;
use App\Services\PortalStaffAccess;
use App\Support\AdminCourseContentInspector;
use App\Support\CourseQuizBankLoader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

final class AdminQuizController extends Controller
{
    public function __construct(
        private CourseContentService $content
    ) {}

    public function index(Request $request): View
    {
        $rows = [];
        $courseId = (int) session('admin_course_id');
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        $isAltCourse = $course && $course->isLegacyAltCourse();
        $useDbModules = $courseId > 0 && Schema::hasTable('course_modules')
            && CourseModule::query()->where('course_id', $courseId)->exists();

        if ($useDbModules) {
            foreach (CourseModule::query()->where('course_id', $courseId)->orderBy('sort')->orderBy('id')->get() as $ent) {
                $m = $ent->effectiveContentIndex();
                $dbTq = $this->dbQuestionCount($courseId, (int) $ent->id, 'theory_quiz');
                $dbEx = $this->dbQuestionCount($courseId, (int) $ent->id, 'module_exam');
                $legacyTq = count(AdminCourseContentInspector::theoryQuizQuestions($m));
                $legacyEx = count(AdminCourseContentInspector::moduleExamQuestions($m));
                $rows[] = [
                    'course_module_id' => (int) $ent->id,
                    'module' => $m,
                    'label' => $ent->title.' · пакет №'.$m,
                    'theory_quiz_count' => $dbTq > 0 ? $dbTq : ($isAltCourse ? $legacyTq : 0),
                    'module_exam_count' => $dbEx > 0 ? $dbEx : ($isAltCourse ? $legacyEx : 0),
                    'mode' => ($dbTq > 0 || $dbEx > 0) ? 'db' : ($isAltCourse ? 'legacy' : 'db'),
                ];
            }
        } elseif ($isAltCourse) {
            foreach (range(1, 9) as $m) {
                $rows[] = [
                    'course_module_id' => null,
                    'module' => $m,
                    'label' => null,
                    'theory_quiz_count' => count(AdminCourseContentInspector::theoryQuizQuestions($m)),
                    'module_exam_count' => count(AdminCourseContentInspector::moduleExamQuestions($m)),
                    'mode' => 'legacy',
                ];
            }
        }

        $ro = app(PortalStaffAccess::class)->isReadOnlyCourseContent();

        return view('admin.quiz-index', [
            'rows' => $rows,
            'selectedCourse' => $course,
            'isReadOnly' => $ro,
        ]);
    }

    public function editModule(Request $request, Course $adminCourse, int $module, string $kind): View
    {
        abort_if($module < 1 || $module > 9, 404);
        abort_if(! in_array($kind, ['theory_quiz', 'module_exam'], true), 404);

        $courseId = (int) session('admin_course_id', 0);
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        $isAlt = $course && $course->isLegacyAltCourse();

        $cm = $this->findCourseModuleByContentPackageIndex($courseId, $module);
        abort_unless($cm !== null, 404);

        $dbCount = Schema::hasTable('course_quiz_banks')
            ? $this->dbQuestionCount($courseId, (int) $cm->id, $kind)
            : 0;
        $useDbEditor = ! $isAlt || $dbCount > 0;

        if ($useDbEditor) {
            $bank = $this->content->quizBankFor($course, $cm, $kind);
            if (! $bank) {
                $defaults = $kind === 'theory_quiz'
                    ? ['pass_percent' => 70, 'time_limit_minutes' => 30, 'attempt_limit' => null, 'shuffle' => false, 'one_by_one' => true, 'breakdown_visible_minutes' => 15, 'penalties_json' => ['2' => 10]]
                    : ['pass_percent' => 70, 'time_limit_minutes' => 60, 'attempt_limit' => 2, 'shuffle' => false, 'one_by_one' => true, 'breakdown_visible_minutes' => 30, 'penalties_json' => ['2' => 10]];
                $bank = CourseQuizBank::query()->create([
                    'course_id' => $courseId,
                    'course_module_id' => (int) $cm->id,
                    'kind' => $kind,
                    ...$defaults,
                ]);
            }
            $questions = $this->content->questionsForBank($bank);
            $ro = app(PortalStaffAccess::class)->isReadOnlyCourseContent();

            return view('admin.quiz-edit-db', [
                'course' => $course,
                'courseModule' => $cm,
                'bank' => $bank,
                'kind' => $kind,
                'title' => $kind === 'theory_quiz' ? 'Тест по теории' : 'Итоговый экзамен',
                'questions' => $questions,
                'isReadOnly' => $ro,
            ]);
        }

        [$jsonPath, $phpPath] = $this->bankPaths($module, $kind);
        $questions = CourseQuizBankLoader::loadBankWithFallback($jsonPath, $phpPath);
        $ro = app(PortalStaffAccess::class)->isReadOnlyCourseContent();

        return view('admin.quiz-edit', [
            'scope' => 'module',
            'module' => $module,
            'kind' => $kind,
            'title' => $kind === 'theory_quiz' ? 'Тест по теории' : 'Итоговый экзамен',
            'questions' => $questions,
            'isReadOnly' => $ro,
        ]);
    }

    public function editFinal(Request $request, Course $adminCourse): View
    {
        $this->assertFinalLabEnabledForCurrentCourse();

        $courseId = (int) session('admin_course_id', 0);
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        $ro = app(PortalStaffAccess::class)->isReadOnlyCourseContent();

        if ($course && ! $course->isLegacyAltCourse()) {
            abort_unless(Schema::hasTable('course_quiz_banks'), 503);

            $bank = $this->content->quizBankFor($course, null, 'final_lab');
            if (! $bank) {
                $bank = CourseQuizBank::query()->create([
                    'course_id' => (int) $course->id,
                    'course_module_id' => null,
                    'kind' => 'final_lab',
                    'pass_percent' => 70,
                    'time_limit_minutes' => null,
                    'attempt_limit' => null,
                    'shuffle' => false,
                    'one_by_one' => true,
                    'breakdown_visible_minutes' => 15,
                    'penalties_json' => null,
                ]);
            }
            $questions = $this->content->questionsForBank($bank);

            return view('admin.quiz-edit-db', [
                'course' => $course,
                'courseModule' => null,
                'bank' => $bank,
                'kind' => 'final_lab',
                'title' => 'Финальная лабораторная (вопросы страницы)',
                'questions' => $questions,
                'isReadOnly' => $ro,
                'quizDbScope' => 'final',
                'quizSaveUrl' => route('admin.quiz.save.final', $this->adminCourseRouteParams()),
            ]);
        }

        $jsonPath = $this->finalJsonPath();
        $questions = CourseQuizBankLoader::loadJsonBank($jsonPath);

        return view('admin.quiz-edit', [
            'scope' => 'final',
            'module' => null,
            'kind' => 'final_lab',
            'title' => 'Финальная лабораторная (вопросы страницы)',
            'questions' => $questions,
            'isReadOnly' => $ro,
        ]);
    }

    public function save(Request $request, Course $adminCourse, int $module, string $kind): RedirectResponse|JsonResponse
    {
        abort_if($module < 1 || $module > 9, 404);
        abort_if(! in_array($kind, ['theory_quiz', 'module_exam'], true), 404);

        $courseId = (int) session('admin_course_id', 0);
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        $isAlt = $course && $course->isLegacyAltCourse();
        if (! $isAlt) {
            return $this->saveDbBank($request, $course, $module, $kind);
        }

        $cm = $this->findCourseModuleByContentPackageIndex($courseId, $module);
        if ($cm !== null && Schema::hasTable('course_quiz_banks')) {
            $bank = $this->content->quizBankFor($course, $cm, $kind);
            if ($bank !== null && $this->dbQuestionCount($courseId, (int) $cm->id, $kind) > 0) {
                return $this->saveDbBank($request, $course, $module, $kind);
            }
        }

        $items = $request->input('questions', []);
        if (! is_array($items)) {
            return $this->fail($request, 'Неверный формат данных (questions).');
        }
        $validated = $this->validateQuizBankFormat($items, $kind, true);
        if ($validated['ok'] !== true) {
            return $this->fail($request, $validated['message']);
        }
        $out = $validated['data'];

        [$jsonPath] = $this->bankPaths($module, $kind);
        $ok = $this->writeJsonAtomically($jsonPath, $out);
        if (! $ok) {
            return $this->fail($request, 'Не удалось сохранить JSON (проверьте права на каталог config/snippets).');
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return redirect()
            ->route('admin.quiz.edit.module', array_merge($this->adminCourseRouteParams(), ['module' => $module, 'kind' => $kind]))
            ->with('ok', 'Банк вопросов сохранён.');
    }

    /**
     * Пакет контента в URL — это {@see CourseModule::effectiveContentIndex()}, а не обязательно значение колонки content_source_index (она может быть NULL).
     */
    private function findCourseModuleByContentPackageIndex(int $courseId, int $contentIdx): ?CourseModule
    {
        if ($courseId < 1 || $contentIdx < 1) {
            return null;
        }

        return CourseModule::query()
            ->where('course_id', $courseId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->first(fn (CourseModule $cm) => $cm->effectiveContentIndex() === $contentIdx);
    }

    private function dbQuestionCount(int $courseId, int $courseModuleId, string $kind): int
    {
        if (! Schema::hasTable('course_quiz_banks') || ! Schema::hasTable('course_quiz_questions')) {
            return 0;
        }
        $bankId = CourseQuizBank::query()
            ->where('course_id', $courseId)
            ->where('course_module_id', $courseModuleId)
            ->where('kind', $kind)
            ->value('id');
        if (! $bankId) {
            return 0;
        }

        return (int) \App\Models\CourseQuizQuestion::query()->where('quiz_bank_id', (int) $bankId)->count();
    }

    private function saveDbBank(Request $request, ?Course $course, int $contentIdx, string $kind): RedirectResponse|JsonResponse
    {
        $courseId = (int) session('admin_course_id', 0);
        abort_unless($course && (int) $course->id === $courseId, 404);

        $cm = $this->findCourseModuleByContentPackageIndex($courseId, $contentIdx);
        abort_unless($cm !== null, 404);

        if (! Schema::hasTable('course_quiz_banks')) {
            return $this->fail($request, 'Таблицы банков вопросов не найдены. Выполните миграции.');
        }

        $bank = $this->content->quizBankFor($course, $cm, $kind);
        if (! $bank) {
            $defaults = $kind === 'theory_quiz'
                ? ['pass_percent' => 70, 'time_limit_minutes' => 30, 'attempt_limit' => null, 'shuffle' => false, 'one_by_one' => true, 'breakdown_visible_minutes' => 15, 'penalties_json' => ['2' => 10]]
                : ['pass_percent' => 70, 'time_limit_minutes' => 60, 'attempt_limit' => 2, 'shuffle' => false, 'one_by_one' => true, 'breakdown_visible_minutes' => 30, 'penalties_json' => ['2' => 10]];
            $bank = CourseQuizBank::query()->create([
                'course_id' => $courseId,
                'course_module_id' => (int) $cm->id,
                'kind' => $kind,
                ...$defaults,
            ]);
        }

        $data = $request->validate([
            'pass_percent' => 'required|integer|min:1|max:100',
            'time_limit_minutes' => 'nullable|integer|min:1|max:600',
            'attempt_limit' => 'nullable|integer|min:1|max:50',
            'shuffle' => 'sometimes|boolean',
            'one_by_one' => 'sometimes|boolean',
            'breakdown_visible_minutes' => 'nullable|integer|min:0|max:10080',
            'penalty_attempt_2' => 'nullable|integer|min:0|max:100',
            'penalty_attempt_3' => 'nullable|integer|min:0|max:100',
            'penalty_attempt_4' => 'nullable|integer|min:0|max:100',
            'bank_json' => 'required|string|max:8000000',
        ]);

        $penalties = [];
        foreach ([2, 3, 4] as $n) {
            $k = 'penalty_attempt_'.$n;
            if (isset($data[$k]) && $data[$k] !== null && $data[$k] !== '') {
                $penalties[(string) $n] = (int) $data[$k];
            }
        }

        $bank->pass_percent = (int) $data['pass_percent'];
        $bank->time_limit_minutes = isset($data['time_limit_minutes']) ? (int) $data['time_limit_minutes'] : null;
        $bank->attempt_limit = isset($data['attempt_limit']) ? (int) $data['attempt_limit'] : null;
        $bank->shuffle = $request->boolean('shuffle');
        $bank->one_by_one = $request->boolean('one_by_one');
        $bank->breakdown_visible_minutes = isset($data['breakdown_visible_minutes']) ? (int) $data['breakdown_visible_minutes'] : $bank->breakdown_visible_minutes;
        $bank->penalties_json = $penalties !== [] ? $penalties : null;
        $bank->save();

        $rawJson = (string) $data['bank_json'];
        $decoded = json_decode($rawJson, true);
        if (! is_array($decoded)) {
            return $this->fail($request, 'JSON не распарсился. Должен быть массив вопросов.');
        }

        $validated = $this->validateQuizBankFormat($decoded, $kind, true);
        if ($validated['ok'] !== true) {
            return $this->fail($request, $validated['message']);
        }
        $items = $validated['data'];

        $this->persistQuizBankItems($bank, $items);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('admin.quiz.edit.module', array_merge($this->adminCourseRouteParams(), ['module' => $contentIdx, 'kind' => $kind]))
            ->with('ok', 'Банк вопросов сохранён в БД.');
    }

    private function saveDbFinalBank(Request $request, Course $course): RedirectResponse|JsonResponse
    {
        $courseId = (int) session('admin_course_id', 0);
        abort_unless((int) $course->id === $courseId, 404);

        if (! Schema::hasTable('course_quiz_banks')) {
            return $this->fail($request, 'Таблицы банков вопросов не найдены. Выполните миграции.');
        }

        $bank = $this->content->quizBankFor($course, null, 'final_lab');
        if (! $bank) {
            $bank = CourseQuizBank::query()->create([
                'course_id' => $courseId,
                'course_module_id' => null,
                'kind' => 'final_lab',
                'pass_percent' => 70,
                'time_limit_minutes' => null,
                'attempt_limit' => null,
                'shuffle' => false,
                'one_by_one' => true,
                'breakdown_visible_minutes' => 15,
                'penalties_json' => null,
            ]);
        }

        $data = $request->validate([
            'pass_percent' => 'required|integer|min:1|max:100',
            'time_limit_minutes' => 'nullable|integer|min:1|max:600',
            'attempt_limit' => 'nullable|integer|min:1|max:50',
            'shuffle' => 'sometimes|boolean',
            'one_by_one' => 'sometimes|boolean',
            'breakdown_visible_minutes' => 'nullable|integer|min:0|max:10080',
            'penalty_attempt_2' => 'nullable|integer|min:0|max:100',
            'penalty_attempt_3' => 'nullable|integer|min:0|max:100',
            'penalty_attempt_4' => 'nullable|integer|min:0|max:100',
            'bank_json' => 'required|string|max:8000000',
        ]);

        $penalties = [];
        foreach ([2, 3, 4] as $n) {
            $k = 'penalty_attempt_'.$n;
            if (isset($data[$k]) && $data[$k] !== null && $data[$k] !== '') {
                $penalties[(string) $n] = (int) $data[$k];
            }
        }

        $bank->pass_percent = (int) $data['pass_percent'];
        $bank->time_limit_minutes = isset($data['time_limit_minutes']) ? (int) $data['time_limit_minutes'] : null;
        $bank->attempt_limit = isset($data['attempt_limit']) ? (int) $data['attempt_limit'] : null;
        $bank->shuffle = $request->boolean('shuffle');
        $bank->one_by_one = $request->boolean('one_by_one');
        $bank->breakdown_visible_minutes = isset($data['breakdown_visible_minutes']) ? (int) $data['breakdown_visible_minutes'] : $bank->breakdown_visible_minutes;
        $bank->penalties_json = $penalties !== [] ? $penalties : null;
        $bank->save();

        $rawJson = (string) $data['bank_json'];
        $decoded = json_decode($rawJson, true);
        if (! is_array($decoded)) {
            return $this->fail($request, 'JSON не распарсился. Должен быть массив вопросов.');
        }

        $validated = $this->validateQuizBankFormat($decoded, 'final_lab', false);
        if ($validated['ok'] !== true) {
            return $this->fail($request, $validated['message']);
        }
        $items = $validated['data'];

        $this->persistQuizBankItems($bank, $items);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('admin.quiz.edit.final', $this->adminCourseRouteParams())
            ->with('ok', 'Вопросы финальной страницы сохранены в БД.');
    }

    public function saveFinal(Request $request, Course $adminCourse): RedirectResponse|JsonResponse
    {
        $this->assertFinalLabEnabledForCurrentCourse();

        $courseId = (int) session('admin_course_id', 0);
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        if ($course && ! $course->isLegacyAltCourse()) {
            return $this->saveDbFinalBank($request, $course);
        }

        $items = $request->input('questions', []);
        if (! is_array($items)) {
            return $this->fail($request, 'Неверный формат данных (questions).');
        }
        $validated = $this->validateQuizBankFormat($items, 'final_lab', false);
        if ($validated['ok'] !== true) {
            return $this->fail($request, $validated['message']);
        }
        $out = $validated['data'];
        $ok = $this->writeJsonAtomically($this->finalJsonPath(), $out);
        if (! $ok) {
            return $this->fail($request, 'Не удалось сохранить JSON (проверьте права на каталог config/snippets).');
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return redirect()
            ->route('admin.quiz.edit.final', $this->adminCourseRouteParams())
            ->with('ok', 'Вопросы финальной страницы сохранены.');
    }

    private function assertFinalLabEnabledForCurrentCourse(): void
    {
        $courseId = (int) session('admin_course_id', 0);
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        if ($course && Schema::hasColumn('courses', 'final_lab_enabled') && ! $course->final_lab_enabled) {
            abort(404, 'Финальная лабораторная отключена для этого курса.');
        }
    }

    /**
     * @return array{0:string,1:?string}
     */
    private function bankPaths(int $module, string $kind): array
    {
        $base = sprintf('module_%02d_%s_questions', $module, $kind);
        $jsonPath = config_path('snippets/'.$base.'.json');
        $phpPath = config_path('snippets/'.$base.'.php');

        return [$jsonPath, is_file($phpPath) ? $phpPath : null];
    }

    private function finalJsonPath(): string
    {
        return config_path('snippets/final_lab_questions.json');
    }

    /**
     * Полная перезапись вопросов банка (в транзакции).
     *
     * @param  list<array<string, mixed>>  $items  результат validateQuizBankFormat()['data']
     */
    public function persistQuizBankItems(CourseQuizBank $bank, array $items): void
    {
        DB::transaction(function () use ($bank, $items): void {
            $qIds = \App\Models\CourseQuizQuestion::query()
                ->where('quiz_bank_id', (int) $bank->id)
                ->pluck('id')->map(fn ($v) => (int) $v)->all();
            if ($qIds !== []) {
                \App\Models\CourseQuizOption::query()->whereIn('question_id', $qIds)->delete();
                \App\Models\CourseQuizCorrectAnswer::query()->whereIn('question_id', $qIds)->delete();
                \App\Models\CourseQuizMatchPair::query()->whereIn('question_id', $qIds)->delete();
                \App\Models\CourseQuizQuestion::query()->whereIn('id', $qIds)->delete();
            }

            foreach (array_values($items) as $i => $q) {
                $type = ! empty($q['match_drag'])
                    ? 'match_drag'
                    : (is_array($q['c'] ?? null) ? 'multi' : 'single');

                /** @var \App\Models\CourseQuizQuestion $qq */
                $qq = \App\Models\CourseQuizQuestion::query()->create([
                    'quiz_bank_id' => (int) $bank->id,
                    'sort' => ($i + 1) * 10,
                    'question_text' => (string) ($q['q'] ?? ''),
                    'type' => $type,
                    'points' => isset($q['points']) ? (int) $q['points'] : null,
                ]);

                if ($type === 'match_drag') {
                    $left = is_array($q['left'] ?? null) ? $q['left'] : [];
                    $right = is_array($q['right'] ?? null) ? $q['right'] : [];
                    foreach (array_values($left) as $pi => $lv) {
                        $rv = (string) ($right[$pi] ?? '');
                        \App\Models\CourseQuizMatchPair::query()->create([
                            'question_id' => (int) $qq->id,
                            'sort' => ($pi + 1) * 10,
                            'left_text' => (string) $lv,
                            'right_text' => $rv,
                        ]);
                    }
                    continue;
                }

                $opts = is_array($q['a'] ?? null) ? $q['a'] : [];
                $optIds = [];
                foreach (array_values($opts) as $oi => $text) {
                    $o = \App\Models\CourseQuizOption::query()->create([
                        'question_id' => (int) $qq->id,
                        'sort' => ($oi + 1) * 10,
                        'option_text' => (string) $text,
                    ]);
                    $optIds[$oi] = (int) $o->id;
                }

                $corr = $q['c'] ?? null;
                $idxs = is_array($corr) ? $corr : [(int) $corr];
                foreach ($idxs as $idx) {
                    $idx = (int) $idx;
                    if (! isset($optIds[$idx])) {
                        continue;
                    }
                    \App\Models\CourseQuizCorrectAnswer::query()->create([
                        'question_id' => (int) $qq->id,
                        'option_id' => (int) $optIds[$idx],
                    ]);
                }
            }
        });
    }

    /**
     * @param  array<mixed>  $items
     * @return array{ok:bool,message:string,data:list<array<string,mixed>>}
     */
    public function validateQuizBankFormat(array $items, string $kind, bool $allowPoints): array
    {
        $out = [];
        if (count($items) > 250) {
            return ['ok' => false, 'message' => 'Слишком много вопросов (максимум 250).', 'data' => []];
        }
        foreach (array_values($items) as $i => $q) {
            if (! is_array($q)) {
                return ['ok' => false, 'message' => "Вопрос #".($i + 1).": неверный формат.", 'data' => []];
            }
            $qq = [];
            $qq['q'] = trim((string) ($q['q'] ?? ''));
            if ($qq['q'] === '') {
                return ['ok' => false, 'message' => "Вопрос #".($i + 1).": пустой текст.", 'data' => []];
            }

            $isMatch = ! empty($q['match_drag']);
            if ($isMatch) {
                $qq['match_drag'] = true;
                $left = is_array($q['left'] ?? null) ? array_values($q['left']) : [];
                $right = is_array($q['right'] ?? null) ? array_values($q['right']) : [];
                if (count($left) < 1 || count($right) < 1 || count($left) !== count($right)) {
                    return ['ok' => false, 'message' => "Вопрос #".($i + 1).": сопоставление должно иметь одинаковое число строк слева/справа.", 'data' => []];
                }
                if (count($left) > 24) {
                    return ['ok' => false, 'message' => "Вопрос #".($i + 1).": слишком много пар (максимум 24).", 'data' => []];
                }
                $qq['left'] = array_map(static fn ($v) => trim((string) $v), $left);
                $qq['right'] = array_map(static fn ($v) => trim((string) $v), $right);
                foreach ($qq['left'] as $v) {
                    if ($v === '') {
                        return ['ok' => false, 'message' => "Вопрос #".($i + 1).": пустая строка слева.", 'data' => []];
                    }
                }
                foreach ($qq['right'] as $v) {
                    if ($v === '') {
                        return ['ok' => false, 'message' => "Вопрос #".($i + 1).": пустая строка справа.", 'data' => []];
                    }
                }
            } else {
                $a = is_array($q['a'] ?? null) ? array_values($q['a']) : [];
                $a = array_map(static fn ($v) => (string) $v, $a);
                $a = array_values(array_map('trim', $a));
                $a = array_values(array_filter($a, static fn ($v) => $v !== ''));
                if (count($a) < 2) {
                    return ['ok' => false, 'message' => "Вопрос #".($i + 1).": нужно минимум 2 варианта ответа.", 'data' => []];
                }
                if (count($a) > 12) {
                    return ['ok' => false, 'message' => "Вопрос #".($i + 1).": слишком много вариантов (максимум 12).", 'data' => []];
                }
                $qq['a'] = $a;

                $c = $q['c'] ?? null;
                if (is_array($c)) {
                    $idx = [];
                    foreach ($c as $v) {
                        if (! is_numeric($v)) {
                            return ['ok' => false, 'message' => "Вопрос #".($i + 1).": неверный индекс в c.", 'data' => []];
                        }
                        $iv = (int) $v;
                        if ($iv < 0 || $iv >= count($a)) {
                            return ['ok' => false, 'message' => "Вопрос #".($i + 1).": индекс в c вне диапазона.", 'data' => []];
                        }
                        $idx[$iv] = true;
                    }
                    if (count($idx) < 1) {
                        return ['ok' => false, 'message' => "Вопрос #".($i + 1).": для multi выберите хотя бы один правильный вариант.", 'data' => []];
                    }
                    $arr = array_keys($idx);
                    sort($arr);
                    $qq['c'] = $arr;
                } else {
                    if (! is_numeric($c)) {
                        return ['ok' => false, 'message' => "Вопрос #".($i + 1).": c должен быть числом (индекс правильного ответа).", 'data' => []];
                    }
                    $iv = (int) $c;
                    if ($iv < 0 || $iv >= count($a)) {
                        return ['ok' => false, 'message' => "Вопрос #".($i + 1).": индекс c вне диапазона.", 'data' => []];
                    }
                    $qq['c'] = $iv;
                }
            }

            if ($allowPoints) {
                if (isset($q['points']) && $q['points'] !== null && $q['points'] !== '') {
                    if (! is_numeric($q['points'])) {
                        return ['ok' => false, 'message' => "Вопрос #".($i + 1).": points должен быть числом.", 'data' => []];
                    }
                    $p = (int) $q['points'];
                    if ($p < 1 || $p > 100) {
                        return ['ok' => false, 'message' => "Вопрос #".($i + 1).": points должен быть 1..100.", 'data' => []];
                    }
                    $qq['points'] = $p;
                }
            }

            $out[] = $qq;
        }

        return ['ok' => true, 'message' => 'ok', 'data' => $out];
    }

    private function fail(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], 422);
        }

        return back()->with('err', $message);
    }

    /**
     * @param  list<array<string, mixed>>  $data
     */
    private function writeJsonAtomically(string $path, array $data): bool
    {
        $dir = dirname($path);
        if (! is_dir($dir) || ! is_writable($dir)) {
            return false;
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            return false;
        }
        $tmp = $path.'.tmp.'.bin2hex(random_bytes(6));
        $bytes = @file_put_contents($tmp, $json."\n");
        if (! is_int($bytes) || $bytes <= 0) {
            @unlink($tmp);
            return false;
        }

        return @rename($tmp, $path);
    }
}

