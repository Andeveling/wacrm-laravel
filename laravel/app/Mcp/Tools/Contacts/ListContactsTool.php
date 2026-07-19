<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Contacts;

use App\Mcp\Support\ListTool;
use App\Models\Contact;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Lista los contactos del account con paginación.')]
class ListContactsTool extends ListTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->description('Resultados por página (max 100).')
                ->default(25),
        ];
    }

    protected function query(Request $request): Builder
    {
        return Contact::query()
            ->with('tags:id,name')
            ->latest();
    }

    protected function mapItem(Model $item): array
    {
        /** @var Contact $c */
        $c = $item;

        return [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'email' => $c->email,
            'company' => $c->company,
            'tags' => $c->tags->pluck('name'),
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
