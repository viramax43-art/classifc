<?php

namespace App\Services;

use App\Models\TnvedItem;

class TnvedTreeMapper
{
    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public static function mapNode(array $node): array
    {
        $id = (int) ($node['ID'] ?? 0);
        $rawCode = trim((string) ($node['CODE'] ?? ''));
        $text = trim((string) ($node['TEXT'] ?? ''));
        $digits = preg_replace('/\D/', '', $rawCode) ?? '';
        $normalizedCode = $rawCode !== '' ? TnvedItem::normalizeCode($rawCode) : null;
        $sectionLabel = self::splitSectionLabel($text);
        $isGroup = $rawCode === '';
        $isLeaf = ! $isGroup && strlen($digits) >= 10;

        $displayCode = null;
        if ($normalizedCode) {
            $displayCode = TnvedItem::formatDisplayCode($normalizedCode);
        } elseif ($rawCode !== '') {
            $displayCode = $rawCode;
        }

        return [
            'id' => $id,
            'code' => $normalizedCode,
            'display_code' => $displayCode,
            'raw_code' => $rawCode !== '' ? $rawCode : null,
            'name' => $text,
            'section_label' => $sectionLabel['prefix'] ?? null,
            'section_title' => $sectionLabel['title'] ?? null,
            'is_section' => $sectionLabel !== null,
            'is_group' => $isGroup,
            'is_leaf' => $isLeaf,
            'nesting_level' => $normalizedCode ? TnvedItem::resolveLevel($normalizedCode) : null,
            'has_children' => $isGroup || (! $isLeaf && $rawCode !== ''),
            'date_begin' => $node['DBEGIN'] ?? null,
            'date_end' => $node['DEND'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    public static function mapNodes(array $nodes): array
    {
        return array_values(array_map(
            fn (array $node) => self::mapNode($node),
            $nodes,
        ));
    }

    /**
     * @return array{prefix: string, title: string}|null
     */
    public static function splitSectionLabel(string $text): ?array
    {
        if (! preg_match('/^(РАЗДЕЛ\s+([IVXLCDM]+)\.)\s*(.+)$/ui', $text, $matches)) {
            return null;
        }

        return [
            'prefix' => mb_strtoupper(trim($matches[1])),
            'roman' => mb_strtoupper(trim($matches[2])),
            'title' => trim($matches[3]),
        ];
    }
}
