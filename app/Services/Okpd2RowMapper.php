<?php

namespace App\Services;

use App\Models\Okpd2Item;
use JsonException;
use RuntimeException;

class Okpd2RowMapper
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public static function fromApiRow(array $row, ?\DateTimeInterface $now = null): ?array
    {
        $cells = $row['Cells'] ?? $row;

        return self::fromCells($cells, (int) ($row['Number'] ?? 0), $now);
    }

    /**
     * @param  array<string, mixed>  $cells
     * @return array<string, mixed>|null
     */
    public static function fromCells(array $cells, int $number = 0, ?\DateTimeInterface $now = null): ?array
    {
        $code = trim((string) ($cells['Kod'] ?? $cells['kod'] ?? $cells['code'] ?? ''));

        if ($code === '') {
            return null;
        }

        $timestamp = $now ?? now();

        return [
            'global_id' => (int) ($cells['global_id'] ?? $cells['Global_id'] ?? 0),
            'number' => $number,
            'name' => trim((string) ($cells['Name'] ?? $cells['name'] ?? '')),
            'idx' => trim((string) ($cells['Idx'] ?? $cells['idx'] ?? '')),
            'section' => strtoupper(trim((string) ($cells['Razdel'] ?? $cells['razdel'] ?? $cells['section'] ?? ''))),
            'code' => $code,
            'description' => trim((string) ($cells['Nomdescr'] ?? $cells['nomdescr'] ?? $cells['description'] ?? '')) ?: null,
            'ancestors_path' => null,
            'parent_code' => Okpd2Item::resolveParentCode($code),
            'level' => Okpd2Item::resolveLevel($code),
            'has_children' => false,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fromFile(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Файл не найден: {$path}");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'json' => self::fromJsonFile($path),
            'csv' => self::fromCsvFile($path),
            default => throw new RuntimeException("Неподдерживаемый формат .{$extension}. Используйте JSON или CSV."),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function fromJsonFile(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Не удалось прочитать файл: {$path}");
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Некорректный JSON: {$e->getMessage()}");
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('JSON должен содержать массив записей.');
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $decoded = $decoded['data'];
        }

        $batch = [];
        $now = now();
        $number = 0;

        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }

            $mapped = self::fromApiRow($row, $now);

            if ($mapped === null) {
                continue;
            }

            if ($mapped['number'] === 0) {
                $number++;
                $mapped['number'] = $number;
            }

            $batch[] = $mapped;
        }

        return $batch;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function fromCsvFile(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Не удалось открыть CSV: {$path}");
        }

        $headers = fgetcsv($handle, 0, ';') ?: fgetcsv($handle, 0, ',');

        if ($headers === false) {
            fclose($handle);

            throw new RuntimeException('CSV пуст или без заголовка.');
        }

        rewind($handle);
        $delimiter = str_contains((string) file_get_contents($path, false, null, 0, 1024), ';') ? ';' : ',';
        $headers = array_map(fn ($h) => trim((string) $h), fgetcsv($handle, 0, $delimiter) ?: []);

        $batch = [];
        $now = now();
        $number = 0;

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            $cells = [];

            foreach ($headers as $index => $header) {
                $cells[$header] = $line[$index] ?? '';
            }

            $mapped = self::fromCells($cells, ++$number, $now);

            if ($mapped !== null) {
                $batch[] = $mapped;
            }
        }

        fclose($handle);

        return $batch;
    }
}
