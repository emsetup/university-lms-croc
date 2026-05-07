<?php

namespace App\Http\Controllers;

use App\Models\FinalLabResult;
use App\Models\Learner;
use App\Models\PracticeSession;
use App\Services\CourseScoringService;
use App\Services\PracticeLabService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class FinalLabController extends Controller
{
    /** Ключ Docker-образа и lab-daemon (как раньше модуль 10). */
    private const FINAL_LAB_DAEMON_MODULE_KEY = 10;

    private const FINAL_LAB_SESSION_MODULE_ID = PracticeSession::FINAL_LAB_SESSION_MODULE_ID;
    private const FINAL_LAB_TIME_LIMIT_MINUTES = 90;

    public function __construct(
        private CourseScoringService $scoring
    ) {}

    public function show(): View|RedirectResponse
    {
        $learner = Learner::findOrFail(session('learner_id'));
        if (! $this->scoring->allModulesComplete($learner)) {
            return redirect()->route('dashboard')->with('err', 'Финальная лабораторная доступна после прохождения всех модулей.');
        }
        if (! Schema::hasTable('practice_sessions')) {
            return view('final-lab', [
                'result' => $learner->finalLabResult,
                'practiceSession' => null,
                'attemptsLeft' => 2,
                'labConfigured' => false,
                'labImage' => null,
                'labEnabled' => false,
                'warningOnly' => true,
            ]);
        }
        $lab = PracticeLabService::make();
        $result = $this->resultModel($learner);
        $courseId = (int) session('course_id', 0);
        $practiceSession = PracticeSession::query()
            ->where('learner_id', $learner->id)
            ->where('course_id', $courseId)
            ->where('module_id', self::FINAL_LAB_SESSION_MODULE_ID)
            ->first();

        return view('final-lab', [
            'result' => $result,
            'practiceSession' => $practiceSession,
            'attemptsLeft' => max(0, 2 - (int) $result->attempts),
            'labConfigured' => $lab->isConfigured(),
            'labImage' => $lab->imageForModule(self::FINAL_LAB_DAEMON_MODULE_KEY),
            'labEnabled' => (bool) config('practice_lab.enabled'),
            'warningOnly' => false,
        ]);
    }

    public function startLab(): RedirectResponse
    {
        $learner = Learner::findOrFail(session('learner_id'));
        if ($r = $this->guardLearnerAccess($learner)) {
            return $r;
        }
        $result = $this->resultModel($learner);
        if ((int) $result->attempts >= 2) {
            return redirect()->route('final-lab')->with('err', 'Попытки финальной лабораторной исчерпаны (2/2).');
        }
        $lab = PracticeLabService::make();
        try {
            $courseId = (int) session('course_id', 0);
            $out = $lab->startLab($learner, $courseId, self::FINAL_LAB_SESSION_MODULE_ID, self::FINAL_LAB_DAEMON_MODULE_KEY);
        } catch (Throwable $e) {
            return redirect()->route('final-lab')->with('err', $e->getMessage());
        }
        if (! empty($out['message'])) {
            return redirect()->route('final-lab')->with('err', (string) $out['message']);
        }
        $session = $out['session'] ?? null;
        if ($session instanceof PracticeSession) {
            $session->expires_at = now()->addMinutes(self::FINAL_LAB_TIME_LIMIT_MINUTES);
            $session->save();
        }

        return redirect()->route('final-lab')->with('ok', 'Контейнер финальной лабораторной запущен. Лимит времени — 90 минут.');
    }

    public function checkLab(): RedirectResponse
    {
        $learner = Learner::findOrFail(session('learner_id'));
        if ($r = $this->guardLearnerAccess($learner)) {
            return $r;
        }
        try {
            $courseId = (int) session('course_id', 0);
            $out = PracticeLabService::make()->runCheck($learner, $courseId, self::FINAL_LAB_SESSION_MODULE_ID);
        } catch (Throwable $e) {
            return redirect()->route('final-lab')->with('err', $e->getMessage());
        }
        if (! empty($out['message'])) {
            return redirect()->route('final-lab')->with('err', (string) $out['message']);
        }

        return redirect()->route('final-lab')->with('ok', 'Проверка завершена. Изучите лог и зафиксируйте результат.');
    }

    public function acceptLab(): RedirectResponse
    {
        $learner = Learner::findOrFail(session('learner_id'));
        if ($r = $this->guardLearnerAccess($learner)) {
            return $r;
        }
        $result = $this->resultModel($learner);
        $attempt = (int) $result->attempts + 1;
        if ($attempt > 2) {
            return redirect()->route('final-lab')->with('err', 'Попытки финальной лабораторной исчерпаны.');
        }
        $courseId = (int) session('course_id', 0);
        $session = PracticeSession::query()
            ->where('learner_id', $learner->id)
            ->where('course_id', $courseId)
            ->where('module_id', self::FINAL_LAB_SESSION_MODULE_ID)
            ->first();
        if (! $session || $session->last_check_score === null) {
            return redirect()->route('final-lab')->with('err', 'Сначала выполните проверку результата.');
        }
        $raw = (int) $session->last_check_score;
        $score = $attempt >= 2 ? max(0, $raw - 10) : $raw;

        $result->attempts = $attempt;
        $result->best_score = max((int) $result->best_score, $score);
        if ($score >= CourseScoringService::PASS_THRESHOLD) {
            $result->passed = true;
            $result->completed_at = now();
        }
        $result->save();

        $suffix = $attempt >= 2 ? ' (вторая попытка: штраф −10 п.п.)' : '';
        $msg = 'Результат финальной лабораторной зафиксирован: '.$score.'%'.$suffix.'.';

        return redirect()->route('final-lab')->with($score >= CourseScoringService::PASS_THRESHOLD ? 'ok' : 'err', $msg);
    }

    public function finishLab(): RedirectResponse
    {
        $learner = Learner::findOrFail(session('learner_id'));
        if ($r = $this->guardLearnerAccess($learner)) {
            return $r;
        }
        $courseId = (int) session('course_id', 0);
        PracticeLabService::make()->destroyLab($learner, $courseId, self::FINAL_LAB_SESSION_MODULE_ID);

        return redirect()->route('final-lab')->with('ok', 'Контейнер финальной лабораторной удалён.');
    }

    private function resultModel(Learner $learner): FinalLabResult
    {
        $courseId = (int) session('course_id', 0);
        return $learner->finalLabResult()->firstOrCreate(
            ['learner_id' => $learner->id, 'course_id' => $courseId > 0 ? $courseId : null],
            ['attempts' => 0, 'passed' => false, 'best_score' => 0]
        );
    }

    private function guardLearnerAccess(Learner $learner): ?RedirectResponse
    {
        if (! $this->scoring->allModulesComplete($learner)) {
            return redirect()->route('dashboard')->with('err', 'Финальная лабораторная доступна после прохождения всех модулей.');
        }
        if (! Schema::hasTable('practice_sessions')) {
            return redirect()->route('final-lab')->with('err', 'Не выполнены миграции practice_sessions. Запустите php artisan migrate.');
        }

        return null;
    }
}
