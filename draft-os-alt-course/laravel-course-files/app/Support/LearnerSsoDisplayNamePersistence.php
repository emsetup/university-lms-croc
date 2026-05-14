<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Learner;
use Illuminate\Support\Facades\Schema;

/**
 * Запись в learners.sso_display_name того же ФИО, что видно на портале из OIDC (сессия + probe claims).
 * Вызывается при входе SSO и при открытии портала/ЛК — чтобы списки «Люди» в админке читали имя из БД.
 */
final class LearnerSsoDisplayNamePersistence
{
    public static function syncIfPossible(Learner $learner): void
    {
        if (! Schema::hasColumn($learner->getTable(), 'sso_display_name')) {
            return;
        }
        $candidate = PortalWelcomeName::ssoMeaningfulNameFromSession($learner);
        if ($candidate === null || $candidate === '') {
            return;
        }
        if (($learner->sso_display_name ?? '') === $candidate) {
            return;
        }
        $learner->sso_display_name = $candidate;
        $learner->saveQuietly();
    }
}
