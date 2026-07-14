<?php

namespace App\Services;

use App\Models\Okpd2TnvedMapping;
use App\Models\TnvedItem;

class Okpd2TnvedMappingImporter
{
    /**
     * @param  list<array{okpd2?: string, tnved?: string, note?: string}>  $rows
     * @return array{imported: int, skipped: int, unique: int}
     */
    public function import(array $rows, string $source, bool $replace = false, bool $bulk = false): array
    {
        if ($replace) {
            Okpd2TnvedMapping::query()->delete();
        }

        $nevacert = str_contains($source, 'nevacert');

        if ($bulk && ! $replace) {
            return $this->importBulk($rows, $source, $nevacert);
        }

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if ($this->persistRow($row, $source, str_contains($source, 'nevacert'))) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'unique' => Okpd2TnvedMapping::query()->count(),
        ];
    }

    /**
     * @param  list<array{okpd2?: string, tnved?: string, note?: string}>  $rows
     * @return array{imported: int, skipped: int, unique: int}
     */
    private function importBulk(array $rows, string $source, bool $nevacert = false): array
    {
        $payload = [];
        $skipped = 0;
        $now = now();

        foreach ($rows as $row) {
            $mapped = $this->mapRow($row, $nevacert);

            if ($mapped === null) {
                $skipped++;

                continue;
            }

            $payload[] = [
                ...$mapped,
                'source' => $source,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($payload, 500) as $chunk) {
            Okpd2TnvedMapping::query()->upsert(
                $chunk,
                ['okpd2_code', 'tnved_code'],
                ['source', 'note', 'updated_at'],
            );
        }

        return [
            'imported' => count($payload),
            'skipped' => $skipped,
            'unique' => Okpd2TnvedMapping::query()->count(),
        ];
    }

    /**
     * @param  array{okpd2?: string, tnved?: string, note?: string}  $row
     */
    private function persistRow(array $row, string $source, bool $nevacert = false): bool
    {
        $mapped = $this->mapRow($row, $nevacert);

        if ($mapped === null) {
            return false;
        }

        Okpd2TnvedMapping::query()->updateOrCreate(
            [
                'okpd2_code' => $mapped['okpd2_code'],
                'tnved_code' => $mapped['tnved_code'],
            ],
            [
                'source' => $source,
                'note' => $mapped['note'],
            ],
        );

        return true;
    }

    /**
     * @param  array{okpd2?: string, tnved?: string, note?: string}  $row
     * @return array{okpd2_code: string, tnved_code: string, note: ?string}|null
     */
    private function mapRow(array $row, bool $nevacert = false): ?array
    {
        $okpd2Code = $nevacert
            ? $this->normalizeOkpd2ForNevacert($row['okpd2'] ?? '')
            : $this->normalizeOkpd2Code($row['okpd2'] ?? '');
        $tnvedCode = $this->normalizeTnvedFromMapping($row['tnved'] ?? '');

        if ($okpd2Code === '' || $tnvedCode === '' || $tnvedCode === '0000000000') {
            return null;
        }

        if (! $nevacert && ! $this->isValidOkpd2Code($okpd2Code)) {
            return null;
        }

        return [
            'okpd2_code' => $okpd2Code,
            'tnved_code' => $tnvedCode,
            'note' => $row['note'] ?? null,
        ];
    }

    /**
     * Nevacert отдаёт ОКПД2 в формате XX.XX.YYYY — третья часть это год, реальный код: XX.XX.YY.
     * Текстовые коды («прочие») сохраняются как есть.
     */
    public function normalizeOkpd2ForNevacert(string $code): string
    {
        $raw = trim($code);

        if ($raw === '') {
            return '';
        }

        $ascii = $this->normalizeOkpd2Code($raw);

        if ($ascii !== '' && $this->isValidOkpd2Code($ascii)) {
            return $ascii;
        }

        $parts = explode('.', $ascii);

        if (count($parts) === 3
            && ctype_digit($parts[0])
            && ctype_digit($parts[1])
            && ctype_digit($parts[2])
            && strlen($parts[2]) === 4
        ) {
            $converted = sprintf(
                '%02d.%02d.%02d',
                (int) $parts[0],
                (int) $parts[1],
                (int) substr($parts[2], 2, 2),
            );

            if ($this->isValidOkpd2Code($converted)) {
                return $converted;
            }
        }

        if (count($parts) === 3
            && ctype_digit($parts[0])
            && ctype_digit($parts[1])
            && ctype_digit($parts[2])
        ) {
            $converted = sprintf('%02d.%02d.%s', (int) $parts[0], (int) $parts[1], $parts[2]);

            if ($this->isValidOkpd2Code($converted)) {
                return $converted;
            }
        }

        if (count($parts) === 2 && ctype_digit($parts[0]) && ctype_digit($parts[1])) {
            $converted = sprintf('%02d.%02d', (int) $parts[0], (int) $parts[1]);

            if ($this->isValidOkpd2Code($converted)) {
                return $converted;
            }
        }

        return mb_substr($raw, 0, 32);
    }

    public function normalizeOkpd2Code(string $code): string
    {
        return preg_replace('/[^0-9A-Za-z\.]/', '', str_replace(' ', '', trim($code))) ?? '';
    }

    public function normalizeTnvedFromMapping(string $code): string
    {
        $digits = preg_replace('/\D/', '', trim($code)) ?? '';

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) <= 10) {
            return str_pad(substr($digits, 0, 10), 10, '0', STR_PAD_RIGHT);
        }

        return TnvedItem::normalizeCode($digits);
    }

    public function isValidOkpd2Code(string $code): bool
    {
        if ($code === '' || ! preg_match('/^\d{2}\.\d{2}(\.\d+)?/', $code)) {
            return false;
        }

        foreach (explode('.', $code) as $part) {
            if (strlen($part) >= 4) {
                return false;
            }
        }

        return true;
    }
}
