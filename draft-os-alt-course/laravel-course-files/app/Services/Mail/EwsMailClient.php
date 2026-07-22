<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Отправка писем через Exchange Web Services (SOAP CreateItem / SendAndSaveCopy).
 */
final class EwsMailClient
{
    /**
     * @param  list<string>  $to
     * @param  list<array{cid: string, name: string, path: string, content_type?: string}>  $inlineImages
     * @return array{ok: bool, error: ?string, raw: ?string}
     */
    public function send(
        array $to,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        ?string $fromAddress = null,
        ?string $fromName = null,
        array $inlineImages = [],
    ): array {
        $to = array_values(array_unique(array_filter(array_map(
            static fn ($e) => mb_strtolower(trim((string) $e)),
            $to
        ), static fn (string $e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))));

        if ($to === []) {
            return ['ok' => false, 'error' => 'Нет валидных получателей', 'raw' => null];
        }

        $url = trim((string) config('portal_mail.ews_url', ''));
        $user = trim((string) config('portal_mail.username', ''));
        $pass = (string) config('portal_mail.password', '');
        if ($url === '' || $user === '' || $pass === '') {
            return ['ok' => false, 'error' => 'EWS не настроен (PORTAL_MAIL_*)', 'raw' => null];
        }

        $fromAddress = $fromAddress ?: (string) config('portal_mail.from_address', $user);
        $fromName = $fromName ?: (string) config('portal_mail.from_name', 'Учебный портал');
        $timeout = max(5, (int) config('portal_mail.timeout', 25));
        $verifySsl = (bool) config('portal_mail.verify_ssl', false);

        $soap = $this->buildCreateItemSoap(
            $to,
            $subject,
            $bodyHtml,
            $bodyText,
            $fromAddress,
            $fromName,
            $inlineImages,
        );

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init failed', 'raw' => null];
        }

        $headers = [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: http://schemas.microsoft.com/exchange/services/2006/messages/CreateItem',
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $soap,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_USERPWD => $user.':'.$pass,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_USERAGENT => 'PracticePortal-EWS/1.0',
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'error' => 'cURL '.$errno.': '.$err, 'raw' => is_string($raw) ? $raw : null];
        }

        $body = is_string($raw) ? $raw : '';
        if ($http >= 400) {
            return ['ok' => false, 'error' => 'HTTP '.$http, 'raw' => $body];
        }

        if (
            str_contains($body, 'ResponseClass="Success"')
            || str_contains($body, "ResponseClass='Success'")
        ) {
            return ['ok' => true, 'error' => null, 'raw' => $body];
        }

        $code = null;
        if (preg_match('/ResponseCode>([^<]+)/', $body, $m)) {
            $code = trim($m[1]);
        }
        $msg = null;
        if (preg_match('/MessageText>([^<]+)/', $body, $m)) {
            $msg = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        return [
            'ok' => false,
            'error' => trim(($code ?? 'EWS_ERROR').($msg ? ': '.$msg : '')),
            'raw' => $body,
        ];
    }

    /**
     * @param  list<string>  $to
     * @param  list<array{cid: string, name: string, path: string, content_type?: string}>  $inlineImages
     */
    private function buildCreateItemSoap(
        array $to,
        string $subject,
        string $bodyHtml,
        ?string $bodyText,
        string $fromAddress,
        string $fromName,
        array $inlineImages,
    ): string {
        $recipients = '';
        foreach ($to as $email) {
            $recipients .= '<t:Mailbox><t:EmailAddress>'.$this->xml($email).'</t:EmailAddress></t:Mailbox>';
        }

        $bodyType = 'HTML';
        $body = $bodyHtml;
        if (trim(strip_tags($bodyHtml)) === '' && $bodyText !== null && $bodyText !== '') {
            $bodyType = 'Text';
            $body = $bodyText;
        }

        $attachmentsXml = $this->buildInlineAttachmentsXml($inlineImages);

        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            .' xmlns:m="http://schemas.microsoft.com/exchange/services/2006/messages"'
            .' xmlns:t="http://schemas.microsoft.com/exchange/services/2006/types"'
            .' xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soap:Header><t:RequestServerVersion Version="Exchange2013_SP1"/></soap:Header>'
            .'<soap:Body>'
            .'<m:CreateItem MessageDisposition="SendAndSaveCopy">'
            .'<m:SavedItemFolderId><t:DistinguishedFolderId Id="sentitems"/></m:SavedItemFolderId>'
            .'<m:Items><t:Message>'
            .'<t:Subject>'.$this->xml($subject).'</t:Subject>'
            .'<t:Body BodyType="'.$bodyType.'">'.$this->xml($body).'</t:Body>'
            .'<t:ToRecipients>'.$recipients.'</t:ToRecipients>'
            .'<t:From><t:Mailbox>'
            .'<t:Name>'.$this->xml($fromName).'</t:Name>'
            .'<t:EmailAddress>'.$this->xml($fromAddress).'</t:EmailAddress>'
            .'</t:Mailbox></t:From>'
            .$attachmentsXml
            .'</t:Message></m:Items>'
            .'</m:CreateItem>'
            .'</soap:Body></soap:Envelope>';
    }

    /**
     * @param  list<array{cid: string, name: string, path: string, content_type?: string}>  $inlineImages
     */
    private function buildInlineAttachmentsXml(array $inlineImages): string
    {
        if ($inlineImages === []) {
            return '';
        }

        $parts = '';
        foreach ($inlineImages as $img) {
            $path = (string) ($img['path'] ?? '');
            $cid = trim((string) ($img['cid'] ?? ''));
            $name = trim((string) ($img['name'] ?? ($cid !== '' ? $cid.'.png' : 'image.png')));
            if ($path === '' || $cid === '' || ! is_file($path) || ! is_readable($path)) {
                continue;
            }
            $bin = file_get_contents($path);
            if ($bin === false || $bin === '') {
                continue;
            }
            $ctype = (string) ($img['content_type'] ?? 'image/png');
            $parts .= '<t:FileAttachment>'
                .'<t:Name>'.$this->xml($name).'</t:Name>'
                .'<t:ContentType>'.$this->xml($ctype).'</t:ContentType>'
                .'<t:ContentId>'.$this->xml($cid).'</t:ContentId>'
                .'<t:IsInline>true</t:IsInline>'
                .'<t:Content>'.base64_encode($bin).'</t:Content>'
                .'</t:FileAttachment>';
        }

        if ($parts === '') {
            return '';
        }

        return '<t:Attachments>'.$parts.'</t:Attachments>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
