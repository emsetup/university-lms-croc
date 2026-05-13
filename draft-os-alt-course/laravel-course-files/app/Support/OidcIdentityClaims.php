<?php

namespace App\Support;

/**
 * Извлечение почты и отображаемого имени из id_token / userinfo (ADFS, Keycloak, прочие IdP).
 */
final class OidcIdentityClaims
{
    /**
     * Группы claim’ов, из которых обычно собирают ФИО (для отладки и документации).
     * В каждой группе достаточно одного непустого ключа.
     *
     * @return list<array{id: string, label: string, keys: list<string>}>
     */
    public static function displayNameClaimGroups(): array
    {
        return [
            [
                'id' => 'display_name',
                'label' => 'Display name (как в AD: атрибут «Display name»; ADFS URI)',
                'keys' => [
                    'http://schemas.microsoft.com/identity/claims/displayname',
                    'displayName',
                ],
            ],
            [
                'id' => 'oidc_name',
                'label' => 'Полное имя в одном поле (OIDC claim `name` — часто Keycloak / Azure AD B2C)',
                'keys' => [
                    'name',
                ],
            ],
            [
                'id' => 'wsfed_name',
                'label' => 'Имя в стиле WS-Federation (claim Name, ADFS)',
                'keys' => [
                    'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
                ],
            ],
            [
                'id' => 'given_name',
                'label' => 'Имя (Given name)',
                'keys' => [
                    'given_name',
                    'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname',
                    'firstname',
                    'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/firstname',
                ],
            ],
            [
                'id' => 'family_name',
                'label' => 'Фамилия (Surname / Family name)',
                'keys' => [
                    'family_name',
                    'surname',
                    'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname',
                    'lastname',
                    'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/lastname',
                ],
            ],
            [
                'id' => 'middle_name',
                'label' => 'Отчество (Middle name), опционально',
                'keys' => [
                    'middle_name',
                    'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/middlename',
                ],
            ],
            [
                'id' => 'nickname',
                'label' => 'Nickname (редко как ФИО)',
                'keys' => [
                    'nickname',
                ],
            ],
        ];
    }

