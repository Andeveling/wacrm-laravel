<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Contacts;

use App\Domain\Contacts\Support\ContactProjection;
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
            ->with(ContactProjection::RELATIONS)
            ->select(ContactProjection::COLUMNS)
            ->latest();
    }

    protected function mapItem(Model $item): array
    {
        /** @var Contact $c */
        $c = $item;

        return ContactProjection::from($c);
    }
}
