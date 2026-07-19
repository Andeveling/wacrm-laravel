<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

abstract class SearchTool extends Tool
{
    abstract protected function searchQuery(Builder $query, string $search): Builder;

    abstract protected function baseQuery(): Builder;

    /**
     * @return array<string, mixed>
     */
    abstract protected function mapItem(Model $item): array;

    public function handle(Request $request): Response|ResponseFactory
    {
        $search = $request->string('query')->value();
        $limit = min($request->integer('limit', 20), 50);

        $query = $this->baseQuery();
        $query = $this->searchQuery($query, $search);

        $items = $query->limit($limit)->get();

        $data = $items->map(fn (Model $item) => $this->mapItem($item));

        return Response::structured(Pagination::collection($data));
    }
}
