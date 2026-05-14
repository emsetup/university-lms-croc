<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Support\LearnerSsoDisplayNamePersistence;
use App\Support\OidcIdentityClaims;
use App\Support\OidcSignInRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OidcLoginController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->enabled()) {
            return redirect('/login');
        }

        $bounce = OidcSignInRedirect::oidcLoginUrl($request);
        if (str_starts_with($bounce, 'http://') || str_starts_with($bounce, 'https://')) {
            $hint = $this->sanitizedLoginHint($request);
            if ($hint !== '') {
                $bounce .= (str_contains($bounce, '?') ? '&' : '?').http_build_query(['login_hint' => $hint], '', '&', PHP_QUERY_RFC3986);
            }

            return redirect()->away($bounce);
        }

        $cfg = $this->discovery();
        $authorize = (string) ($cfg['authorization_endpoint'] ?? '');
        if ($authorize === '') {
            return redirect('/login')->withErrors(['oidc' => 'OIDC: не найден authorization_endpoint']);
        }

        $clientId = $this->clientId();
        if ($clientId === '') {
            return redirect('/login')->withErrors(['oidc' => 'OIDC: не задан client_id']);
        }

        $redirectUri = $this->redirectUriForRequest($request);
        if ($redirectUri === '') {
            return redirect('/login')->withErrors(['oidc' => 'OIDC: redirect_uri не разрешён для хоста']);
        }

        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $request->session()->put('oidc_state', $state);
        $request->session()->put('oidc_nonce', $nonce);

        $scope = $this->scope();
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state,
            'nonce' => $nonce,
        ];

        $loginHint = $this->sanitizedLoginHint($request);
        if ($loginHint !== '') {
            $params['login_hint'] = $loginHint;
        }

        return redirect()->away($authorize.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->enabled()) {
            return redirect('/login');
        }

        $state = (string) $request->query('state', '');
        $expectedState = (string) $request->session()->pull('oidc_state', '');
        if ($state === '' || $expectedState === '' || ! hash_equals($expectedState, $state)) {
            return redirect('/login')->withErrors(['oidc' => 'OIDC: неверный state']);
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            $desc = (string) $request->query('error_description', '');
            $err = (string) $request->query('error', '');
            $msg = trim('OIDC: ошибка авторизации: '.$err.' '.$desc);
            return redirect('/login')->withErrors(['oidc' => $msg !== '' ? $msg : 'OIDC: ошибка авторизации']);
        }

        $cfg = $this->discovery();
        $tokenEndpoint = (string) ($cfg['token_endpoint'] ?? '');
        if ($tokenEndpoint === '') {
            return redirect('/login')->withErrors(['oidc' => 'OIDC: не найден token_endpoint']);
        }

        $redirectUri = $this->redirectUriForRequest($request);
        if ($redirectUri === '') {
            return redirect('/login')->withErrors(['oidc' => 'OIDC: redirect_uri не разрешён для хоста']);
        }

        $token = $this->exchangeCode($tokenEndpoint, $code, $redirectUri);
        if (($token['ok'] ?? false) !== true) {
            return redirect('/login')->withErrors(['oidc' => (string) ($token['error'] ?? 'OIDC: ошибка обмена кода')]);
        }

        $idToken = (string) ($token['id_token'] ?? '');
        if ($idToken === '') {
            return redirect('/login')->withErrors(['oidc' => 'OIDC: отсутствует id_token']);
        }

        $expectedNonce = (string) $request->session()->pull('oidc_nonce', '');
        $claims = $this->validateIdToken($idToken, $expectedNonce);
        if (($claims['ok'] ?? false) !== true) {
            return redirect('/login')->withErrors(['oidc' => (string) ($claims['error'] ?? 'OIDC: неверный id_token')]);
        }

        $idClaims = (array) ($claims['claims'] ?? []);
        $mergedClaims = $this->mergeUserInfoClaims($cfg, $idClaims, (string) ($token['access_token'] ?? ''));

        $email = OidcIdentityClaims::email($mergedClaims);
        if ($email === '') {
            return redirect('/login')->withErrors(['oidc' => 'OIDC: не удалось получить email/логин пользователя']);
        }

        $learner = Learner::firstOrCreate(['email' => strtolower($email)]);

        $currentLearnerId = (int) session('learner_id', 0);
        if ($currentLearnerId > 0 && $currentLearnerId !== (int) $learner->id) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        session(['learner_id' => $learner->id]);

        // Снимок claim’ов для главной / отладки (обновляется при каждом SSO).
        session(['oidc_identity_probe_claims' => $mergedClaims]);

        $name = OidcIdentityClaims::displayName($mergedClaims);
        if ($name !== '') {
            session(['learner_name' => $name]);
        } else {
            $request->session()->forget('learner_name');
        }

        LearnerSsoDisplayNamePersistence::syncIfPossible($learner);

        return redirect('/');
    }

    private function enabled(): bool
    {
        return (bool) config('oidc.enabled', false);
    }

    private function discoveryUrl(): string
    {
        return (string) config('oidc.discovery_url', '');
    }

    private function clientId(): string
    {
        return (string) config('oidc.client_id', '');
    }

    private function clientSecret(): string
    {
        return (string) config('oidc.client_secret', '');
    }

    private function issuerExpected(): string
    {
        return (string) config('oidc.issuer', '');
    }

    private function scope(): string
    {
        return (string) config('oidc.scope', 'openid profile email');
    }

    /**
     * Подсказка для IdP (OIDC login_hint): ADFS может подставить UPN на своей странице входа.
     * Пароль на портале не собираем и не передаём.
     */
    private function sanitizedLoginHint(Request $request): string
    {
        $raw = trim((string) $request->query('login_hint', ''));
        if ($raw === '') {
            return '';
        }
        $domain = (string) config('course.email_domain', '');
        if ($domain === '') {
            return '';
        }
        $d = preg_quote(strtolower($domain), '/');
        if (! preg_match('/^[^@\s]+@'.$d.'$/i', $raw)) {
            return '';
        }

        return strtolower($raw);
    }

    private function allowedRedirectHosts(): array
    {
        $hosts = config('oidc.redirect_hosts', []);
        if (! is_array($hosts)) {
            return [];
        }

        return array_values(array_unique($hosts));
    }

    private function redirectUriForRequest(Request $request): string
    {
        $fixed = trim((string) config('oidc.redirect_uri', ''));
        if ($fixed !== '') {
            return $fixed;
        }

        $host = strtolower((string) $request->getHost());
        if ($host === '' || ! in_array($host, $this->allowedRedirectHosts(), true)) {
            return '';
        }

        // Prefer HTTPS on the public domain; allow HTTP for the stand IP.
        $scheme = $request->isSecure() ? 'https' : 'http';
        if ($host === 'practice.croc.ru') {
            $scheme = 'https';
        }

        return $scheme.'://'.$host.'/oidc/callback';
    }

    private function discovery(): array
    {
        $url = $this->discoveryUrl();
        $key = 'oidc:discovery:'.sha1($url);

        /** @var array $cfg */
        $cfg = Cache::remember($key, 3600, function () use ($url) {
            $json = $this->httpGetJson($url);
            return is_array($json) ? $json : [];
        });

        return is_array($cfg) ? $cfg : [];
    }

    private function jwks(): array
    {
        $cfg = $this->discovery();
        $jwksUri = (string) ($cfg['jwks_uri'] ?? '');
        if ($jwksUri === '') {
            return [];
        }

        $key = 'oidc:jwks:'.sha1($jwksUri);
        /** @var array $jwks */
        $jwks = Cache::remember($key, 3600, function () use ($jwksUri) {
            $json = $this->httpGetJson($jwksUri);
            return is_array($json) ? $json : [];
        });

        return is_array($jwks) ? $jwks : [];
    }

    private function exchangeCode(string $tokenEndpoint, string $code, string $redirectUri): array
    {
        $clientId = $this->clientId();
        $secret = $this->clientSecret();

        $body = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ], '', '&', PHP_QUERY_RFC3986);

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ];

        if ($clientId !== '' && $secret !== '') {
            $headers[] = 'Authorization: Basic '.base64_encode($clientId.':'.$secret);
        }

        $res = $this->httpPostJson($tokenEndpoint, $body, $headers);
        if (! is_array($res)) {
            return ['ok' => false, 'error' => 'OIDC: token_endpoint вернул некорректный ответ'];
        }
        if (isset($res['error'])) {
            $msg = (string) ($res['error_description'] ?? $res['error']);
            return ['ok' => false, 'error' => 'OIDC: '.$msg];
        }

        return [
            'ok' => true,
            'id_token' => (string) ($res['id_token'] ?? ''),
            'access_token' => (string) ($res['access_token'] ?? ''),
        ];
    }

    private function validateIdToken(string $jwt, string $expectedNonce): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return ['ok' => false, 'error' => 'OIDC: неверный формат JWT'];
        }
        [$h64, $p64, $s64] = $parts;

        $header = json_decode($this->b64urlDecode($h64), true);
        $payload = json_decode($this->b64urlDecode($p64), true);
        $sig = $this->b64urlDecode($s64);

        if (! is_array($header) || ! is_array($payload) || ! is_string($sig)) {
            return ['ok' => false, 'error' => 'OIDC: не удалось прочитать JWT'];
        }

        $alg = (string) ($header['alg'] ?? '');
        $kid = (string) ($header['kid'] ?? '');
        if ($alg !== 'RS256') {
            return ['ok' => false, 'error' => 'OIDC: неподдерживаемый alg (ожидается RS256)'];
        }
        if ($kid === '') {
            return ['ok' => false, 'error' => 'OIDC: отсутствует kid'];
        }

        $pem = $this->pemForKid($kid);
        if ($pem === '') {
            return ['ok' => false, 'error' => 'OIDC: не найден ключ подписи (kid)'];
        }

        $data = $h64.'.'.$p64;
        $ok = openssl_verify($data, $sig, $pem, OPENSSL_ALGO_SHA256) === 1;
        if (! $ok) {
            return ['ok' => false, 'error' => 'OIDC: подпись id_token не прошла проверку'];
        }

        $now = time();
        $iss = (string) ($payload['iss'] ?? '');
        $aud = $payload['aud'] ?? null;
        $exp = (int) ($payload['exp'] ?? 0);
        $nonce = (string) ($payload['nonce'] ?? '');

        $issuerExpected = $this->issuerExpected();
        if ($issuerExpected !== '' && $iss !== $issuerExpected) {
            return ['ok' => false, 'error' => 'OIDC: неверный issuer'];
        }

        $clientId = $this->clientId();
        if ($clientId !== '') {
            $audOk = false;
            if (is_string($aud)) {
                $audOk = hash_equals($aud, $clientId);
            } elseif (is_array($aud)) {
                foreach ($aud as $a) {
                    if (is_string($a) && hash_equals($a, $clientId)) {
                        $audOk = true;
                        break;
                    }
                }
            }
            if (! $audOk) {
                return ['ok' => false, 'error' => 'OIDC: неверный audience'];
            }
        }

        if ($exp < ($now - 30)) {
            return ['ok' => false, 'error' => 'OIDC: id_token истёк'];
        }

        if ($expectedNonce !== '' && $nonce !== '' && ! hash_equals($expectedNonce, $nonce)) {
            return ['ok' => false, 'error' => 'OIDC: неверный nonce'];
        }

        return ['ok' => true, 'claims' => $payload];
    }

    private function pemForKid(string $kid): string
    {
        $jwks = $this->jwks();
        $keys = $jwks['keys'] ?? null;
        if (! is_array($keys)) {
            return '';
        }

        foreach ($keys as $k) {
            if (! is_array($k)) {
                continue;
            }
            if ((string) ($k['kid'] ?? '') !== $kid) {
                continue;
            }
            if ((string) ($k['kty'] ?? '') !== 'RSA') {
                continue;
            }
            $n = (string) ($k['n'] ?? '');
            $e = (string) ($k['e'] ?? '');
            if ($n === '' || $e === '') {
                continue;
            }
            return $this->rsaJwkToPem($n, $e);
        }

        return '';
    }

    private function rsaJwkToPem(string $nB64Url, string $eB64Url): string
    {
        $n = $this->b64urlDecode($nB64Url);
        $e = $this->b64urlDecode($eB64Url);

        // ASN.1 DER for RSA public key (SubjectPublicKeyInfo)
        $modulus = $this->asn1EncodeInteger($n);
        $exponent = $this->asn1EncodeInteger($e);
        $rsaPubKey = $this->asn1EncodeSequence($modulus.$exponent);

        // AlgorithmIdentifier for rsaEncryption (OID 1.2.840.113549.1.1.1) with NULL params
        $algId = $this->asn1EncodeSequence(
            $this->asn1EncodeOid("\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01"). // 1.2.840.113549.1.1.1
            $this->asn1EncodeNull()
        );

        $spki = $this->asn1EncodeSequence(
            $algId.
            $this->asn1EncodeBitString($rsaPubKey)
        );

        $pem = "-----BEGIN PUBLIC KEY-----\n".
            chunk_split(base64_encode($spki), 64, "\n").
            "-----END PUBLIC KEY-----\n";

        return $pem;
    }

    private function asn1EncodeLength(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }
        $bin = ltrim(pack('N', $len), "\x00");
        return chr(0x80 | strlen($bin)).$bin;
    }

    private function asn1EncodeInteger(string $bytes): string
    {
        if ($bytes === '') {
            $bytes = "\x00";
        }
        // Ensure positive integer.
        if ((ord($bytes[0]) & 0x80) === 0x80) {
            $bytes = "\x00".$bytes;
        }
        return "\x02".$this->asn1EncodeLength(strlen($bytes)).$bytes;
    }

    private function asn1EncodeSequence(string $inner): string
    {
        return "\x30".$this->asn1EncodeLength(strlen($inner)).$inner;
    }

    private function asn1EncodeNull(): string
    {
        return "\x05\x00";
    }

    private function asn1EncodeOid(string $oidBytes): string
    {
        return "\x06".$this->asn1EncodeLength(strlen($oidBytes)).$oidBytes;
    }

    private function asn1EncodeBitString(string $inner): string
    {
        // Prepend 0 unused bits count byte.
        $v = "\x00".$inner;
        return "\x03".$this->asn1EncodeLength(strlen($v)).$v;
    }

    private function b64urlDecode(string $s): string
    {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad > 0) {
            $s .= str_repeat('=', 4 - $pad);
        }
        $out = base64_decode($s, true);
        return is_string($out) ? $out : '';
    }

    private function httpGetJson(string $url): mixed
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 6,
                'header' => "Accept: application/json\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        return json_decode($raw, true);
    }

    /**
     * @param  array<string, mixed>  $idClaims
     * @return array<string, mixed>
     */
    private function mergeUserInfoClaims(array $discovery, array $idClaims, string $accessToken): array
    {
        $url = trim((string) ($discovery['userinfo_endpoint'] ?? ''));
        if ($url === '' || $accessToken === '') {
            return $idClaims;
        }
        $ui = $this->httpGetJsonBearer($url, $accessToken);
        if (! is_array($ui)) {
            return $idClaims;
        }

        return array_merge($idClaims, $ui);
    }

    private function httpGetJsonBearer(string $url, string $accessToken): mixed
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 6,
                'header' => "Accept: application/json\r\nAuthorization: Bearer ".$accessToken."\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return json_decode($raw, true);
    }

    private function httpPostJson(string $url, string $body, array $headers): mixed
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 8,
                'header' => implode("\r\n", $headers)."\r\n",
                'content' => $body,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        return json_decode($raw, true);
    }
}

