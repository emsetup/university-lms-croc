<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\PortalBugReport;
use App\Services\PortalBugReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class BugReportController extends Controller
{
    public function store(Request $request, PortalBugReportService $service): JsonResponse
    {
        if (! $service->tableReady()) {
            return response()->json(['ok' => false, 'message' => 'Сервис временно недоступен.'], 503);
        }

        $learnerId = (int) session('learner_id', 0);
        $learner = $learnerId > 0 ? Learner::query()->find($learnerId) : null;
        if ($learner === null) {
            return response()->json([
                'ok' => false,
                'auth_required' => true,
                'message' => 'Чтобы отправить сообщение, войдите в учётную запись.',
                'login_url' => route('portal', ['login' => 1]),
            ], 401);
        }

        $request->validate([
            'type' => ['required', 'string', 'in:bug,suggestion,other'],
            'scope' => ['required', 'string', 'in:portal,course'],
            'course_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => (string) $request->input('scope') === PortalBugReport::SCOPE_COURSE),
                'exists:courses,id',
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:8000'],
            'page_url' => ['nullable', 'string', 'max:1000'],
            'page_title' => ['nullable', 'string', 'max:500'],
            'screenshots' => ['nullable', 'array', 'max:'.PortalBugReportService::MAX_SCREENSHOTS],
            'screenshots.*' => [
                'file',
                'max:'.(int) (PortalBugReportService::MAX_SCREENSHOT_BYTES / 1024),
                'mimetypes:image/png,image/jpeg,image/webp,image/gif',
            ],
        ], [
            'message.required' => 'Опишите проблему или предложение.',
            'message.min' => 'Сообщение слишком короткое (минимум 10 символов).',
            'course_id.required' => 'Выберите курс, к которому относится сообщение.',
            'course_id.exists' => 'Выбранный курс не найден.',
            'screenshots.max' => 'Можно приложить не больше '.PortalBugReportService::MAX_SCREENSHOTS.' скриншотов.',
            'screenshots.*.max' => 'Каждый файл — не больше 5 МБ.',
            'screenshots.*.mimetypes' => 'Допустимы PNG, JPEG, WebP и GIF.',
        ]);

        /** @var list<UploadedFile> $files */
        $files = [];
        foreach ((array) $request->file('screenshots', []) as $file) {
            if ($file instanceof UploadedFile) {
                $files[] = $file;
            }
        }

        try {
            $report = $service->createFromRequest($request, $learner, $files);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'id' => (int) $report->id,
            'ticket' => '#'.$report->id,
            'message' => 'Спасибо! Тикет #'.$report->id.' зарегистрирован. Подтверждение отправлено на вашу почту.',
        ]);
    }
}
