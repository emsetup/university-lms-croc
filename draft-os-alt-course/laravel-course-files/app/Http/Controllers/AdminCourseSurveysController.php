<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseSection;
use App\Services\CourseSurveyCatalogService;
use App\Services\PortalStaffAccess;
use App\Services\SurveyResponseExportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminCourseSurveysController extends Controller
{
    public function __construct(
        private CourseSurveyCatalogService $catalog,
        private SurveyResponseExportService $export,
        private PortalStaffAccess $staff,
    ) {}

    public function index(Request $request, Course $adminCourse): View
    {
        $courseId = (int) $adminCourse->id;
        $this->staff->assertCanViewCourseLearnerStats($courseId);
        abort_unless($this->catalog->hasSurveys($courseId), 404);

        $surveys = $this->catalog->surveysForCourse($courseId);
        $selectedId = (int) $request->query('section', 0);
        if ($selectedId < 1 && $surveys !== []) {
            $selectedId = (int) $surveys[0]['section_id'];
        }

        $selected = $this->catalog->findSurveySection($courseId, $selectedId);
        $wideTable = null;
        $longTable = null;
        if ($selected instanceof CourseSection) {
            $wideTable = $this->export->tableForSection($selected);
            $longTable = $this->export->longFormForSection($selected);
        }

        return view('admin.course-surveys', [
            'course' => $adminCourse,
            'surveys' => $surveys,
            'selectedSection' => $selected,
            'selectedId' => $selected instanceof CourseSection ? (int) $selected->id : 0,
            'wideTable' => $wideTable,
            'longTable' => $longTable,
        ]);
    }

    public function exportWide(Course $adminCourse, CourseSection $section): StreamedResponse
    {
        return $this->downloadExport($adminCourse, $section, 'wide');
    }

    public function exportLong(Course $adminCourse, CourseSection $section): StreamedResponse
    {
        return $this->downloadExport($adminCourse, $section, 'long');
    }

    private function downloadExport(Course $adminCourse, CourseSection $section, string $mode): StreamedResponse
    {
        $courseId = (int) $adminCourse->id;
        $this->staff->assertCanViewCourseLearnerStats($courseId);
        abort_unless(
            $this->catalog->findSurveySection($courseId, (int) $section->id) !== null,
            404
        );

        $suffix = $mode === 'long' ? '-long' : '';
        $filename = 'survey-'.$adminCourse->slug.'-'.$section->id.$suffix.'.xls';
        $content = $mode === 'long'
            ? $this->export->longFormCsvContent($section)
            : $this->export->csvContent($section);

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }
}
