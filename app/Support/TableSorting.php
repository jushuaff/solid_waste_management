<?php
namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Shared allow-listed table sorting for MongoDB-backed Eloquent queries.
 *
 * Simple fields are sorted by MongoDB BEFORE pagination.
 * Display fields derived from related records are sorted by their displayed label
 * BEFORE the page is sliced. Those cases load the filtered set in memory and should
 * be replaced with indexed MongoDB aggregation/$lookup for very large datasets.
 */
final class TableSorting
{
    public static function resolve(Request $request, array $fields, string $default = 'created_at', string $defaultDirection = 'desc'): array
    {
        $requested = $request->query('sort');
        $sort = is_string($requested) && array_key_exists($requested, $fields) ? $requested : $default;

        $value = $request->query('direction');
        $direction = is_string($value) && in_array(strtolower($value), ['asc', 'desc'], true)
            ? strtolower($value) : ($sort === $default ? $defaultDirection : 'asc');

        return [$sort, $direction];
    }

    /**
     * @param array<string, string|callable> $fields Sort key => actual DB column or displayed-value callback.
     */
    public static function paginate($query, Request $request, array $fields, int $perPage = 20, string $default = 'created_at', string $defaultDirection = 'desc'): LengthAwarePaginator
    {
        [$sort, $direction] = self::resolve($request, $fields, $default, $defaultDirection);
        $sorter = $fields[$sort] ?? $default;

        if (is_string($sorter)) {
            return $query->orderBy($sorter, $direction)
                ->orderBy('_id', 'asc')
                ->paginate($perPage)->withQueryString();
        }

        // Relation/display sorting is global across the filtered result set.
        $rows = $query->get()->all();
        usort($rows, static function ($a, $b) use ($sorter, $direction): int {
            $cmp = self::compare($sorter($a), $sorter($b));
            if ($cmp === 0) $cmp = strcmp((string)$a->getKey(), (string)$b->getKey());
            return $direction === 'desc' ? -$cmp : $cmp;
        });

        $page = LengthAwarePaginator::resolveCurrentPage();
        $page = max(1, (int)$page);
        return new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /** Sort pre-aggregated dashboard rows without changing the calculated metrics. */
    public static function rows(array $rows, Request $request, array $fields): array
    {
        [$sort, $direction] = self::resolve($request, $fields, 'zone', 'asc');
        $getter = $fields[$sort];
        usort($rows, static function ($a, $b) use ($getter, $direction): int {
            $cmp = self::compare($getter($a), $getter($b));
            if ($cmp === 0) $cmp = self::compare((string)$a['zone']->name, (string)$b['zone']->name);
            return $direction === 'desc' ? -$cmp : $cmp;
        });
        return $rows;
    }

    private static function compare($left, $right): int
    {
        if ($left === null || $left === '') return ($right === null || $right === '') ? 0 : 1;
        if ($right === null || $right === '') return -1;
        if (is_numeric($left) && is_numeric($right)) return (float)$left <=> (float)$right;
        return strnatcasecmp((string)$left, (string)$right);
    }
}
