<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Services\PortalStaffAccess;
use App\Services\SurveyResponseExportService;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminSurveyResponsesController extends Controller
{
    public function __construct(
        private SurveyResponseExportService $export,
        private PortalStaffAccess $staff,
    ) {}

    public function index(Course $adminCourse, CourseModule $courseModule, CourseSection $section): View
    {
        $this->assertSurveySection($adminCourse, $courseModule, $section);
        $this->staff->assertCanEditCourseMeta((int) $adminCourse->id);

        $table = $this->export->tableForSection($section);

        return view('admin.survey-responses', [
            'course' => $adminCourse,
            'module' => $courseModule,
            'section' => $section,
            'anonymous' => $table['anonymous'],
            'columns' => $table['columns'],
            'rows' => $table['rows'],
        ]);
    }

    public function exportCsv(Course $adminCourse, CourseModule $courseModule, CourseSection $section): StreamedResponse
    {
        $this->assertSurveySection($adminCourse, $courseModule, $section);
        $this->staff->assertCanEditCourseMeta((int) $adminCourse->id);

        $filename = 'survey-'.$adminCourse->slug.'-m'.$courseModule->id.'-'.$section->id.'.csv';
        $content = $this->export->csvContent($section);

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function assertSurveySection(Course $course, CourseModule $module, CourseSection $section): void
    {
        if ((int) $section->course_id !== (int) $course->id
            || (int) $section->course_module_id !== (int) $module->id
            || $section->type !== CourseSection::TYPE_SURVEY) {
            abort(404);
        }
    }
}
