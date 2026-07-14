<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class DataMosApiClient
{
    private string $baseUrl;

    private string $apiKey;

    private int $datasetId;

    /** @var list<string> */
    private array $baseUrls;

    public function __construct()
    {
        $this->apiKey = (string) config('datamos.api_key');
        $this->datasetId = (int) config('datamos.dataset_id');
        $this->baseUrls = $this->resolveBaseUrls();
        $this->baseUrl = $this->baseUrls[0];

        if ($this->apiKey === '') {
            throw new RuntimeException('DATAMOS_API_KEY is not configured.');
        }
    }

    public function getActiveBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return list<string>
     */
    public function getBaseUrls(): array
    {
        return $this->baseUrls;
    }

    public function getDataset(): array
    {
        return $this->request("/datasets/{$this->datasetId}");
    }

    public function getCount(): int
    {
        $response = $this->request("/datasets/{$this->datasetId}/count");

        return (int) ($response['count'] ?? $response['Count'] ?? 0);
    }

    public function getRows(int $skip = 1, int $top = 1000): array
    {
        return $this->request("/datasets/{$this->datasetId}/rows", $this->rowsQuery($skip, $top));
    }

    /**
     * @param  list<int>  $skips
     * @return array<int, list<array<string, mixed>>>
     */
    public function getRowsConcurrent(array $skips, int $top = 1000): array
    {
        if ($skips === []) {
            return [];
        }

        if (count($skips) === 1) {
            $skip = $skips[0];

            return [$skip => $this->getRows($skip, $top)];
        }

        $path = "/datasets/{$this->datasetId}/rows";
        $timeout = (int) config('datamos.timeout', 120);
        $lastException = null;

        foreach ($this->baseUrls as $candidateBaseUrl) {
            $url = rtrim($candidateBaseUrl, '/').$path;

            try {
                $responses = Http::pool(function (Pool $pool) use ($skips, $top, $url, $timeout) {
                    foreach ($skips as $skip) {
                        $pool->as((string) $skip)
                            ->timeout($timeout)
                            ->connectTimeout(min($timeout, 30))
                            ->get($url, $this->rowsQuery($skip, $top));
                    }
                });

                $result = $this->parsePoolResponses($responses, $skips);
                $this->baseUrl = $candidateBaseUrl;

                return $result;
            } catch (ConnectionException|RequestException $e) {
                $lastException = $e;
            } catch (Throwable $e) {
                $lastException = new RuntimeException($e->getMessage(), 0, $e);
            }
        }

        if ($lastException instanceof ConnectionException || $lastException instanceof RequestException) {
            throw $lastException;
        }

        throw $lastException ?? new RuntimeException('DataMos API: all base URLs are unreachable.');
    }

    /**
     * @return array{ok: bool, base_url: string, status: ?int, error: ?string, elapsed_ms: float}
     */
    public function probeBaseUrl(string $baseUrl, int $timeoutSeconds = 10): array
    {
        $started = microtime(true);
        $url = rtrim($baseUrl, '/').'/version';

        try {
            $response = Http::timeout($timeoutSeconds)
                ->connectTimeout($timeoutSeconds)
                ->get($url, ['api_key' => $this->apiKey]);

            return [
                'ok' => $response->successful(),
                'base_url' => $baseUrl,
                'status' => $response->status(),
                'error' => $response->successful() ? null : mb_substr($response->body(), 0, 200),
                'elapsed_ms' => round((microtime(true) - $started) * 1000, 1),
            ];
        } catch (ConnectionException $e) {
            return [
                'ok' => false,
                'base_url' => $baseUrl,
                'status' => null,
                'error' => $this->humanizeConnectionError($e),
                'elapsed_ms' => round((microtime(true) - $started) * 1000, 1),
            ];
        }
    }

    private function request(string $path, array $query = []): array
    {
        $query['api_key'] = $this->apiKey;
        $lastException = null;

        foreach ($this->baseUrls as $candidateBaseUrl) {
            try {
                $response = $this->client()
                    ->get(rtrim($candidateBaseUrl, '/').$path, $query);

                if ($response->successful()) {
                    $this->baseUrl = $candidateBaseUrl;

                    return $response->json();
                }

                if ($response->status() >= 500) {
                    continue;
                }

                throw new RuntimeException(
                    "DataMos API error ({$response->status()}): ".$response->body()
                );
            } catch (ConnectionException $e) {
                $lastException = $e;
            }
        }

        if ($lastException) {
            throw $lastException;
        }

        throw new RuntimeException('DataMos API: all base URLs are unreachable.');
    }

    /**
     * @param  array<string, mixed>  $responses
     * @param  list<int>  $skips
     * @return array<int, list<array<string, mixed>>>
     */
    private function parsePoolResponses(array $responses, array $skips): array
    {
        $result = [];

        foreach ($skips as $skip) {
            $key = (string) $skip;
            $response = $responses[$key] ?? null;

            if ($response === null) {
                throw new RuntimeException("DataMos API: no response for page skip={$skip}.");
            }

            if ($response instanceof ConnectionException || $response instanceof RequestException) {
                throw $response;
            }

            if ($response instanceof Throwable) {
                throw new RuntimeException(
                    "DataMos API error for skip={$skip}: ".$response->getMessage(),
                    0,
                    $response
                );
            }

            if (! $response instanceof Response) {
                throw new RuntimeException("DataMos API: unexpected response type for skip={$skip}.");
            }

            if (! $response->successful()) {
                throw new RuntimeException(
                    "DataMos API error ({$response->status()}) for skip={$skip}: ".$response->body()
                );
            }

            $result[$skip] = $response->json();
        }

        ksort($result);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function rowsQuery(int $skip, int $top): array
    {
        return [
            '$skip' => max(1, $skip),
            '$top' => $top,
            '$orderby' => 'Kod',
            'api_key' => $this->apiKey,
        ];
    }

    private function client(): PendingRequest
    {
        $timeout = (int) config('datamos.timeout', 120);

        return Http::timeout($timeout)
            ->connectTimeout(min($timeout, 30))
            ->retry((int) config('datamos.retries', 5), (int) config('datamos.retry_delay', 2000));
    }

    /**
     * @return list<string>
     */
    private function resolveBaseUrls(): array
    {
        $configured = array_filter([
            config('datamos.base_url'),
            ...config('datamos.fallback_base_urls', []),
        ]);

        $unique = [];

        foreach ($configured as $url) {
            $normalized = rtrim((string) $url, '/');

            if ($normalized !== '' && ! in_array($normalized, $unique, true)) {
                $unique[] = $normalized;
            }
        }

        return $unique !== [] ? $unique : ['https://apidata.mos.ru/v1'];
    }

    private function humanizeConnectionError(ConnectionException $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'Could not resolve host')) {
            return 'DNS: хост не найден';
        }

        if (str_contains($message, 'Connection timed out') || str_contains($message, 'connect timeout')) {
            return 'TCP: соединение не установлено (хост недоступен из вашей сети)';
        }

        if (str_contains($message, 'SSL') || str_contains($message, 'certificate')) {
            return 'TLS: ошибка сертификата';
        }

        return mb_substr($message, 0, 160);
    }
}
