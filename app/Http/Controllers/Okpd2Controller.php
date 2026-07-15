<?php

namespace App\Http\Controllers;

use App\Models\Okpd2Item;
use App\Models\Okpd2Meta;
use App\Models\Okpd2TnvedMapping;
use App\Models\TnvedItem;
use App\Support\ActualizationFormatter;
use App\Support\ClassifierUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class Okpd2Controller extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $code = trim((string) $request->query('code', ''));

        if ($code !== '') {
            $normalized = Okpd2Item::normalizeCodeQuery($code);

            if ($normalized !== '') {
                return ClassifierUrl::redirectTo(ClassifierUrl::okpd2PublicPath($normalized));
            }
        }

        return $this->renderIndex();
    }

    public function codePage(string $code): View|RedirectResponse
    {
        $item = Okpd2Item::query()->where('code', $code)->first();

        if (! $item) {
            abort(404);
        }

        return $this->renderIndex($item->code, $item);
    }

    private function renderIndex(?string $initialCode = null, ?Okpd2Item $seoItem = null): View
    {
        $meta = Okpd2Meta::query()->latest('id')->first();
        $sections = collect(config('okpd2.sections'))
            ->map(fn (string $name, string $code) => [
                'code' => $code,
                'name' => $name,
                'count' => Okpd2Item::query()->where('section', $code)->count(),
            ])
            ->filter(fn (array $section) => $section['count'] > 0)
            ->values();

        $seoTitle = 'ОКПД 2 — классификатор продукции';
        $seoDescription = 'Классификатор ОКПД 2: поиск по коду и названию, навигация по разделам, связи с ТН ВЭД.';

        if ($seoItem) {
            $seoTitle = 'ОКПД 2 '.$seoItem->code.' — '.$seoItem->name;
            $seoDescription = mb_substr(trim($seoItem->name), 0, 160);
        }

        return view('okpd2.index', [
            'meta' => $meta,
            'classifierUpdatedAt' => ActualizationFormatter::fromMeta($meta),
            'sections' => $sections,
            'totalCount' => Okpd2Item::query()->count(),
            'initialCode' => $initialCode,
            'seoTitle' => $seoTitle,
            'seoDescription' => $seoDescription,
            'canonicalUrl' => ClassifierUrl::absolute(
                ClassifierUrl::okpd2PublicPath($seoItem?->code),
            ),
        ]);
    }

    public function sections(): JsonResponse
    {
        $sections = collect(config('okpd2.sections'))
            ->map(fn (string $name, string $code) => [
                'code' => $code,
                'name' => $name,
                'count' => Okpd2Item::query()->where('section', $code)->count(),
            ])
            ->values();

        return response()->json(['sections' => $sections]);
    }

    public function children(Request $request): JsonResponse
    {
        $section = strtoupper((string) $request->query('section', ''));
        $parentCode = $request->query('parent');

        if ($parentCode) {
            $parent = Okpd2Item::query()->where('code', $parentCode)->first();
            $items = $parent ? $parent->children()->get() : collect();
        } elseif ($section !== '') {
            $items = Okpd2Item::query()
                ->where('section', $section)
                ->whereNull('parent_code')
                ->orderBy('code')
                ->get();
        } else {
            return response()->json(['items' => []]);
        }

        return response()->json([
            'items' => $items->map(fn (Okpd2Item $item) => $this->formatItem($item)),
        ]);
    }

    public function show(string $code): JsonResponse
    {
        $item = Okpd2Item::query()->where('code', $code)->first();

        if (! $item) {
            return response()->json(['message' => 'Код не найден'], 404);
        }

        $children = $item->children()->get();
        $siblings = $this->getSiblings($item);

        $breadcrumb = $item->breadcrumb();
        $schemeSiblings = $this->schemeSiblings($item);
        $relatedTnved = Okpd2TnvedMapping::query()
            ->where('okpd2_code', $item->code)
            ->orderBy('tnved_code')
            ->get()
            ->map(fn (Okpd2TnvedMapping $mapping) => [
                'code' => $mapping->tnved_code,
                'display_code' => TnvedItem::formatDisplayCode($mapping->tnved_code),
                'name' => $mapping->tnvedItem?->name,
            ]);

        return response()->json([
            'item' => [
                ...$this->formatItem($item),
                'description' => $item->description,
                'idx' => $item->idx,
                'ancestors_path' => $item->ancestors_path,
                'breadcrumb' => $breadcrumb,
            ],
            'children' => $children->map(fn (Okpd2Item $child) => $this->formatItem($child)),
            'siblings' => $siblings,
            'scheme_siblings' => $schemeSiblings,
            'related_tnved' => $relatedTnved,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $section = strtoupper((string) $request->query('section', ''));
        $limit = min((int) $request->query('limit', 50), 100);

        if ($query === '') {
            return response()->json(['items' => [], 'total' => 0]);
        }

        $normalizedCode = Okpd2Item::normalizeCodeQuery($query);
        $codeDigits = preg_replace('/[^0-9A-Za-z]/', '', $normalizedCode) ?? '';

        if ($this->canUseFts()) {
            return $this->searchWithFts($query, $normalizedCode, $codeDigits, $section, $limit);
        }

        return $this->searchWithLike($query, $normalizedCode, $codeDigits, $section, $limit);
    }

    public function meta(): JsonResponse
    {
        $meta = Okpd2Meta::query()->latest('id')->first();

        return response()->json([
            'meta' => $meta,
            'total' => Okpd2Item::query()->count(),
        ]);
    }

    private function canUseFts(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite'
            && $this->ftsTableExists();
    }

    private function ftsTableExists(): bool
    {
        static $exists = null;

        if ($exists === null) {
            $exists = (bool) DB::selectOne(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='okpd2_items_fts'"
            );
        }

        return $exists;
    }

    private function searchWithFts(
        string $query,
        string $normalizedCode,
        string $codeDigits,
        string $section,
        int $limit,
    ): JsonResponse {
        $ftsQuery = $this->buildFtsQuery($query, $normalizedCode);

        $builder = Okpd2Item::query()
            ->select('okpd2_items.*')
            ->join('okpd2_items_fts', 'okpd2_items.id', '=', 'okpd2_items_fts.rowid')
            ->whereRaw('okpd2_items_fts MATCH ?', [$ftsQuery]);

        if ($section !== '' && $section !== 'ALL') {
            $builder->where('okpd2_items.section', $section);
        }

        $total = (clone $builder)->count();

        $items = $builder
            ->orderByRaw('CASE
                WHEN okpd2_items.code = ? THEN 0
                WHEN okpd2_items.code LIKE ? THEN 1
                WHEN okpd2_items.idx LIKE ? THEN 2
                WHEN okpd2_items.name LIKE ? THEN 3
                ELSE 4
            END', [
                $normalizedCode,
                "{$normalizedCode}%",
                "{$codeDigits}%",
                "%{$query}%",
            ])
            ->orderBy('okpd2_items.code')
            ->limit($limit)
            ->get();

        return response()->json([
            'items' => $items->map(fn (Okpd2Item $item) => $this->formatItem($item, $query)),
            'total' => $total,
        ]);
    }

    private function searchWithLike(
        string $query,
        string $normalizedCode,
        string $codeDigits,
        string $section,
        int $limit,
    ): JsonResponse {
        $builder = Okpd2Item::query();

        if ($section !== '' && $section !== 'ALL') {
            $builder->where('section', $section);
        }

        $builder->where(function ($q) use ($query, $normalizedCode, $codeDigits) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('code', 'like', "{$normalizedCode}%")
                ->orWhere('idx', 'like', "%{$codeDigits}%")
                ->orWhere('ancestors_path', 'like', "%{$query}%");

            if (strlen($query) >= 3) {
                $q->orWhere('description', 'like', "%{$query}%");
            }
        });

        $total = (clone $builder)->count();

        $items = $builder
            ->orderByRaw('CASE
                WHEN code = ? THEN 0
                WHEN code LIKE ? THEN 1
                WHEN idx LIKE ? THEN 2
                WHEN name LIKE ? THEN 3
                ELSE 4
            END', [
                $normalizedCode,
                "{$normalizedCode}%",
                "{$codeDigits}%",
                "%{$query}%",
            ])
            ->orderBy('code')
            ->limit($limit)
            ->get();

        return response()->json([
            'items' => $items->map(fn (Okpd2Item $item) => $this->formatItem($item, $query)),
            'total' => $total,
        ]);
    }

    private function buildFtsQuery(string $query, string $normalizedCode): string
    {
        $terms = preg_split('/\s+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($terms === []) {
            return '"'.str_replace('"', '""', $normalizedCode).'"*';
        }

        $parts = [];

        foreach ($terms as $term) {
            $safe = preg_replace('/[^0-9A-Za-zА-Яа-яЁё\.]/u', '', $term) ?? '';

            if ($safe === '') {
                continue;
            }

            $parts[] = '"'.str_replace('"', '""', $safe).'"*';
        }

        if ($normalizedCode !== '' && ! in_array($normalizedCode, $terms, true)) {
            $parts[] = '"'.str_replace('"', '""', $normalizedCode).'"*';
        }

        return $parts === [] ? '""' : implode(' ', $parts);
    }

    /**
     * For each ancestor in the breadcrumb, return its sibling codes
     * so the scheme tree can show navigable peers at each level.
     */
    private function schemeSiblings(Okpd2Item $item): array
    {
        $result = [];
        $current = $item;

        while ($current) {
            $sibs = Okpd2Item::query()
                ->when(
                    $current->parent_code,
                    fn ($q) => $q->where('parent_code', $current->parent_code),
                    fn ($q) => $q->where('section', $current->section)->whereNull('parent_code'),
                )
                ->orderBy('code')
                ->get(['code', 'name', 'has_children']);

            $result[$current->code] = $sibs->map(fn (Okpd2Item $s) => [
                'code' => $s->code,
                'name' => $s->name,
                'has_children' => $s->has_children,
            ])->values()->all();

            $current = $current->parent_code
                ? Okpd2Item::query()->where('code', $current->parent_code)->first()
                : null;
        }

        return $result;
    }

    private function peerItems(Okpd2Item $item): \Illuminate\Support\Collection
    {
        return Okpd2Item::query()
            ->when(
                $item->parent_code,
                fn ($q) => $q->where('parent_code', $item->parent_code),
                fn ($q) => $q->where('section', $item->section)->whereNull('parent_code'),
            )
            ->orderBy('code')
            ->get();
    }

    private function getSiblings(Okpd2Item $item): array
    {
        $siblings = $this->peerItems($item);

        $index = $siblings->search(fn (Okpd2Item $sibling) => $sibling->code === $item->code);

        if ($index === false) {
            return [
                'prev' => null,
                'next' => null,
                'index' => null,
                'total' => 0,
            ];
        }

        return [
            'prev' => $index > 0 ? $siblings[$index - 1]->code : null,
            'next' => $index < $siblings->count() - 1 ? $siblings[$index + 1]->code : null,
            'index' => $index + 1,
            'total' => $siblings->count(),
        ];
    }

    private function formatItem(Okpd2Item $item, ?string $highlight = null): array
    {
        $nestingLevel = Okpd2Item::resolveLevel($item->code);

        return [
            'code' => $item->code,
            'name' => $item->name,
            'section' => $item->section,
            'section_name' => config('okpd2.sections.'.$item->section),
            'level' => $nestingLevel,
            'nesting_level' => $nestingLevel,
            'level_name' => Okpd2Item::resolveLevelName($nestingLevel),
            'parent_code' => $item->parent_code,
            'has_children' => $item->has_children,
            'has_description' => filled($item->description),
            'ancestors_path' => $item->ancestors_path,
            'description_preview' => $item->description
                ? mb_substr($item->description, 0, 160).(mb_strlen($item->description) > 160 ? '…' : '')
                : null,
        ];
    }
}
