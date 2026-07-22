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

    public function editModule(Request $request, Course $adminCourse, int $module, string $kind): View
    {
        abort_if($module < 1 || $module > 9, 404);
        abort_if(! in_array($kind, ['theory_quiz', 'module_exam'], true), 404);

        $courseId = (int) session('admin_course_id', 0);
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        $isAlt = $course && $course->isLegacyAltCourse();

        $cm = $this->findCourseModuleByContentPackageIndex($courseId, $module);
        abort_unless($cm !== null, 404);
        app(PortalStaffAccess::class)->assertCanEditModuleQuiz((int) $cm->id, $kind);

        $dbCount = Schema::hasTable('course_quiz_banks')
            ? AdminCourseContentInspector::dbQuestionCountForModule($courseId, (int) $cm->id, $kind)
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
            $gate = app(PortalStaffAccess::class);
            $ro = $gate->isReadOnlyCourseContent()
                || ! $gate->canEditModuleQuiz((int) $cm->id, $kind);

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
        $gate = app(PortalStaffAccess::class);
        $ro = $gate->isReadOnlyCourseContent()
            || ! $gate->canEditModuleQuiz((int) $cm->id, $kind);

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
            if ($bank !== null && AdminCourseContentInspector::dbQuestionCountForModule($courseId, (int) $cm->id, $kind) > 0) {
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

    private function saveDbBank(Request $request, ?Course $course, int $contentIdx, string $kind): RedirectResponse|JsonResponse
    {
        $courseId = (int) session('admin_course_id', 0);
        abort_unless($course && (int) $course->id === $courseId, 404);

        $cm = $this->findCourseModuleByContentPackageIndex($courseId, $contentIdx);
        abort_unless($cm !== null, 404);
        app(PortalStaffAccess::class)->assertCanEditModuleQuiz((int) $cm->id, $kind);

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
     * Сохранение вопросов банка: обновление по id (без wipe), чтобы не сносить ответы опросов
     * (FK course_survey_answers.question_id ON DELETE CASCADE).
     *
     * @param  list<array<string, mixed>>  $items  результат validateQuizBankFormat()['data']
     */
    public function persistQuizBankItems(CourseQuizBank $bank, array $items): void
    {
        DB::transaction(function () use ($bank, $items): void {
            $bankId = (int) $bank->id;
            $existing = \App\Models\CourseQuizQuestion::query()
                ->where('quiz_bank_id', $bankId)
                ->get()
                ->keyBy(fn ($q) => (int) $q->id);
            $existingOrdered = $existing->sortBy([
                fn ($q) => (int) $q->sort,
                fn ($q) => (int) $q->id,
            ])->values();
            $positionalFallback = $existingOrdered->count() === count($items);

            $keepIds = [];
            foreach (array_values($items) as $i => $q) {
                $type = ! empty($q['open_text'])
                    ? 'open_text'
                    : (! empty($q['multi_other'])
                        ? 'multi_other'
                        : (! empty($q['match_drag'])
                            ? 'match_drag'
                            : (is_array($q['c'] ?? null) ? 'multi' : 'single')));

                $payload = [
                    'quiz_bank_id' => $bankId,
                    'sort' => ($i + 1) * 10,
                    'question_text' => (string) ($q['q'] ?? ''),
                    'type' => $type,
                    'points' => isset($q['points']) ? (int) $q['points'] : null,
                ];
                if ($type === 'open_text' || $type === 'multi_other') {
                    $settings = [];
                    if (! empty($q['placeholder'])) {
                        $settings['placeholder'] = trim((string) $q['placeholder']);
                    }
                    if (! empty($q['max_length']) && is_numeric($q['max_length'])) {
                        $settings['max_length'] = (int) $q['max_length'];
                    }
                    $payload['settings_json'] = $settings !== [] ? $settings : null;
                } else {
                    $payload['settings_json'] = null;
                }

                $incomingId = isset($q['id']) ? (int) $q['id'] : 0;
                /** @var \App\Models\CourseQuizQuestion|null $qq */
                $qq = ($incomingId > 0 && $existing->has($incomingId))
                    ? $existing->get($incomingId)
                    : null;
                // Старый клиент без id: при том же числе вопросов обновляем по порядку — иначе wipe сносит ответы.
                if ($qq === null && $positionalFallback) {
                    $cand = $existingOrdered->get($i);
                    if ($cand instanceof \App\Models\CourseQuizQuestion
                        && ! in_array((int) $cand->id, $keepIds, true)) {
                        $qq = $cand;
                    }
                }

                if ($qq !== null) {
                    $qq->fill($payload);
                    $qq->save();
                    \App\Models\CourseQuizOption::query()->where('question_id', (int) $qq->id)->delete();
                    \App\Models\CourseQuizCorrectAnswer::query()->where('question_id', (int) $qq->id)->delete();
                    \App\Models\CourseQuizMatchPair::query()->where('question_id', (int) $qq->id)->delete();
                } else {
                    $qq = \App\Models\CourseQuizQuestion::query()->create($payload);
                }

                $keepIds[] = (int) $qq->id;
                $this->persistQuestionChildren($qq, $type, $q);
            }

            $toDelete = [];
            foreach ($existing->keys() as $oldId) {
                $oldId = (int) $oldId;
                if (! in_array($oldId, $keepIds, true)) {
                    $toDelete[] = $oldId;
                }
            }
            if ($toDelete === []) {
                return;
            }

            $blocked = [];
            if (Schema::hasTable('course_survey_answers')) {
                $blocked = \App\Models\CourseSurveyAnswer::query()
                    ->whereIn('question_id', $toDelete)
                    ->distinct()
                    ->pluck('question_id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
            }
            if ($blocked !== []) {
                throw new \InvalidArgumentException(
                    'Нельзя удалить вопросы, на которые уже есть ответы опроса (id: '
                    .implode(', ', $blocked)
                    .'). Экспортируйте ответы или оставьте эти вопросы в банке.'
                );
            }

            \App\Models\CourseQuizOption::query()->whereIn('question_id', $toDelete)->delete();
            \App\Models\CourseQuizCorrectAnswer::query()->whereIn('question_id', $toDelete)->delete();
            \App\Models\CourseQuizMatchPair::query()->whereIn('question_id', $toDelete)->delete();
            \App\Models\CourseQuizQuestion::query()->whereIn('id', $toDelete)->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $q
     */
    private function persistQuestionChildren(\App\Models\CourseQuizQuestion $qq, string $type, array $q): void
    {
        if ($type === 'open_text') {
            return;
        }

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

            return;
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

        if ($type === 'multi_other') {
            return;
        }

        $corr = $q['c'] ?? null;
        if ($corr !== null && $corr !== []) {
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
            if (isset($q['id']) && is_numeric($q['id']) && (int) $q['id'] > 0) {
                $qq['id'] = (int) $q['id'];
            }
            $qq['q'] = trim((string) ($q['q'] ?? ''));
            if ($qq['q'] === '') {
                return ['ok' => false, 'message' => "Вопрос #".($i + 1).": пустой текст.", 'data' => []];
            }

            $isOpen = ! empty($q['open_text']);
            if ($isOpen) {
                if ($kind !== 'survey') {
                    return ['ok' => false, 'message' => "Вопрос #".($i + 1).": открытый ответ только для опросов.", 'data' => []];
                }
                $qq['open_text'] = true;
                if (! empty($q['placeholder'])) {
                    $qq['placeholder'] = trim((string) $q['placeholder']);
                }
                if (! empty($q['max_length']) && is_numeric($q['max_length'])) {
                    $ml = (int) $q['max_length'];
                    if ($ml < 1 || $ml > 50000) {
                        return ['ok' => false, 'message' => "Вопрос #".($i + 1).": max_length 1..50000.", 'data' => []];
                    }
                    $qq['max_length'] = $ml;
                }
                $out[] = $qq;
                continue;
            }
            $isMultiOther = ! empty($q['multi_other']);
            if ($isMultiOther) {
                if ($kind !== 'survey') {
                    return ['ok' => false, 'message' => "Вопрос #".($i + 1).": смешанный ответ только для опросов.", 'data' => []];
                }
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
                $qq['multi_other'] = true;
                $qq['a'] = $a;
                $qq['c'] = [];
                if (! empty($q['placeholder'])) {
                    $qq['placeholder'] = trim((string) $q['placeholder']);
                }
                if (! empty($q['max_length']) && is_numeric($q['max_length'])) {
                    $ml = (int) $q['max_length'];
                    if ($ml < 1 || $ml > 50000) {
                        return ['ok' => false, 'message' => "Вопрос #".($i + 1).": max_length 1..50000.", 'data' => []];
                    }
                    $qq['max_length'] = $ml;
                }
                $out[] = $qq;
                continue;
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
                if ($kind === 'survey') {
                    if (is_array($c)) {
                        $qq['c'] = [];
                    }
                } elseif (is_array($c)) {
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

