<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Course;
use App\Models\CourseContentGrant;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Models\Learner;
use App\Models\PortalMailLog;
use App\Models\PortalStaff;
use App\Support\LearnerDisplay;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Высокоуровневые уведомления портала (доступ, права, опросы).
 */
final class PortalMailNotifier
{
    public function __construct(private PortalMailService $mail) {}

    public function notifyAccessGranted(
        Learner $learner,
        Course $course,
        string $resourceLabel,
        ?string $url = null,
    ): ?PortalMailLog {
        $email = $this->emailOf($learner);
        if ($email === null) {
            return null;
        }

        $portalUrl = $url ?: $this->portalUrl();
        $name = LearnerDisplay::portalDisplayName($learner) ?: $email;
        $courseTitle = (string) $course->title;

        $subject = 'Тебе открыт доступ: '.$courseTitle;
        $lead = 'Тебе открыли доступ к материалу на учебном портале.';
        $details = [
            'Курс' => $courseTitle,
            'Материал' => $resourceLabel,
        ];

        return $this->safeSend(
            PortalMailLog::TYPE_ACCESS_GRANTED,
            $email,
            $name,
            (int) $learner->id,
            $subject,
            $lead,
            $details,
            $portalUrl,
            'Открыть портал',
            [
                'course_id' => (int) $course->id,
                'course_title' => $courseTitle,
                'resource_label' => $resourceLabel,
            ],
        );
    }

    public function notifyStaffAdded(PortalStaff $staff, string $roleLabel): ?PortalMailLog
    {
        $learner = $staff->learner;
        if ($learner === null) {
            $learner = Learner::query()->find((int) $staff->learner_id);
        }
        if ($learner === null) {
            return null;
        }
        $email = $this->emailOf($learner);
        if ($email === null) {
            return null;
        }

        $name = LearnerDisplay::portalDisplayName($learner) ?: $email;
        $subject = 'Тебе выдали права на портале';
        $lead = 'Тебя добавили в сотрудники учебного портала. Зайди под корпоративной почтой — откроется панель /adm.';
        $details = [
            'Роль' => $roleLabel,
            'Почта' => $email,
        ];

        return $this->safeSend(
            PortalMailLog::TYPE_STAFF_ADDED,
            $email,
            $name,
            (int) $learner->id,
            $subject,
            $lead,
            $details,
            $this->adminUrl(),
            'Открыть панель',
            [
                'portal_staff_id' => (int) $staff->id,
                'role' => (string) $staff->role,
                'role_label' => $roleLabel,
            ],
        );
    }

    /**
     * @param  list<array{resource_type: string, resource_id: int|null, permission: string}>  $grants
     */
    public function notifyCollaborator(PortalStaff $staff, Course $course, array $grants): ?PortalMailLog
    {
        $learner = $staff->learner ?? Learner::query()->find((int) $staff->learner_id);
        if ($learner === null) {
            return null;
        }
        $email = $this->emailOf($learner);
        if ($email === null) {
            return null;
        }

        $name = LearnerDisplay::portalDisplayName($learner) ?: $email;
        $courseTitle = (string) $course->title;
        $grantLines = $this->formatGrants($course, $grants);
        $subject = 'Права соавтора: '.$courseTitle;
        $lead = 'Тебе выдали права на курс в панели администратора.';
        $details = [
            'Курс' => $courseTitle,
            'Права' => $grantLines !== [] ? implode('; ', $grantLines) : 'обновлены',
        ];

        $url = route('admin.course.settings', ['adminCourse' => $course->slug]);

        return $this->safeSend(
            PortalMailLog::TYPE_COLLABORATOR,
            $email,
            $name,
            (int) $learner->id,
            $subject,
            $lead,
            $details,
            $url,
            'Открыть настройки курса',
            [
                'course_id' => (int) $course->id,
                'portal_staff_id' => (int) $staff->id,
                'grants' => $grants,
            ],
        );
    }

