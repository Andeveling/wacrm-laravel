<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

abstract class GetTool extends Tool
{
    abstract protected function findQuery(string $id): Builder;

    /**
     * @return array<string, mixed>
     */
    abstract protected function mapItem(Model $item): array;

    abstract protected function notFoundMessage(): string;

    protected function idParam(): string
    {
        return 'id';
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $id = $request->string($this->idParam())->value();

        $item = $this->findQuery($id)->find($id);

        if ($item === null) {
            return Response::error($this->notFoundMessage());
        }

        return Response::structured($this->mapItem($item));
    }
}
