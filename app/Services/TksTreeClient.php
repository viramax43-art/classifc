<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TksTreeClient
{
    public const ROOT_NODE_ID = 10;

    public function getRootNodes(): array
    {
        return $this->getBranch(self::ROOT_NODE_ID);
    }

    public function getBranch(int $nodeId): array
    {
        return Cache::remember(
            "tnved_tree_branch_{$nodeId}",
            (int) config('tks.tree_cache_ttl', 3600),
            fn () => $this->fetchBranch($nodeId),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(?string $code = null, ?string $searchstr = null): array
    {
        $code = $code !== null ? trim($code) : '';
        $searchstr = $searchstr !== null ? trim($searchstr) : '';

        if ($code === '' && $searchstr === '') {
            return [];
        }

        $query = array_filter([
            'code' => $code !== '' ? $code : null,
            'searchstr' => $searchstr !== '' ? $searchstr : null,
        ]);

        $response = $this->request()->get($this->url('search/'), $query);
        $response->throw();

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    public function clearCache(?int $nodeId = null): void
    {
        if ($nodeId === null) {
            Cache::forget('tnved_tree_branch_'.self::ROOT_NODE_ID);

            return;
        }

        Cache::forget("tnved_tree_branch_{$nodeId}");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchBranch(int $nodeId): array
    {
        $padded = str_pad((string) $nodeId, 8, '0', STR_PAD_LEFT);
        $response = $this->request()->get($this->url("{$padded}.json"));
        $response->throw();

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    private function url(string $suffix): string
    {
        $base = rtrim((string) config('tks.tree_base_url'), '/');
        $key = (string) config('tks.api_key');

        return "{$base}/{$key}/{$suffix}";
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
