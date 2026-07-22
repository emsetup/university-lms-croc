<?php

namespace App\Services;

use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Models\CourseSurveySubmission;
use Illuminate\Support\Facades\Schema;

/**
 * Каталог опросов курса для раздела админки «Опросы».
 */
final class CourseSurveyCatalogService
{
    public function __construct(
        private CourseModuleService $courseModules,
        private CourseSectionService $sections,
        private SurveyQuickLinkService $quickLinks,
    ) {}

    public function hasSurveys(int $courseId): bool
    {
        return $courseId > 0
            && Schema::hasTable('course_sections')
            && $this->surveySectionsQuery($courseId)->exists();
    }

    /**
     * @return list<array{
     *   section_id:int,
     *   section_title:string,
     *   module_id:int,
     *   module_sequence:int,
     *   module_title:string,
     *   response_count:int,
     *   question_count:int,
     *   anonymous:bool,
     *   quick_link_url:?string
     * }>
     */
    public function surveysForCourse(int $courseId): array
    {
        if ($courseId < 1 || ! Schema::hasTable('course_sections')) {
            return [];
        }

        $sections = $this->surveySectionsQuery($courseId)
            ->orderBy('course_module_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        if ($sections->isEmpty()) {
            return [];
        }

        $moduleSeq = [];
        $seq = 0;
        foreach ($this->courseModules->orderedModulesForCourse($courseId) as $mod) {
            $seq++;
            $moduleSeq[(int) $mod->id] = $seq;
        }

        $sectionIds = $sections->pluck('id')->map(fn ($id) => (int) $id)->all();
        $counts = Schema::hasTable('course_survey_submissions')
            ? CourseSurveySubmission::query()
                ->whereIn('course_section_id', $sectionIds)
                ->whereHas('answers')
                ->selectRaw('course_section_id, COUNT(*) as c')
                ->groupBy('course_section_id')
                ->pluck('c', 'course_section_id')
                ->all()
            : [];

        $modulesById = CourseModule::query()
            ->whereIn('id', $sections->pluck('course_module_id')->unique()->filter()->all())
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($sections as $sec) {
            $mid = (int) $sec->course_module_id;
            $mod = $modulesById->get($mid);
            $settings = $this->sections->mergedSettings($sec);
            $bank = app(SurveyResponseService::class)->bankForSection((int) $sec->id);
            $qCount = $bank
                ? (int) \App\Models\CourseQuizQuestion::query()->where('quiz_bank_id', (int) $bank->id)->count()
                : 0;

            $out[] = [
                'section_id' => (int) $sec->id,
                'section_title' => (string) $sec->title,
                'module_id' => $mid,
                'module_sequence' => (int) ($moduleSeq[$mid] ?? 0),
                'module_title' => (string) ($mod?->title ?? 'Модуль'),
                'response_count' => (int) ($counts[(int) $sec->id] ?? 0),
                'question_count' => $qCount,
                'anonymous' => (bool) ($settings['anonymous'] ?? false),
                'quick_link_url' => $this->quickLinks->activeUrlForSection((int) $sec->id),
            ];
        }

        usort($out, static function (array $a, array $b): int {
            $cmp = $a['module_sequence'] <=> $b['module_sequence'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return $a['section_id'] <=> $b['section_id'];
        });

        return $out;
    }

    public function findSurveySection(int $courseId, int $sectionId): ?CourseSection
    {
        if ($courseId < 1 || $sectionId < 1) {
            return null;
        }

        return $this->surveySectionsQuery($courseId)
            ->whereKey($sectionId)
            ->first();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<CourseSection> */
    private function surveySectionsQuery(int $courseId)
    {
        return CourseSection::query()
            ->where('course_id', $courseId)
            ->where('type', CourseSection::TYPE_SURVEY)
            ->where('is_enabled', true);
    }
}
