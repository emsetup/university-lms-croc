<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Learner;
use App\Models\PortalMailLog;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class PortalMailService
{
    public function __construct(private EwsMailClient $ews) {}

    public function tableReady(): bool
    {
        return Schema::hasTable('portal_mail_logs');
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<array{cid: string, name: string, path: string, content_type?: string}>  $inlineImages
     */
    public function send(
        string $type,
        string $toEmail,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        ?string $toName = null,
        ?int $learnerId = null,
        array $meta = [],
        ?int $resendOfId = null,
        array $inlineImages = [],
    ): PortalMailLog {
        $toEmail = mb_strtolower(trim($toEmail));
        $subject = Str::limit(trim($subject), 500, '');
        $bodyText = $bodyText ?? trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $bodyHtml)), ENT_QUOTES, 'UTF-8'));

        $actor = $this->actorContext();

        if (! $this->tableReady()) {
            throw new \RuntimeException('Таблица portal_mail_logs не создана. Выполните миграции.');
        }

        if ($inlineImages === []) {
            $inlineImages = PortalMailAssets::inlineImages();
        }

        /** @var PortalMailLog $log */
        $log = PortalMailLog::query()->create([
            'type' => $type !== '' ? $type : PortalMailLog::TYPE_GENERIC,
            'status' => PortalMailLog::STATUS_PENDING,
            'to_email' => $toEmail,
            'to_name' => $toName !== null ? Str::limit(trim($toName), 255, '') : null,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'error' => null,
            'meta' => $meta !== [] ? $meta : null,
            'learner_id' => $learnerId,
            'sent_by_learner_id' => $actor['learner_id'],
            'sent_by_email' => $actor['email'],
            'resend_of_id' => $resendOfId,
            'sent_at' => null,
        ]);

        if (! (bool) config('portal_mail.enabled', true)) {
            $log->status = PortalMailLog::STATUS_SKIPPED;
            $log->error = 'PORTAL_MAIL_ENABLED=false';
            $log->save();

            return $log;
        }

        if ($toEmail === '' || ! filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $log->status = PortalMailLog::STATUS_FAILED;
            $log->error = 'Некорректный email получателя';
            $log->save();

            return $log;
        }

        try {
            $result = $this->ews->send(
                [$toEmail],
                $subject,
                $bodyHtml,
                $bodyText,
                null,
                null,
                $inlineImages,
            );
            if ($result['ok']) {
                $log->status = PortalMailLog::STATUS_SENT;
                $log->sent_at = now();
                $log->error = null;
            } else {
                $log->status = PortalMailLog::STATUS_FAILED;
                $log->error = Str::limit((string) ($result['error'] ?? 'Ошибка EWS'), 2000, '');
            }
        } catch (Throwable $e) {
            $log->status = PortalMailLog::STATUS_FAILED;
            $log->error = Str::limit($e->getMessage(), 2000, '');
        }

        $log->save();

        return $log;
    }

    public function resend(PortalMailLog $original): PortalMailLog
    {
        return $this->send(
            type: (string) $original->type,
            toEmail: (string) $original->to_email,
            subject: (string) $original->subject,
            bodyHtml: (string) $original->body_html,
            bodyText: $original->body_text !== null ? (string) $original->body_text : null,
            toName: $original->to_name !== null ? (string) $original->to_name : null,
            learnerId: $original->learner_id !== null ? (int) $original->learner_id : null,
            meta: is_array($original->meta) ? $original->meta : [],
            resendOfId: (int) $original->id,
        );
    }

    /**
     * @return array{learner_id: ?int, email: ?string}
     */
    private function actorContext(): array
    {
        $learnerId = (int) session('learner_id', 0);
        $email = null;
        if ($learnerId > 0) {
            $email = Learner::query()->whereKey($learnerId)->value('email');
            $email = $email !== null ? (string) $email : null;
        }

        return [
            'learner_id' => $learnerId > 0 ? $learnerId : null,
            'email' => $email,
        ];
    }
}
