<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Contacts;

use App\Mcp\Support\SearchTool;
use App\Models\Contact;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Busca contactos por nombre, teléfono, email o empresa.')]
class SearchContactsTool extends SearchTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Texto a buscar en nombre, teléfono, email o empresa.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Máximo de resultados (max 50).')
                ->default(20),
        ];
    }

    protected function baseQuery(): Builder
    {
        return Contact::query()->with('tags:id,name');
    }

    protected function searchQuery(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search): void {
            $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('phone', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")
                ->orWhere('company', 'ilike', "%{$search}%");
        });
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
        ];
    }
}
