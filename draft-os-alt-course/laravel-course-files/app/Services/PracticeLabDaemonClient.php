<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * HTTP-клиент к lab-daemon (внутренняя сеть). Секрет в заголовке Authorization.
 */
final class PracticeLabDaemonClient
{
    public function __construct(
        private string $baseUrl,
        private string $secret
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public static function fromConfig(): ?self
    {
        $url = config('practice_lab.daemon_url');
        $secret = (string) config('practice_lab.daemon_secret');
        if ($url === '' || $secret === '') {
            return null;
        }

        return new self($url, $secret);
    }

    public function createLab(int $learnerId, int $moduleId, string $image): array
    {
        $r = $this->post('/internal/v1/lab', [
            'learner_id' => $learnerId,
            'module_id' => $moduleId,
            'image' => $image,
        ]);
        $r->throw();

        return $r->json();
    }

    public function checkLab(string $labId): array
    {
        $r = $this->post("/internal/v1/lab/{$labId}/check", []);
        $r->throw();

        return $r->json();
    }

    /** Снимок команд из ~/.bash_history в контейнере (после проверок и перед destroy). */
    public function getBashHistory(string $labId): array
    {
        $r = Http::withHeaders($this->headers())
            ->timeout(60)
            ->get($this->baseUrl.'/internal/v1/lab/'.$labId.'/bash-history');
        $r->throw();

        return $r->json();
    }

    public function destroyLab(string $labId): void
    {
        $r = $this->delete("/internal/v1/lab/{$labId}");
        if ($r->status() === 404) {
            return;
        }
        $r->throw();
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->secret,
            'Accept' => 'application/json',
        ];
    }

    private function post(string $path, array $json): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout(120)
            ->post($this->baseUrl.$path, $json);
    }

    private function delete(string $path): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout(60)
            ->delete($this->baseUrl.$path);
    }
}
