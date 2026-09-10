<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Learner;
use App\Models\PortalBugReport;
use App\Services\Mail\PortalMailNotifier;
use App\Support\LearnerDisplay;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class PortalBugReportService
{
    public const MAX_SCREENSHOTS = 5;

    public const MAX_SCREENSHOT_BYTES = 5_242_880; // 5 MB

    /** @var list<string> */
    public const ALLOWED_MIMES = [
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/gif',
    ];

    public function __construct(private PortalMailNotifier $notifier) {}

    public function tableReady(): bool
    {
        return Schema::hasTable('portal_bug_reports');
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function createFromRequest(Request $request, Learner $learner, array $files = []): PortalBugReport
    {
        $type = (string) $request->input('type', PortalBugReport::TYPE_BUG);
        if (! in_array($type, PortalBugReport::TYPES, true)) {
            $type = PortalBugReport::TYPE_BUG;
        }

        $scope = (string) $request->input('scope', PortalBugReport::SCOPE_PORTAL);
        if (! in_array($scope, PortalBugReport::SCOPES, true)) {
            $scope = PortalBugReport::SCOPE_PORTAL;
        }

        $courseId = null;
        $course = null;
        if ($scope === PortalBugReport::SCOPE_COURSE) {
            $courseId = (int) $request->input('course_id', 0);
            $course = $courseId > 0 ? Course::query()->find($courseId) : null;
            if ($course === null) {
                throw new \InvalidArgumentException('Выберите курс, к которому относится сообщение.');
            }
            $courseId = (int) $course->id;
        }

        $title = trim((string) $request->input('title', ''));
        $message = trim((string) $request->input('message', ''));
        $pageUrl = trim((string) $request->input('page_url', ''));
        $pageTitle = trim((string) $request->input('page_title', ''));

        $report = PortalBugReport::query()->create([
            'type' => $type,
            'scope' => $scope,
            'course_id' => $courseId,
            'status' => PortalBugReport::STATUS_NEW,
            'title' => $title !== '' ? Str::limit($title, 255, '') : null,
            'message' => $message,
            'page_url' => $pageUrl !== '' ? Str::limit($pageUrl, 1000, '') : null,
            'page_title' => $pageTitle !== '' ? Str::limit($pageTitle, 500, '') : null,
            'learner_id' => (int) $learner->id,
            'user_email' => mb_strtolower(trim((string) $learner->email)) ?: null,
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'ip' => $request->ip(),
            'screenshots' => [],
            'screenshot_count' => 0,
        ]);

        $stored = $this->storeScreenshots($report, $files);
        if ($stored !== []) {
            $report->screenshots = $stored;
            $report->screenshot_count = count($stored);
            $report->save();
        }

        if ($course !== null) {
            $report->setRelation('course', $course);
        }

        $this->dispatchNotifications($report, $learner, $course);

        return $report;
    }

    private function dispatchNotifications(PortalBugReport $report, Learner $reporter, ?Course $course): void
    {
        try {
            $this->notifier->notifyBugReportAck($report, $reporter);
        } catch (Throwable $e) {
            Log::warning('portal_bug_report.ack_failed', [
                'id' => (int) $report->id,
                'error' => $e->getMessage(),
            ]);
        }

        $recipients = $this->staffNotifyRecipients($report, $course, $reporter);
        foreach ($recipients as $row) {
            try {
                $this->notifier->notifyBugReportStaff(
                    $report,
                    $reporter,
                    $row['email'],
                    $row['name'],
                    $row['role'],
                    $course,
                );
            } catch (Throwable $e) {
                Log::warning('portal_bug_report.staff_notify_failed', [
                    'id' => (int) $report->id,
                    'email' => $row['email'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return list<array{email: string, name: ?string, role: string}>
     */
    private function staffNotifyRecipients(PortalBugReport $report, ?Course $course, Learner $reporter): array
    {
        $out = [];
        $seen = [];
        $reporterEmail = mb_strtolower(trim((string) $reporter->email));

        $add = static function (string $email, ?string $name, string $role) use (&$out, &$seen, $reporterEmail): void {
            $email = mb_strtolower(trim($email));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            if ($email === $reporterEmail) {
                return;
            }
            if (isset($seen[$email])) {
                return;
            }
            $seen[$email] = true;
            $out[] = ['email' => $email, 'name' => $name, 'role' => $role];
        };

        $add(self::notifyEmail(), null, 'admin');

        if ($report->scope === PortalBugReport::SCOPE_COURSE && $course !== null) {
            $author = $this->courseAuthorLearner($course);
            if ($author !== null) {
                $email = mb_strtolower(trim((string) $author->email));
                $name = LearnerDisplay::portalDisplayName($author) ?: null;
                $add($email, $name, 'course_author');
            }
        }

        return $out;
    }

    public function courseAuthorLearner(Course $course): ?Learner
    {
        $course->loadMissing('createdByPortalStaff.learner');
        $staff = $course->createdByPortalStaff;
        if ($staff === null || $staff->learner === null) {
            return null;
        }

        return $staff->learner;
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    public static function courseOptions(): array
    {
        if (! Schema::hasTable('courses')) {
            return [];
        }

        return Course::query()
            ->where('is_archived', false)
            ->orderBy('sort')
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(static fn (Course $c) => [
                'id' => (int) $c->id,
                'title' => (string) $c->title,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{path: string, name: string, mime: string, bytes: int}>
     */
    private function storeScreenshots(PortalBugReport $report, array $files): array
    {
        $out = [];
        $dir = 'private/bug-reports/'.$report->id;
        Storage::disk('local')->makeDirectory($dir);

        foreach (array_slice($files, 0, self::MAX_SCREENSHOTS) as $i => $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $mime = (string) ($file->getMimeType() ?: '');
            if (! in_array($mime, self::ALLOWED_MIMES, true)) {
                continue;
            }
            if ($file->getSize() > self::MAX_SCREENSHOT_BYTES) {
                continue;
            }

            $ext = match ($mime) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                default => 'jpg',
            };
            $name = sprintf('%d_%s.%s', $i + 1, Str::lower(Str::random(10)), $ext);
            $path = $file->storeAs($dir, $name, 'local');
            if (! is_string($path) || $path === '') {
                continue;
            }

            $out[] = [
                'path' => $path,
                'name' => Str::limit((string) $file->getClientOriginalName(), 200, '') ?: $name,
                'mime' => $mime,
                'bytes' => (int) $file->getSize(),
            ];
        }

        return $out;
    }

    public function absolutePath(string $relative): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }
        if (! str_starts_with($relative, 'private/bug-reports/')) {
            return null;
        }
        if (! Storage::disk('local')->exists($relative)) {
            return null;
        }

        return Storage::disk('local')->path($relative);
    }

    /**
     * @return list<string>
     */
    public static function inboxEmails(): array
    {
        $raw = (string) config('portal.bug_inbox_emails', 'emednikov@croc.ru');
        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $e = mb_strtolower(trim((string) $p));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $out[] = $e;
            }
        }

        return array_values(array_unique($out)) ?: ['emednikov@croc.ru'];
    }

    public static function learnerCanAccessInbox(?Learner $learner): bool
    {
        if ($learner === null) {
            return false;
        }
        $email = mb_strtolower(trim((string) $learner->email));

        return $email !== '' && in_array($email, self::inboxEmails(), true);
    }

    public static function notifyEmail(): string
    {
        $email = mb_strtolower(trim((string) config('portal.bug_notify_email', 'emednikov@croc.ru')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'emednikov@croc.ru';
        }

        return $email;
    }

    public static function displayName(PortalBugReport $report): string
    {
        if ($report->learner) {
            $name = LearnerDisplay::portalDisplayName($report->learner);
            if ($name !== '') {
                return $name;
            }
        }

        return (string) ($report->user_email ?: 'Пользователь');
    }
}
