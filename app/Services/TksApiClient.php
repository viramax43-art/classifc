<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class TksApiClient
{
    public function getActiveBaseUrl(): string
    {
        return rtrim((string) config('tks.base_url'), '/');
    }

    public function getApiKey(): string
    {
        return (string) config('tks.api_key');
    }

    /**
     * @return array{version?: string, date?: string, VER?: string, DATE?: string}
     */
    public function getVersion(): array
    {
        $response = $this->request()->get($this->url('ver.json'));

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @return list<string>
     */
    public function getCodeList(): array
    {
        $response = $this->request()->get($this->url(''));

        $response->throw();

        $data = $response->json();

        if (! is_array($data)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => is_string($item) ? TnvedRowMapper::extractCode($item) : null,
            $data,
        )));
    }

    /**
     * @return array<string, mixed>
     */
    public function getCode(string $code): array
    {
        $normalized = TnvedRowMapper::normalizeCode($code);
        $response = $this->request()->get($this->url("{$normalized}.json"));

        $response->throw();

        return $response->json() ?? [];
    }

    public function downloadArchive(string $destinationPath): void
    {
        $response = $this->request()
            ->withOptions(['sink' => $destinationPath])
            ->get($this->url('archive.zip'));

        $response->throw();
    }

    private function url(string $suffix): string
    {
        $key = $this->getApiKey();

        return "{$this->getActiveBaseUrl()}/{$key}/{$suffix}";
    }

    private function request(): PendingRequest
    {
        return Http::timeout((int) config('tks.timeout', 120))
            ->retry(
                (int) config('tks.retries', 3),
                (int) config('tks.retry_delay', 2000),
                fn ($exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && ($exception->response?->serverError() ?? false)),
            )
            ->acceptJson();
    }
}
