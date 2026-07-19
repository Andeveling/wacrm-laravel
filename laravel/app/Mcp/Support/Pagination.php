<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class Pagination
{
    /**
     * @return array{current_page: int, per_page: int, total: int, last_page: int}
     */
    public static function from(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * @template T
     *
     * @param  Collection<int, T>|array<int, T>  $items
     * @return array{data: Collection<int, T>|array<int, T>, total: int}
     */
    public static function collection(Collection|array $items): array
    {
        return [
            'data' => $items,
            'total' => is_array($items) ? count($items) : $items->count(),
        ];
    }
}
