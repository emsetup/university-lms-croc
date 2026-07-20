<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Services\PortalStaffAccess;
use App\Services\SectionParticipantsAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminSectionParticipantsController extends Controller
{
    public function __construct(
        private SectionParticipantsAnalyticsService $analytics,
        private PortalStaffAccess $staff,
    ) {}

    public function index(
        Request $request,
        Course $adminCourse,
        CourseModule $courseModule,
        CourseSection $section
    ): View {
        $this->assertSection($adminCourse, $courseModule, $section);
        $this->staff->assertCanViewCourseLearnerStats((int) $adminCourse->id);

        $payload = $this->analytics->participantsForSection($section);
        $selectedLearnerId = (int) $request->query('learner', 0);
        $detail = null;
        if ($selectedLearnerId > 0 && ! $payload['anonymous']) {
            $detail = $this->analytics->detailForLearner($section, $selectedLearnerId);
        }

        return view('admin.section-participants', [
            'course' => $adminCourse,
            'module' => $courseModule,
            'section' => $section,
            'payload' => $payload,
            'selectedLearnerId' => $selectedLearnerId > 0 ? $selectedLearnerId : null,
            'detail' => $detail,
        ]);
    }

    public function indexJson(
        Course $adminCourse,
        CourseModule $courseModule,
        CourseSection $section
    ): JsonResponse {
        $this->assertSection($adminCourse, $courseModule, $section);
        $this->staff->assertCanViewCourseLearnerStats((int) $adminCourse->id);

        $payload = $this->analytics->participantsForSection($section);

        return response()->json([
            'ok' => true,
            ...$payload,
            'page_url' => route('admin.course.module.section.participants', [
                'adminCourse' => $adminCourse->slug,
                'courseModule' => $courseModule->id,
                'section' => $section->id,
            ]),
        ]);
    }

    public function detailJson(
        Course $adminCourse,
        CourseModule $courseModule,
        CourseSection $section,
        int $learner
    ): JsonResponse {
        $this->assertSection($adminCourse, $courseModule, $section);
        $this->staff->assertCanViewCourseLearnerStats((int) $adminCourse->id);

        if ($learner < 1) {
            abort(404);
        }

        $payload = $this->analytics->participantsForSection($section);
        if ($payload['anonymous']) {
            return response()->json([
                'ok' => false,
                'message' => 'Анонимный опрос — персональные ответы недоступны.',
            ], 403);
        }

        return response()->json($this->analytics->detailForLearner($section, $learner));
    }

    private function assertSection(Course $course, CourseModule $module, CourseSection $section): void
    {
        if ((int) $section->course_id !== (int) $course->id
            || (int) $section->course_module_id !== (int) $module->id
            || (int) $module->course_id !== (int) $course->id) {
            abort(404);
        }
    }
}
