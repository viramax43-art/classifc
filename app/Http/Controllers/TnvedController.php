<?php

namespace App\Http\Controllers;

use App\Models\Okpd2TnvedMapping;
use App\Models\TnvedItem;
use App\Models\TnvedMeta;
use App\Services\TksTreeClient;
use App\Services\TnvedTreeMapper;
use App\Support\ActualizationFormatter;
use App\Support\ClassifierUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TnvedController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $code = trim((string) $request->query('code', ''));

        if ($code !== '' && preg_match('/^\d+$/', $code)) {
            return ClassifierUrl::redirectTo(ClassifierUrl::tnvedPublicPath($code));
        }

        return $this->renderIndex();
    }

    public function codePage(string $code): View|RedirectResponse
    {
        $item = TnvedItem::findByExactCode($code);

        if (! $item) {
            abort(404);
        }

        $seoItem = ! $item->has_children ? $item : null;

        return $this->renderIndex(
            (string) $item->code,
            $seoItem,
            $this->buildShowPayload($item),
        );
    }

    private function renderIndex(
        ?string $initialCode = null,
        ?TnvedItem $seoItem = null,
        ?array $initialPayload = null,
    ): View {
        $meta = TnvedMeta::query()->latest('id')->first();
        $sections = $this->buildSections();

        $seoTitle = 'ТН ВЭД — классификатор товаров';
        $seoDescription = 'Классификатор ТН ВЭД: дерево разделов, поиск по коду и названию, связи с ОКПД 2.';

        if ($seoItem) {
            $displayCode = $seoItem->display_code ?: TnvedItem::formatDisplayCode($seoItem->code);
            $seoTitle = 'ТН ВЭД '.$displayCode.' — '.$seoItem->name;
            $seoDescription = mb_substr(trim($seoItem->name), 0, 160);
        }

        return view('tnved.index', [
            'meta' => $meta,
            'classifierUpdatedAt' => ActualizationFormatter::fromMeta($meta),
            'sections' => $sections,
            'totalCount' => TnvedItem::query()->count(),
            'tnvedParts' => config('tnved.parts', []),
            'initialCode' => $initialCode !== null ? (string) $initialCode : null,
            'initialPayload' => $initialPayload,
            'seoTitle' => $seoTitle,
            'seoDescription' => $seoDescription,
            'canonicalUrl' => ClassifierUrl::absolute(
                ClassifierUrl::tnvedPublicPath($seoItem?->code),
            ),
        ]);
    }

    public function treeRoot(TksTreeClient $treeClient): JsonResponse
    {
        try {
            $items = TnvedTreeMapper::mapNodes($treeClient->getRootNodes());

            return response()->json(['items' => $items]);
        } catch (\Throwable $exception) {
            return $this->treeErrorResponse($exception);
        }
    }

    public function treeBranch(int $nodeId, TksTreeClient $treeClient): JsonResponse
    {
        try {
            $items = TnvedTreeMapper::mapNodes($treeClient->getBranch($nodeId));

            return response()->json([
                'node_id' => $nodeId,
                'items' => $items,
            ]);
        } catch (\Throwable $exception) {
            return $this->treeErrorResponse($exception);
        }
    }

    public function treeSearch(Request $request, TksTreeClient $treeClient): JsonResponse
    {
        $code = trim((string) $request->query('code', ''));
        $query = trim((string) $request->query('q', $request->query('searchstr', '')));

        if ($code === '' && $query === '') {
            return response()->json(['items' => []]);
        }

        try {
            $items = TnvedTreeMapper::mapNodes($treeClient->search(
                $code !== '' ? $code : null,
                $query !== '' ? $query : null,
            ));

            return response()->json(['items' => $items]);
        } catch (\Throwable $exception) {
            return $this->treeErrorResponse($exception);
        }
    }

    public function sections(): JsonResponse
    {
        $sections = $this->buildSections();

        return response()->json([
            'sections' => $sections,
        ]);
    }

    public function children(Request $request): JsonResponse
    {
        $section = str_pad(preg_replace('/\D/', '', (string) $request->query('section', '')) ?? '', 2, '0', STR_PAD_LEFT);
        $parentCode = $request->query('parent');

        if ($parentCode) {
            $normalizedParent = TnvedItem::normalizeCode((string) $parentCode);
            $parent = TnvedItem::query()->where('code', $normalizedParent)->first();
            $items = $parent ? $parent->children()->get() : collect();
        } elseif ($section !== '00') {
            $items = TnvedItem::query()
                ->where('section', $section)
                ->whereNull('parent_code')
                ->orderBy('code')
                ->get();
        } else {
            return response()->json(['items' => []]);
        }

        return response()->json([
            'items' => $items->map(fn (TnvedItem $item) => $this->formatItem($item)),
        ]);
    }

    public function show(string $code): JsonResponse
    {
        $item = TnvedItem::findByExactCode($code);

        if (! $item) {
            return response()->json(['message' => 'Код не найден'], 404);
        }

        return response()->json($this->buildShowPayload($item));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildShowPayload(TnvedItem $item): array
    {
        $isFullProduct = ! $item->has_children;
        $children = $item->children()->get();

        $payload = [
            'item' => [
                ...$this->formatItem($item),
                'description' => $item->description,
                'idx' => $item->idx,
                'ancestors_path' => $item->ancestors_path,
                'breadcrumb' => $item->breadcrumb(),
                'rates' => $item->rates,
                'date_begin' => $item->date_begin?->format('Y-m-d'),
                'date_end' => $item->date_end?->format('Y-m-d'),
            ],
            'is_full_product' => $isFullProduct,
            'mode' => $isFullProduct ? 'product' : 'tree',
            'children' => $children->map(fn (TnvedItem $child) => $this->formatItem($child))->values()->all(),
        ];

        if ($isFullProduct) {
            $payload['siblings'] = $this->getSiblings($item);
            $payload['related_okpd2'] = Okpd2TnvedMapping::query()
                ->where('tnved_code', $item->code)
                ->orderBy('okpd2_code')
                ->get()
                ->map(fn (Okpd2TnvedMapping $mapping) => [
                    'code' => $mapping->okpd2_code,
                    'name' => $mapping->okpd2Item?->name,
                ])->values()->all();
        } else {
            $payload['siblings'] = [
                'prev' => null,
                'next' => null,
                'index' => null,
                'total' => 0,
            ];
            $payload['related_okpd2'] = [];
        }

        return $payload;
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $section = str_pad(preg_replace('/\D/', '', (string) $request->query('section', '')) ?? '', 2, '0', STR_PAD_LEFT);
        $limit = min((int) $request->query('limit', 50), 100);

        if ($query === '') {
            return response()->json(['items' => [], 'total' => 0]);
        }

        $normalizedCode = TnvedItem::normalizeCodeQuery($query);
        $codeDigits = preg_replace('/\D/', '', $normalizedCode) ?? '';

        if ($this->canUseFts()) {
            return $this->searchWithFts($query, $normalizedCode, $codeDigits, $section, $limit);
        }

        return $this->searchWithLike($query, $normalizedCode, $codeDigits, $section, $limit);
    }

    public function meta(): JsonResponse
    {
        $meta = TnvedMeta::query()->latest('id')->first();

        return response()->json([
            'meta' => $meta,
            'total' => TnvedItem::query()->count(),
        ]);
    }

    private function treeErrorResponse(\Throwable $exception): JsonResponse
    {
        report($exception);

        return response()->json([
            'message' => 'Не удалось загрузить дерево ТН ВЭД из API TKS',
        ], 502);
    }

    private function buildSections(): \Illuminate\Support\Collection
    {
        $counts = TnvedItem::query()
            ->selectRaw('section, COUNT(*) as count')
            ->groupBy('section')
            ->pluck('count', 'section');

        return $counts
            ->map(function (int $count, string $code) {
                $root = TnvedItem::query()
                    ->where('code', $code.'00000000')
                    ->first();

                $name = $root?->name ?? '';
                if ($name === '' || $name === '—' || $name === '-') {
                    $name = config('tnved.sections.'.$code, "Глава {$code}");
                }

                return [
                    'code' => $code,
                    'name' => $name,
                    'count' => $count,
                ];
            })
            ->sortKeys()
            ->values();
    }

    private function canUseFts(): bool
    {
        return \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite'
            && (bool) \Illuminate\Support\Facades\DB::selectOne(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='tnved_items_fts'"
            );
    }

    private function searchWithFts(
        string $query,
        string $normalizedCode,
        string $codeDigits,
        string $section,
        int $limit,
    ): JsonResponse {
        $ftsQuery = $this->buildFtsQuery($query, $normalizedCode);

        $builder = TnvedItem::query()
            ->select('tnved_items.*')
            ->join('tnved_items_fts', 'tnved_items.id', '=', 'tnved_items_fts.rowid')
            ->whereRaw('tnved_items_fts MATCH ?', [$ftsQuery]);

        if ($section !== '' && $section !== '00' && $section !== 'ALL') {
            $builder->where('tnved_items.section', $section);
        }

        $total = (clone $builder)->count();

        $items = $builder
            ->orderByRaw('CASE
                WHEN tnved_items.code = ? THEN 0
                WHEN tnved_items.code LIKE ? THEN 1
                WHEN tnved_items.idx LIKE ? THEN 2
                WHEN tnved_items.name LIKE ? THEN 3
                ELSE 4
            END', [
                str_pad($normalizedCode, 10, '0', STR_PAD_LEFT),
                str_pad($normalizedCode, 10, '0', STR_PAD_LEFT).'%',
                "{$codeDigits}%",
                "%{$query}%",
            ])
            ->orderBy('tnved_items.code')
            ->limit($limit)
            ->get();

        return response()->json([
            'items' => $items->map(fn (TnvedItem $item) => $this->formatItem($item, $query)),
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
        $builder = TnvedItem::query();

        if ($section !== '' && $section !== '00' && $section !== 'ALL') {
            $builder->where('section', $section);
        }

        $padded = str_pad($normalizedCode, 10, '0', STR_PAD_LEFT);

        $builder->where(function ($q) use ($query, $padded, $codeDigits) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('code', 'like', "{$padded}%")
                ->orWhere('display_code', 'like', "%{$query}%")
                ->orWhere('idx', 'like', "{$codeDigits}%")
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
                $padded,
                "{$padded}%",
                "{$codeDigits}%",
                "%{$query}%",
            ])
            ->orderBy('code')
            ->limit($limit)
            ->get();

        return response()->json([
            'items' => $items->map(fn (TnvedItem $item) => $this->formatItem($item, $query)),
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

    private function schemeSiblings(TnvedItem $item): array
    {
        $result = [];
        $current = $item;

        while ($current) {
            $sibs = TnvedItem::query()
                ->when(
                    $current->parent_code,
                    fn ($q) => $q->where('parent_code', $current->parent_code),
                    fn ($q) => $q->where('section', $current->section)->whereNull('parent_code'),
                )
                ->orderBy('code')
                ->get(['code', 'display_code', 'name', 'has_children']);

            $result[$current->code] = $sibs->map(fn (TnvedItem $s) => [
                'code' => $s->code,
                'display_code' => $s->display_code,
                'name' => $s->name,
                'has_children' => $s->has_children,
            ])->values()->all();

            $current = $current->parent_code
                ? TnvedItem::query()->where('code', $current->parent_code)->first()
                : null;
        }

        return $result;
    }

    private function getSiblings(TnvedItem $item): array
    {
        $siblings = TnvedItem::query()
            ->when(
                $item->parent_code,
                fn ($q) => $q->where('parent_code', $item->parent_code),
                fn ($q) => $q->where('section', $item->section)->whereNull('parent_code'),
            )
            ->orderBy('code')
            ->get();

        $index = $siblings->search(fn (TnvedItem $sibling) => $sibling->code === $item->code);

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

    private function formatItem(TnvedItem $item, ?string $highlight = null): array
    {
        $nestingLevel = TnvedItem::resolveLevel($item->code);

        return [
            'code' => $item->code,
            'display_code' => $item->display_code ?: TnvedItem::formatDisplayCode($item->code),
            'name' => $item->name,
            'section' => $item->section,
            'section_name' => config('tnved.sections.'.$item->section),
            'level' => $nestingLevel,
            'nesting_level' => $nestingLevel,
            'level_name' => TnvedItem::resolveLevelName($nestingLevel),
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
