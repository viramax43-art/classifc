<?php

namespace App\Console\Commands;

use App\Models\Okpd2TnvedMapping;
use App\Models\TnvedItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ImportOkpd2TnvedMappingCommand extends Command
{
    protected $signature = 'mapping:import-okpd2-tnved
        {file : Путь к CSV/XLSX с соответствиями}
        {--replace : Очистить существующие соответствия перед импортом}
        {--source=mineconom : Источник данных}';

    protected $description = 'Импорт соответствий ОКПД2 ↔ ТН ВЭД из CSV или XLSX (Минэкономразвития)';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("Файл не найден: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readRows($path);

        if ($rows === []) {
            $this->error('Не найдено строк для импорта.');

            return self::FAILURE;
        }

        if ($this->option('replace')) {
            Okpd2TnvedMapping::query()->delete();
        }

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $okpd2Code = $this->normalizeOkpd2Code($row['okpd2'] ?? '');
            $tnvedCode = $this->normalizeTnvedFromMapping($row['tnved'] ?? '');

            if ($okpd2Code === '' || $tnvedCode === '' || $tnvedCode === '0000000000') {
                $skipped++;

                continue;
            }

            Okpd2TnvedMapping::query()->updateOrCreate(
                [
                    'okpd2_code' => $okpd2Code,
                    'tnved_code' => $tnvedCode,
                ],
                [
                    'source' => $this->option('source'),
                    'note' => $row['note'] ?? null,
                ],
            );

            $imported++;
        }

        $this->components->info("Импортировано: {$imported}, пропущено: {$skipped}");

        return self::SUCCESS;
    }

    /**
     * @return list<array{okpd2?: string, tnved?: string, note?: string}>
     */
    private function readRows(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx' => $this->readXlsx($path),
            default => $this->readCsv($path),
        };
    }

    /**
     * @return list<array{okpd2?: string, tnved?: string, note?: string}>
     */
    private function readCsv(string $path): array
    {
        $content = File::get($path);
        $delimiter = str_contains($content, ';') ? ';' : ',';
        $lines = preg_split('/\R/u', $content) ?: [];
        $rows = [];
        $header = null;
        $lastOkpd2 = '';

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $cells = str_getcsv($line, $delimiter);
            $lower = array_map(fn ($v) => mb_strtolower(trim((string) $v)), $cells);

            if ($header === null) {
                $header = $this->detectColumns($lower);

                if ($header !== null) {
                    continue;
                }

                $header = ['okpd2' => 0, 'tnved' => 1, 'note' => 2];
            }

            $okpd2Cell = trim((string) ($cells[$header['okpd2']] ?? ''));
            $tnvedCell = trim((string) ($cells[$header['tnved']] ?? ''));

            if ($okpd2Cell !== '') {
                $lastOkpd2 = $okpd2Cell;
            }

            if ($tnvedCell !== '' && $lastOkpd2 !== '') {
                $rows[] = [
                    'okpd2' => $lastOkpd2,
                    'tnved' => $tnvedCell,
                    'note' => isset($header['note']) ? ($cells[$header['note']] ?? null) : null,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{okpd2?: string, tnved?: string, note?: string}>
     */
    private function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            $this->error('ZipArchive недоступен для чтения XLSX.');

            return [];
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            $this->error('Не удалось открыть XLSX.');

            return [];
        }

        $sharedStrings = $this->loadSharedStrings($zip);
        $sheetPaths = $this->listWorksheetPaths($zip);
        $rows = [];

        foreach ($sheetPaths as $sheetPath) {
            $sheetRows = $this->readMappingSheet($zip, $sheetPath, $sharedStrings);
            $rows = array_merge($rows, $sheetRows);
        }

        $zip->close();

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function loadSharedStrings(ZipArchive $zip): array
    {
        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

        if (! $sharedXml) {
            return $sharedStrings;
        }

        $xml = simplexml_load_string($sharedXml);

        foreach ($xml->si ?? [] as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = trim((string) $si->t);
            } else {
                $text = '';
                foreach ($si->r ?? [] as $r) {
                    $text .= (string) $r->t;
                }
                $sharedStrings[] = trim($text);
            }
        }

        return $sharedStrings;
    }

    /**
     * @return list<string>
     */
    private function listWorksheetPaths(ZipArchive $zip): array
    {
        $paths = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (str_starts_with($name, 'xl/worksheets/sheet') && str_ends_with($name, '.xml')) {
                $paths[] = $name;
            }
        }

        sort($paths, SORT_NATURAL);

        return $paths;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return list<array{okpd2?: string, tnved?: string, note?: string}>
     */
    private function readMappingSheet(ZipArchive $zip, string $sheetPath, array $sharedStrings): array
    {
        $sheetXml = $zip->getFromName($sheetPath);

        if (! $sheetXml) {
            return [];
        }

        $xml = simplexml_load_string($sheetXml);
        $grid = [];

        foreach ($xml->sheetData->row ?? [] as $row) {
            $rowNum = (int) $row['r'];

            foreach ($row->c ?? [] as $cell) {
                preg_match('/([A-Z]+)(\d+)/', (string) $cell['r'], $matches);
                $col = $matches[1] ?? 'A';
                $grid[$rowNum][$col] = $this->cellValue($cell, $sharedStrings);
            }
        }

        if ($grid === []) {
            return [];
        }

        ksort($grid);

        $headerRowNum = null;
        $colOkpd2 = null;
        $colTnved = null;

        foreach ($grid as $rowNum => $cells) {
            $detected = $this->detectHeaderColumns($cells);

            if ($detected !== null) {
                $headerRowNum = $rowNum;
                $colOkpd2 = $detected['okpd2'];
                $colTnved = $detected['tnved'];
                break;
            }
        }

        if ($headerRowNum === null || $colOkpd2 === null || $colTnved === null) {
            return [];
        }

        $okpd2First = $this->columnIndex($colOkpd2) < $this->columnIndex($colTnved);
        $rows = [];
        $lastOkpd2 = '';
        $lastTnved = '';

        foreach ($grid as $rowNum => $cells) {
            if ($rowNum <= $headerRowNum) {
                continue;
            }

            $okpd2Cell = trim($cells[$colOkpd2] ?? '');
            $tnvedCell = trim($cells[$colTnved] ?? '');

            if ($okpd2First) {
                if ($okpd2Cell !== '') {
                    $lastOkpd2 = $okpd2Cell;
                }

                if ($tnvedCell !== '' && $lastOkpd2 !== '') {
                    $rows[] = ['okpd2' => $lastOkpd2, 'tnved' => $tnvedCell];
                }

                continue;
            }

            if ($tnvedCell !== '') {
                $lastTnved = $tnvedCell;
            }

            if ($okpd2Cell !== '' && $lastTnved !== '') {
                $rows[] = ['okpd2' => $okpd2Cell, 'tnved' => $lastTnved];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $cells
     * @return array{okpd2: string, tnved: string}|null
     */
    private function detectHeaderColumns(array $cells): ?array
    {
        $colOkpd2 = null;
        $colTnved = null;

        foreach ($cells as $col => $value) {
            $lower = mb_strtolower(trim($value));

            if (! str_contains($lower, 'код')) {
                continue;
            }

            if (str_contains($lower, 'окpd') || str_contains($lower, 'окпд')) {
                $colOkpd2 = $col;
            }

            if (str_contains($lower, 'тн') || str_contains($lower, 'tnved') || str_contains($lower, 'вэд')) {
                $colTnved = $col;
            }
        }

        if ($colOkpd2 === null || $colTnved === null || $colOkpd2 === $colTnved) {
            return null;
        }

        return ['okpd2' => $colOkpd2, 'tnved' => $colTnved];
    }

    private function cellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');
        $value = (string) ($cell->v ?? '');

        if ($type === 's') {
            return trim($sharedStrings[(int) $value] ?? $value);
        }

        if ($type === 'str') {
            return trim((string) ($cell->is->t ?? $value));
        }

        return trim($value);
    }

    /**
     * @param  list<string>  $lowerCells
     * @return array{okpd2: int, tnved: int, note?: int}|null
     */
    private function detectColumns(array $lowerCells): ?array
    {
        $okpd2 = null;
        $tnved = null;
        $note = null;

        foreach ($lowerCells as $index => $cell) {
            if (str_contains($cell, 'окpd') || str_contains($cell, 'окпд')) {
                $okpd2 = $index;
            }

            if (str_contains($cell, 'тн') || str_contains($cell, 'tnved') || str_contains($cell, 'вэд')) {
                $tnved = $index;
            }

            if (str_contains($cell, 'примеч') || str_contains($cell, 'note')) {
                $note = $index;
            }
        }

        if ($okpd2 === null || $tnved === null) {
            return null;
        }

        return array_filter([
            'okpd2' => $okpd2,
            'tnved' => $tnved,
            'note' => $note,
        ], fn ($v) => $v !== null);
    }

    private function normalizeOkpd2Code(string $code): string
    {
        return preg_replace('/[^0-9A-Za-z\.]/', '', str_replace(' ', '', trim($code))) ?? '';
    }

    private function normalizeTnvedFromMapping(string $code): string
    {
        $digits = preg_replace('/\D/', '', trim($code)) ?? '';

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) <= 6) {
            return str_pad($digits, 10, '0', STR_PAD_RIGHT);
        }

        return TnvedItem::normalizeCode($digits);
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;

        foreach (str_split(strtoupper($letters)) as $char) {
            $index = $index * 26 + (ord($char) - ord('A') + 1);
        }

        return $index - 1;
    }
}
