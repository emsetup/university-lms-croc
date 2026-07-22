<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PortalMailLog;
use App\Services\Mail\PortalMailFeedService;
use App\Services\Mail\PortalMailService;
use App\Services\Mail\PortalMailTemplateCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

final class AdminMailLogsController extends Controller
{
    public function index(PortalMailFeedService $feed): View
    {
        return view('admin.mail-logs', [
            'mailTab' => 'zhurnal',
            'mailStats' => $feed->stats(),
            'mailFeedUrl' => route('admin.mail.feed'),
            'typeLabels' => PortalMailFeedService::TYPE_LABELS,
            'statusLabels' => PortalMailFeedService::STATUS_LABELS,
            'emailSuggestions' => $feed->recentEmailSuggestions(),
        ]);
    }

    public function templates(PortalMailFeedService $feed): View
    {
        return view('admin.mail-logs', [
            'mailTab' => 'shablony',
            'mailStats' => $feed->stats(),
            'mailTemplates' => PortalMailTemplateCatalog::all(),
        ]);
    }

    public function feed(PortalMailFeedService $feed): JsonResponse
    {
        return response()->json($feed->feed(request()));
    }

    public function show(PortalMailLog $mailLog, PortalMailFeedService $feed): JsonResponse
    {
        return response()->json($feed->detail($mailLog));
    }

    public function resend(PortalMailLog $mailLog, PortalMailService $mail): JsonResponse
    {
        $copy = $mail->resend($mailLog);

        return response()->json([
            'ok' => $copy->status === PortalMailLog::STATUS_SENT,
            'log' => [
                'id' => (int) $copy->id,
                'status' => (string) $copy->status,
                'status_label' => PortalMailLog::statusLabel((string) $copy->status),
                'error' => $copy->error,
            ],
            'message' => $copy->status === PortalMailLog::STATUS_SENT
                ? 'Письмо отправлено повторно.'
                : ('Не удалось отправить: '.($copy->error ?: $copy->status)),
        ], $copy->status === PortalMailLog::STATUS_SENT ? 200 : 422);
    }
}