    public function notifySurveyInvite(
        string $email,
        string $surveyUrl,
        Course $course,
        CourseSection $section,
        ?Learner $learner = null,
        ?string $toName = null,
    ): ?PortalMailLog {
        $email = mb_strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $courseTitle = (string) $course->title;
        $sectionTitle = trim((string) ($section->title ?: 'Опрос'));
        $name = $toName;
        if ($name === null && $learner !== null) {
            $name = LearnerDisplay::portalDisplayName($learner) ?: null;
        }

        $subject = 'Приглашение пройти опрос: '.$sectionTitle;
        $lead = 'Тебя пригласили пройти опрос на учебном портале.';
        $details = [
            'Курс' => $courseTitle,
            'Опрос' => $sectionTitle,
        ];

        return $this->safeSend(
            PortalMailLog::TYPE_SURVEY_INVITE,
            $email,
            $name,
            $learner?->id !== null ? (int) $learner->id : null,
            $subject,
            $lead,
            $details,
            $surveyUrl,
            'Пройти опрос',
            [
                'course_id' => (int) $course->id,
                'section_id' => (int) $section->id,
                'survey_url' => $surveyUrl,
            ],
        );
    }

    /**
     * @param  array<string, string>  $details
     * @param  array<string, mixed>  $meta
     */
    private function safeSend(
        string $type,
        string $email,
        ?string $name,
        ?int $learnerId,
        string $subject,
        string $lead,
        array $details,
        string $ctaUrl,
        string $ctaLabel,
        array $meta,
    ): ?PortalMailLog {
        try {
            if (! $this->mail->tableReady()) {
                return null;
            }

            $ctaBtn = PortalMailAssets::resolveCtaButton($ctaLabel);
            $imgMap = PortalMailAssets::cidMap($ctaBtn['file'] ?? null);

            $html = view('emails.portal-notification', [
                'greeting' => $this->informalGreeting($name),
                'lead' => $lead,
                'details' => $details,
                'ctaUrl' => $ctaUrl,
                'ctaLabel' => $ctaLabel,
                'ctaButton' => $ctaBtn,
                'portalName' => (string) config('portal_mail.from_name', 'Учебный портал'),
                'headline' => $this->headlineForType($type, $subject),
                'eyebrow' => $this->eyebrowForType($type),
                'img' => $imgMap,
            ])->render();

            $inline = PortalMailAssets::inlineImages(
                $ctaBtn['path'] ?? null,
                $ctaBtn['cid'] ?? null,
            );

            return $this->mail->send(
                type: $type,
                toEmail: $email,
                subject: $subject,
                bodyHtml: $html,
                toName: $name,
                learnerId: $learnerId,
                meta: $meta,
                inlineImages: $inline,
            );
        } catch (Throwable $e) {
            Log::warning('portal_mail.notify_failed', [
                'type' => $type,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function emailOf(Learner $learner): ?string
    {
        $email = mb_strtolower(trim((string) $learner->email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    private function portalUrl(): string
    {
        try {
            return route('portal');
        } catch (Throwable) {
            return rtrim((string) config('app.url'), '/');
        }
    }

    private function adminUrl(): string
    {
        try {
            return route('admin.panel');
        } catch (Throwable) {
            return $this->portalUrl();
        }
    }

    /**
     * @param  list<array{resource_type: string, resource_id: int|null, permission: string}>  $grants
     * @return list<string>
     */
    private function formatGrants(Course $course, array $grants): array
    {
        $permLabels = [
            CourseContentGrant::PERMISSION_VIEW => 'просмотр',
            CourseContentGrant::PERMISSION_EDIT => 'редактирование',
            CourseContentGrant::PERMISSION_MANAGE => 'управление',
        ];
        $lines = [];
        foreach ($grants as $g) {
            $type = (string) ($g['resource_type'] ?? '');
            $perm = $permLabels[(string) ($g['permission'] ?? '')] ?? (string) ($g['permission'] ?? '');
            $rid = $g['resource_id'] ?? null;
            if ($type === CourseContentGrant::RESOURCE_COURSE) {
                $lines[] = 'курс — '.$perm;
            } elseif ($type === CourseContentGrant::RESOURCE_MODULE && $rid) {
                $title = CourseModule::query()->whereKey((int) $rid)->value('title') ?: '#'.$rid;
                $lines[] = 'модуль «'.$title.'» — '.$perm;
            } elseif ($type === CourseContentGrant::RESOURCE_SECTION && $rid) {
                $title = CourseSection::query()->whereKey((int) $rid)->value('title') ?: '#'.$rid;
                $lines[] = 'раздел «'.$title.'» — '.$perm;
            }
        }

        return $lines;
    }

    public static function roleLabel(string $role): string
    {
        return match ($role) {
            PortalStaff::ROLE_PORTAL_ADMIN => 'Администратор портала',
            PortalStaff::ROLE_COURSE_MODERATOR => 'Модератор курсов',
            PortalStaff::ROLE_PORTAL_AUDITOR => 'Аудитор портала',
            PortalStaff::ROLE_COURSE_CREATOR => 'Создатель курсов',
            PortalStaff::ROLE_COURSE_EDITOR => 'Редактор курсов',
            PortalStaff::ROLE_INSTRUCTOR => 'Инструктор',
            PortalStaff::ROLE_COURSE_TESTER => 'Тестировщик курса',
            PortalStaff::ROLE_COURSE_CONTRIBUTOR => 'Соавтор курса',
            default => $role,
        };
    }

    public static function resourceLabel(string $resourceType, int $resourceId, Course $course): string
    {
        if ($resourceType === \App\Models\ContentViewAudienceRule::RESOURCE_COURSE) {
            return 'Курс «'.$course->title.'»';
        }
        if ($resourceType === \App\Models\ContentViewAudienceRule::RESOURCE_MODULE) {
            $m = CourseModule::query()->find($resourceId);

            return 'Модуль «'.($m?->title ?: '#'.$resourceId).'»';
        }
        if ($resourceType === \App\Models\ContentViewAudienceRule::RESOURCE_SECTION) {
            $s = CourseSection::query()->find($resourceId);

            return 'Раздел «'.($s?->title ?: '#'.$resourceId).'»';
        }

        return 'Материал';
    }

    private function headlineForType(string $type, string $subject): string
    {
        return match ($type) {
            PortalMailLog::TYPE_ACCESS_GRANTED => 'Тебе открыт доступ',
            PortalMailLog::TYPE_STAFF_ADDED => 'Новые права на портале',
            PortalMailLog::TYPE_COLLABORATOR => 'Ты стал соавтором',
            PortalMailLog::TYPE_SURVEY_INVITE => 'Приглашение на опрос',
            default => Str::limit($subject !== '' ? $subject : 'Уведомление портала', 80, ''),
        };
    }

    private function informalGreeting(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'Привет!';
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        $parts = array_values(array_filter($parts, static fn ($p) => $p !== ''));
        if ($parts === []) {
            return 'Привет!';
        }

        // Обычно «Фамилия Имя» — берём имя
        $first = count($parts) >= 2 ? $parts[count($parts) - 1] : $parts[0];

        return 'Привет, '.$first.'!';
    }

    private function eyebrowForType(string $type): string
    {
        return match ($type) {
            PortalMailLog::TYPE_ACCESS_GRANTED => 'Доступ к обучению',
            PortalMailLog::TYPE_STAFF_ADDED => 'Сотрудники портала',
            PortalMailLog::TYPE_COLLABORATOR => 'Соавторы курса',
            PortalMailLog::TYPE_SURVEY_INVITE => 'Опросы',
            default => 'Учебный портал КРОК',
        };
    }
}
