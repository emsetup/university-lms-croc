<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\PortalMailLog;

/**
 * Каталог шаблонов писем и триггеров (для вкладки /adm/pochta/shablony).
 */
final class PortalMailTemplateCatalog
{
    /**
     * @return list<array{
     *   id: string,
     *   type: string,
     *   title: string,
     *   eyebrow: string,
     *   headline: string,
     *   subject: string,
     *   greeting: string,
     *   lead: string,
     *   details: array<string, string>,
     *   cta_label: string,
     *   cta_url: string,
     *   triggers: list<string>,
     *   preview_html: string
     * }>
     */
    public static function all(): array
    {
        $portal = 'https://practice.croc.ru';
        $items = [
            [
                'id' => PortalMailLog::TYPE_ACCESS_GRANTED,
                'type' => PortalMailLog::TYPE_ACCESS_GRANTED,
                'title' => 'Доступ к материалу',
                'eyebrow' => 'Доступ к обучению',
                'headline' => 'Тебе открыт доступ',
                'subject' => 'Тебе открыт доступ: {название курса}',
                'greeting' => 'Привет, Имя!',
                'lead' => 'Тебе открыли доступ к материалу на учебном портале.',
                'details' => [
                    'Курс' => 'Особенности ОС «Альт»',
                    'Материал' => 'Курс «Особенности ОС «Альт»»',
                ],
                'cta_label' => 'Открыть портал',
                'cta_url' => $portal,
                'triggers' => [
                    'Обучающегося добавили на курс (запись / enrollment).',
                    'Выдали доступ к курсу, модулю или разделу через «видимость» (аудитория / группы).',
                    'В письмо попадает конкретный материал: весь курс, модуль или раздел (в т.ч. опрос как раздел).',
                ],
            ],
            [
                'id' => PortalMailLog::TYPE_STAFF_ADDED,
                'type' => PortalMailLog::TYPE_STAFF_ADDED,
                'title' => 'Права сотрудника',
                'eyebrow' => 'Сотрудники портала',
                'headline' => 'Новые права на портале',
                'subject' => 'Тебе выдали права на портале',
                'greeting' => 'Привет, Имя!',
                'lead' => 'Тебя добавили в сотрудники учебного портала. Зайди под корпоративной почтой — откроется панель /adm.',
                'details' => [
                    'Роль' => 'Модератор',
                    'Почта' => 'user@croc.ru',
                ],
                'cta_label' => 'Открыть панель',
                'cta_url' => $portal.'/adm',
                'triggers' => [
                    'Добавили сотрудника в «Сотрудники» портала.',
                    'Сменили роль сотрудника.',
                    'Добавили человека в группу сотрудников с выдачей роли.',
                ],
            ],
            [
                'id' => PortalMailLog::TYPE_COLLABORATOR,
                'type' => PortalMailLog::TYPE_COLLABORATOR,
                'title' => 'Соавтор курса',
                'eyebrow' => 'Соавторы курса',
                'headline' => 'Ты стал соавтором',
                'subject' => 'Права соавтора: {название курса}',
                'greeting' => 'Привет, Имя!',
                'lead' => 'Тебе выдали права на курс в панели администратора.',
                'details' => [
                    'Курс' => 'Особенности ОС «Альт»',
                    'Права' => 'редактирование: Модуль 1',
                ],
                'cta_label' => 'Открыть курс',
                'cta_url' => $portal.'/adm',
                'triggers' => [
                    'Назначили соавтора на курс и выдали права на материалы (просмотр / редактирование / управление).',
                ],
            ],
            [
                'id' => PortalMailLog::TYPE_SURVEY_INVITE,
                'type' => PortalMailLog::TYPE_SURVEY_INVITE,
                'title' => 'Приглашение на опрос',
                'eyebrow' => 'Опросы',
                'headline' => 'Приглашение на опрос',
                'subject' => 'Приглашение пройти опрос: {название опроса}',
                'greeting' => 'Привет, Имя!',
                'lead' => 'Тебя пригласили пройти опрос на учебном портале.',
                'details' => [
                    'Курс' => 'Особенности ОС «Альт»',
                    'Опрос' => 'Обратная связь',
                ],
                'cta_label' => 'Пройти опрос',
                'cta_url' => $portal.'/s/…',
                'triggers' => [
                    'Из админки курса отправили приглашение на опрос (share → пригласить по почте).',
                    'Кнопка ведёт на персональную / быструю ссылку опроса.',
                ],
            ],
        ];

        foreach ($items as &$item) {
            $item['preview_html'] = self::renderPreview($item);
        }
        unset($item);

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function renderPreview(array $item): string
    {
        $ctaBtn = PortalMailAssets::resolveCtaButton((string) $item['cta_label']);
        $imgMap = self::assetUrlMap($ctaBtn['file'] ?? null);

        return view('emails.portal-notification', [
            'greeting' => (string) $item['greeting'],
            'lead' => (string) $item['lead'],
            'details' => $item['details'],
            'ctaUrl' => (string) $item['cta_url'],
            'ctaLabel' => (string) $item['cta_label'],
            'ctaButton' => $ctaBtn,
            'portalName' => 'Учебный портал practice.croc.ru',
            'headline' => (string) $item['headline'],
            'eyebrow' => (string) $item['eyebrow'],
            'img' => $imgMap,
        ])->render();
    }

    /**
     * @return array<string, string>
     */
    private static function assetUrlMap(?string $ctaFile = null): array
    {
        $map = [];
        foreach (PortalMailAssets::TEMPLATE_FILES as $file) {
            $map[$file] = asset('images/email/'.$file);
        }
        if ($ctaFile !== null && $ctaFile !== '') {
            $url = asset('images/email/'.$ctaFile);
            $map['cta-button.png'] = $url;
            $map[$ctaFile] = $url;
        }

        return $map;
    }
}
