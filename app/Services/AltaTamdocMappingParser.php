<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;

class AltaTamdocMappingParser
{
    /**
     * @return list<array{okpd2: string, tnved: string}>
     */
    public function parseHtml(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $rows = $xpath->query("//table[contains(@class,'ordw-table-1')]//tr[contains(@class,'ordw-r')]");

        if ($rows === false) {
            return [];
        }

        $mappings = [];
        $lastOkpd2 = '';
        $headerPassed = false;

        foreach ($rows as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $cells = $row->getElementsByTagName('td');
            $cellCount = $cells->length;

            if ($cellCount < 2) {
                continue;
            }

            $okpd2Cell = '';
            $tnvedNode = null;

            if ($cellCount >= 4) {
                $okpd2Cell = $this->cellText($cells->item(0));
                $tnvedNode = $cells->item(2);
            } elseif ($cellCount === 2) {
                $tnvedNode = $cells->item(0);
            } else {
                continue;
            }

            if ($this->isOkpd2Header($okpd2Cell)) {
                $headerPassed = true;

                continue;
            }

            if (mb_strtoupper(trim($okpd2Cell)) === 'ОКПД2' || mb_strtoupper(trim($okpd2Cell)) === 'ТН ВЭД') {
                continue;
            }

            if (! $headerPassed) {
                continue;
            }

            if (preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $okpd2Cell)) {
                $lastOkpd2 = $okpd2Cell;
            }

            if ($lastOkpd2 === '' || ! $tnvedNode instanceof DOMElement) {
                continue;
            }

            foreach ($this->extractTnvedCodes($tnvedNode) as $tnvedDigits) {
                $mappings[] = [
                    'okpd2' => $lastOkpd2,
                    'tnved' => $tnvedDigits,
                ];
            }
        }

        return $mappings;
    }

    /**
     * @return list<array{okpd2: string, tnved: string}>
     */
    public function parseText(string $text): array
    {
        $mappings = [];
        $lastOkpd2 = '';
        $headerPassed = false;

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_contains(mb_strtolower($line), 'код') && str_contains(mb_strtolower($line), '(6')) {
                $headerPassed = true;

                continue;
            }

            if (! $headerPassed) {
                continue;
            }

            if (preg_match('/^\|\s*(\d{2}\.\d{2}\.\d{2})\s*\|/', $line, $matches)) {
                $lastOkpd2 = $matches[1];

                continue;
            }

            if (preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $line)) {
                $lastOkpd2 = $line;

                continue;
            }

            if ($lastOkpd2 === '') {
                continue;
            }

            if (preg_match_all('/\(\/tnved\/\?tnved=(\d+)\)/', $line, $linkMatches)) {
                foreach ($linkMatches[1] as $digits) {
                    $mappings[] = ['okpd2' => $lastOkpd2, 'tnved' => $digits];
                }

                continue;
            }

            if (preg_match('/^(\d{4}\s\d{2})$/', $line, $matches)) {
                $mappings[] = [
                    'okpd2' => $lastOkpd2,
                    'tnved' => str_replace(' ', '', $matches[1]),
                ];
            }
        }

        return $mappings;
    }

    /**
     * @return list<string>
     */
    private function extractTnvedCodes(DOMElement $cell): array
    {
        $codes = [];
        $links = $cell->getElementsByTagName('a');

        foreach ($links as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            if (! str_contains($link->getAttribute('class'), 'ordw-tnved')) {
                continue;
            }

            if (preg_match('/tnved=(\d+)/', $link->getAttribute('href'), $matches)) {
                $codes[] = $matches[1];
            }
        }

        if ($codes !== []) {
            return array_values(array_unique($codes));
        }

        $text = $this->cellText($cell);

        if (preg_match('/^(\d{4}\s\d{2})$/', $text, $matches)) {
            return [str_replace(' ', '', $matches[1])];
        }

        return [];
    }

    private function cellText(?DOMElement $cell): string
    {
        if (! $cell) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', $cell->textContent ?? '') ?? '');
    }

    private function isOkpd2Header(string $text): bool
    {
        $lower = mb_strtolower(trim($text));

        return str_contains($lower, 'код')
            && str_contains($lower, '(6')
            && str_contains($lower, 'зн');
    }
}
