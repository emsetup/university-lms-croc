<?php

namespace App\Http\Controllers;

use App\Models\CourseModule;
use App\Support\AdminCourseContentInspector;
use App\Support\CourseQuizBankLoader;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

final class AdminQuizController extends Controller
{
    public function index(Request $request): View
    {
        $adminKey = (string) $request->query('key', '');
        $rows = [];
        $courseId = (int) session('admin_course_id');
        $useDbModules = $courseId > 0 && Schema::hasTable('course_modules')
            && CourseModule::query()->where('course_id', $courseId)->exists();

        if ($useDbModules) {
            foreach (CourseModule::query()->where('course_id', $courseId)->orderBy('sort')->orderBy('id')->get() as $ent) {
                $m = $ent->effectiveContentIndex();
                $rows[] = [
                    'module' => $m,
                    'label' => $ent->title.' · пакет №'.$m,
                    'theory_quiz_count' => count(AdminCourseContentInspector::theoryQuizQuestions($m)),
                    'module_exam_count' => count(AdminCourseContentInspector::moduleExamQuestions($m)),
                ];
            }
        } else {
            foreach (range(1, 9) as $m) {
                $rows[] = [
                    'module' => $m,
                    'label' => null,
                    'theory_quiz_count' => count(AdminCourseContentInspector::theoryQuizQuestions($m)),
                    'module_exam_count' => count(AdminCourseContentInspector::moduleExamQuestions($m)),
                ];
            }
        }

        return view('admin.quiz-index', [
            'adminKey' => $adminKey,
            'rows' => $rows,
        ]);
    }

    public function editModule(Request $request, int $module, string $kind): View
    {
        $adminKey = (string) $request->query('key', '');
        abort_if($module < 1 || $module > 9, 404);
        abort_if(! in_array($kind, ['theory_quiz', 'module_exam'], true), 404);

        [$jsonPath, $phpPath] = $this->bankPaths($module, $kind);
        $questions = CourseQuizBankLoader::loadBankWithFallback($jsonPath, $phpPath);

        return view('admin.quiz-edit', [
            'adminKey' => $adminKey,
            'scope' => 'module',
            'module' => $module,
            'kind' => $kind,
            'title' => $kind === 'theory_quiz' ? 'Тест по теории' : 'Итоговый экзамен',
            'questions' => $questions,
        ]);
    }

    public function editFinal(Request $request): View
    {
        $adminKey = (string) $request->query('key', '');
        $jsonPath = $this->finalJsonPath();
        $questions = CourseQuizBankLoader::loadJsonBank($jsonPath);

        return view('admin.quiz-edit', [
            'adminKey' => $adminKey,
            'scope' => 'final',
            'module' => null,
            'kind' => 'final_lab',
            'title' => 'Финальная лабораторная (вопросы страницы)',
            'questions' => $questions,
        ]);
    }

    public function save(Request $request, int $module, string $kind): RedirectResponse|JsonResponse
    {
        $adminKey = (string) $request->query('key', '');
        abort_if($module < 1 || $module > 9, 404);
        abort_if(! in_array($kind, ['theory_quiz', 'module_exam'], true), 404);

        $items = $request->input('questions', []);
        if (! is_array($items)) {
            return $this->fail($request, 'Неверный формат данных (questions).');
        }
        $validated = $this->validateBank($items, $kind, true);
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
            ->route('admin.quiz.edit.module', ['module' => $module, 'kind' => $kind, 'key' => $adminKey])
            ->with('ok', 'Банк вопросов сохранён.');
    }

    public function saveFinal(Request $request): RedirectResponse|JsonResponse
    {
        $adminKey = (string) $request->query('key', '');
        $items = $request->input('questions', []);
        if (! is_array($items)) {
            return $this->fail($request, 'Неверный формат данных (questions).');
        }
        $validated = $this->validateBank($items, 'final_lab', false);
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
            ->route('admin.quiz.edit.final', ['key' => $adminKey])
            ->with('ok', 'Вопросы финальной страницы сохранены.');
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
     * @param  array<mixed>  $items
     * @return array{ok:bool,message:string,data:list<array<string,mixed>>}
     */
    private function validateBank(array $items, string $kind, bool $allowPoints): array
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

