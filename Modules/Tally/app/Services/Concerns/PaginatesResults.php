<?php

namespace Modules\Tally\Services\Concerns;

use Illuminate\Http\Request;

trait PaginatesResults
{
    /**
     * Filter Tally export rows by an exact (case-insensitive) field value.
     * Skips when $value is null/empty so callers can pass query-string args directly.
     *
     * Tally exports nest text under @attributes (e.g. PARENT type-stamped) or
     * #text — both shapes are normalised here.
     */
    protected function filterByField(array $items, string $field, ?string $value): array
    {
        if ($value === null || $value === '') {
            return $items;
        }

        $needle = strtolower($value);

        return array_values(array_filter($items, function ($item) use ($field, $needle) {
            $raw = $item[$field]['#text'] ?? ($item[$field] ?? ($item['@attributes'][$field] ?? ''));

            return strtolower((string) $raw) === $needle;
        }));
    }

    /**
     * Filter Tally export rows where a numeric balance field is zero.
     * Treats absent / "0" / "0.00" / " 0.00 " all as zero. Returns full list
     * unchanged when $only is false / not requested.
     */
    protected function filterByZeroBalance(array $items, string $field, bool $only): array
    {
        if (! $only) {
            return $items;
        }

        return array_values(array_filter($items, function ($item) use ($field) {
            $raw = $item[$field]['#text'] ?? ($item[$field] ?? '');
            $normalised = trim(str_replace(',', '', (string) $raw));

            return $normalised === '' || (float) $normalised === 0.0;
        }));
    }

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
