<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class NevacertApiClient
{
    private const TYPE_TNVED_OKPD2 = 13;

    /**
     * @param  callable(int, int, int): void|null  $onPage
     * @return \Generator<int, list<array{okpd2: string, tnved: string, note: ?string}>>
     */
    public function fetchTnvedOkpd2Mappings(?callable $onPage = null, ?int $maxPages = null): \Generator
    {
        $page = 1;
        $lastPage = 1;
        $sortIndex = null;

        do {
            $response = $this->fetchPage($page, $sortIndex);
            $items = $this->mapItems($response['data'] ?? []);

            if ($onPage) {
                $onPage($page, (int) ($response['last_page'] ?? $page), count($items));
            }

            if ($items !== []) {
                yield $items;
            }

            $lastPage = (int) ($response['last_page'] ?? $page);
            $sortIndex = $response['sort_index'] ?? null;
            $page++;
        } while ($page <= $lastPage && ($maxPages === null || $page <= $maxPages));
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPage(int $page, mixed $sortIndex): array
    {
        $query = [
            'type' => self::TYPE_TNVED_OKPD2,
            'page' => $page,
        ];

        if ($sortIndex !== null) {
            $query['sort_index'] = is_string($sortIndex)
                ? $sortIndex
                : json_encode($sortIndex, JSON_UNESCAPED_UNICODE);
        }

        $response = Http::timeout(60)
            ->retry(3, 1500, fn ($exception) => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && ($exception->response?->serverError() ?? false)))
            ->withHeaders([
                'Accept' => 'application/json, text/plain, */*',
                'User-Agent' => 'Mozilla/5.0 (compatible; ClassificatorBot/1.0)',
                'Referer' => 'https://nevacert.ru/dokumenty/perekhodnye-klyuchi/tnved-okpd2',
            ])
            ->get('https://nevacert.ru/api/okdp-okdp2/search', $query);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @return list<array{okpd2: string, tnved: string, note: ?string}>
     */
    private function mapItems(array $data): array
    {
        $rows = [];

        foreach ($data as $item) {
            $okpd2 = trim((string) ($item['code_of_type2'] ?? ''));
            $tnved = filled($item['code_search'] ?? null)
                ? (string) $item['code_search']
                : trim((string) ($item['code_of_type1'] ?? ''));
            $comment = trim((string) ($item['comment'] ?? ''));
            $name = trim((string) ($item['name_of_type2'] ?? ''));

            if ($tnved === '') {
                continue;
            }

            if ($okpd2 === '') {
                $okpd2 = 'id:'.(int) ($item['id'] ?? 0);
            }

            $note = $comment !== '' ? $comment : ($name !== '' ? $name : null);

            $rows[] = [
                'okpd2' => $okpd2,
                'tnved' => $tnved,
                'note' => $note,
            ];
        }

        return $rows;
    }
}
