<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseGlossaryTerm;
use App\Services\CourseGlossaryService;
use App\Services\PortalStaffAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminCourseGlossaryController extends Controller
{
    public function store(Request $request, Course $adminCourse): RedirectResponse
    {
        $course = $adminCourse;
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) $course->id);
        $data = $this->validated($request, (int) $course->id);

        CourseGlossaryTerm::query()->create([
            'course_id' => (int) $course->id,
            'term' => $data['term'],
            'abbreviation' => $data['abbreviation'],
            'definition' => $data['definition'],
            'sort' => $data['sort'],
        ]);
        app(CourseGlossaryService::class)->clearCache((int) $course->id);

        return $this->backToTab($course)->with('ok', 'Термин добавлен в глоссарий.');
    }

    public function update(Request $request, Course $adminCourse, CourseGlossaryTerm $term): RedirectResponse
    {
        $course = $adminCourse;
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) $course->id);
        abort_unless((int) $term->course_id === (int) $course->id, 404);

        $data = $this->validated($request, (int) $course->id, (int) $term->id);
        $term->update([
            'term' => $data['term'],
            'abbreviation' => $data['abbreviation'],
            'definition' => $data['definition'],
            'sort' => $data['sort'],
        ]);
        app(CourseGlossaryService::class)->clearCache((int) $course->id);

        return $this->backToTab($course)->with('ok', 'Термин обновлён.');
    }

    public function destroy(Course $adminCourse, CourseGlossaryTerm $term): RedirectResponse
    {
        $course = $adminCourse;
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) $course->id);
        abort_unless((int) $term->course_id === (int) $course->id, 404);

        $term->delete();
        app(CourseGlossaryService::class)->clearCache((int) $course->id);

        return $this->backToTab($course)->with('ok', 'Термин удалён.');
    }

    /**
     * @return array{term:string, abbreviation:?string, definition:string, sort:int}
     */
    private function validated(Request $request, int $courseId, ?int $ignoreId = null): array
    {
        $unique = Rule::unique('course_glossary_terms', 'term')
            ->where(fn ($q) => $q->where('course_id', $courseId));
        if ($ignoreId !== null) {
            $unique = $unique->ignore($ignoreId);
        }

        $data = $request->validate([
            'term' => ['required', 'string', 'max:120', $unique],
            'abbreviation' => ['nullable', 'string', 'max:64'],
            'definition' => ['required', 'string', 'max:4000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], [
            'term.unique' => 'Такой термин уже есть в глоссарии курса.',
            'term.required' => 'Укажите термин или аббревиатуру.',
            'definition.required' => 'Добавьте краткое описание.',
        ]);

        $abbr = isset($data['abbreviation']) ? trim((string) $data['abbreviation']) : '';

        return [
            'term' => trim((string) $data['term']),
            'abbreviation' => $abbr !== '' ? $abbr : null,
            'definition' => trim((string) $data['definition']),
            'sort' => isset($data['sort']) && is_numeric($data['sort']) ? (int) $data['sort'] : 100,
        ];
    }

    private function backToTab(Course $course): RedirectResponse
    {
        return redirect()->route('admin.course.settings', [
            'adminCourse' => $course->slug,
            'tab' => 'glossariy',
        ]);
    }
}
