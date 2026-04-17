<?php

namespace Modules\Tally\Services\Concerns;

use Illuminate\Http\Request;

trait PaginatesResults
{
    protected function paginate(array $items, Request $request): array
    {
        $search = $request->query('search');
        $sortBy = $request->query('sort_by', 'NAME');
        $sortDir = strtolower($request->query('sort_dir', 'asc'));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(200, max(1, (int) $request->query('per_page', 50)));

        // Search filter
        if ($search) {
            $searchLower = strtolower($search);
            $items = array_filter($items, function ($item) use ($searchLower) {
                $name = $item['@attributes']['NAME'] ?? ($item['NAME'] ?? '');

                return str_contains(strtolower((string) $name), $searchLower);
            });
            $items = array_values($items);
        }

        // Sort
        usort($items, function ($a, $b) use ($sortBy, $sortDir) {
            $aVal = $a['@attributes'][$sortBy] ?? ($a[$sortBy] ?? '');
            $bVal = $b['@attributes'][$sortBy] ?? ($b[$sortBy] ?? '');

            $cmp = strcasecmp((string) $aVal, (string) $bVal);

            return $sortDir === 'desc' ? -$cmp : $cmp;
        });

        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;
        $pageItems = array_slice($items, $offset, $perPage);

        return [
            'data' => $pageItems,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }
}
