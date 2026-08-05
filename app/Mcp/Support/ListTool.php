<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

abstract class ListTool extends Tool
{
    abstract protected function query(Request $request): Builder;

    /**
     * @return array<string, mixed>
     */
    abstract protected function mapItem(Model $item): array;

    public function handle(Request $request): Response|ResponseFactory
    {
        $limit = min($request->integer('limit', 25), 50);

        $paginator = $this->query($request)->paginate($limit);

        $data = $paginator->map(fn (Model $item) => $this->mapItem($item));

        return Response::structured([
            'data' => $data,
            'pagination' => Pagination::from($paginator),
        ]);
    }
}