    /**
     * Строки для таблицы «ожидали / получили» (например, ?identity_debug=1).
     *
     * @param  array<string, mixed>  $claims
     * @return list<array{label: string, value: string}>
     */
    public static function nameClaimsCoverageRows(array $claims): array
    {
        $rows = [];
        foreach (self::displayNameClaimGroups() as $g) {
            $foundKey = null;
            $foundVal = null;
            foreach ($g['keys'] as $k) {
                $s = self::scalar($claims, $k);
                if ($s !== '') {
                    $foundKey = $k;
                    $foundVal = $s;
                    break;
                }
            }
            $keyList = implode(', ', array_map(static fn (string $x): string => '`'.$x.'`', $g['keys']));
            if ($foundVal !== null) {
                $rows[] = [
                    'label' => '[ожидание ФИО] '.$g['label'],
                    'value' => 'получено: ключ `'.$foundKey.'` → '.self::shortenForTable($foundVal),
                ];
            } else {
                $rows[] = [
                    'label' => '[ожидание ФИО] '.$g['label'],
                    'value' => 'нет — ожидается один из: '.$keyList,
                ];
            }
        }

        $rows[] = [
            'label' => '[справочно] preferred_username (Keycloak / OIDC)',
            'value' => self::scalar($claims, 'preferred_username') !== ''
                ? 'есть: `'.self::shortenForTable(self::scalar($claims, 'preferred_username')).'` (часто логин, не ФИО)'
                : 'нет',
        ];
        $rows[] = [
            'label' => '[справочно] unique_name (часто DOMAIN\\user в ADFS)',
            'value' => self::scalar($claims, 'unique_name') !== ''
                ? 'есть: `'.self::shortenForTable(self::scalar($claims, 'unique_name')).'`'
                : 'нет',
        ];

        $resolved = self::displayName($claims);
        $summary = $resolved !== ''
            ? 'Итог: из токена собрано отображаемое имя: «'.self::shortenForTable($resolved).'».'
            : 'Итог: ни одна группа ФИО не заполнена — портал не может показать «Добро пожаловать, Имя Фамилия» без доработки IdP (добавьте хотя бы Display name или `given_name`+`family_name` / OIDC `name`).';

        $rows[] = ['label' => '[вывод] Сводка по ФИО', 'value' => $summary];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function email(array $claims): string
    {
        foreach ([
            'email',
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
            'upn',
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/upn',
            'unique_name',
            'preferred_username',
        ] as $k) {
            $v = $claims[$k] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function displayName(array $claims): string
    {
        foreach ([
            'http://schemas.microsoft.com/identity/claims/displayname',
            'displayName',
        ] as $k) {
            $s = self::scalar($claims, $k);
            if ($s !== '' && ! str_contains($s, '\\')) {
                return $s;
            }
        }

        $oidcName = self::scalar($claims, 'name');
        if ($oidcName !== '' && self::nameLooksHuman($oidcName, $claims)) {
            return $oidcName;
        }

        foreach ([
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
        ] as $k) {
            $s = self::scalar($claims, $k);
            if ($s !== '' && self::nameLooksHuman($s, $claims)) {
                return $s;
            }
        }

        $gn = self::scalar($claims, 'given_name')
            ?: self::scalar($claims, 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname')
            ?: self::scalar($claims, 'firstname')
            ?: self::scalar($claims, 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/firstname');
        $fn = self::scalar($claims, 'family_name')
            ?: self::scalar($claims, 'surname')
            ?: self::scalar($claims, 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname')
            ?: self::scalar($claims, 'lastname')
            ?: self::scalar($claims, 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/lastname');
        $pair = trim($gn.' '.$fn);
        if ($pair !== '') {
            return $pair;
        }

        $nick = self::scalar($claims, 'nickname');
        if ($nick !== '' && self::nameLooksHuman($nick, $claims)) {
            return $nick;
        }

        foreach ($claims as $k => $v) {
            if (! is_string($k) || ! is_string($v)) {
                continue;
            }
            $kl = strtolower($k);
            if (! preg_match('/display|given|surname|family|commonname|fullname|firstname|lastname|patronym/i', $kl)) {
                continue;
            }
            $t = trim($v);
            if ($t === '' || str_contains($t, '\\')) {
                continue;
            }
            if (self::nameLooksHuman($t, $claims)) {
                return $t;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function hasAnyNameLikeClaim(array $claims): bool
    {
        foreach (self::displayNameClaimGroups() as $g) {
            foreach ($g['keys'] as $k) {
                if (self::scalar($claims, $k) !== '') {
                    return true;
                }
            }
        }

        if (self::scalar($claims, 'name') !== '') {
            return true;
        }

        $pat = '/display|given|surname|family|commonname|fullname|firstname|lastname|patronym/i';
        foreach (array_keys($claims) as $k) {
            if (is_string($k) && preg_match($pat, $k)) {
                return true;
            }
        }

        return false;
    }

    private static function shortenForTable(string $s, int $max = 200): string
    {
        return strlen($s) > $max ? substr($s, 0, $max).'…' : $s;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private static function nameLooksHuman(string $name, array $claims): bool
    {
        if (str_contains($name, '\\')) {
            return false;
        }

        if (str_contains($name, ' ') || preg_match('/\p{Cyrillic}/u', $name)) {
            return true;
        }

        $email = self::email($claims);
        if ($email !== '') {
            $local = explode('@', strtolower($email), 2)[0] ?? '';
            if ($local !== '' && strcasecmp($name, $local) === 0) {
                return false;
            }
        }

        foreach (['upn', 'preferred_username', 'unique_name', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/upn'] as $k) {
            $u = self::scalar($claims, $k);
            if ($u === '') {
                continue;
            }
            $uLocal = explode('@', strtolower($u), 2)[0] ?? '';
            if ($uLocal !== '' && strcasecmp($name, $uLocal) === 0) {
                return false;
            }
            if (stripos($u, '\\'.$name) !== false) {
                return false;
            }
        }

        return strlen($name) >= 6;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private static function scalar(array $claims, string $key): string
    {
        $v = $claims[$key] ?? null;

        return is_string($v) ? trim($v) : '';
    }
}
