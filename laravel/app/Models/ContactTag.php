<?php

namespace App\Models;

use Database\Factories\ContactTagFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Pivote contacto↔tag. Tiene PK uuid propia (herencia del esquema Supabase),
 * por eso attach/detach deben pasar por `using(ContactTag::class)` para que
 * HasUuids genere el id.
 *
 * @property string $id
 * @property string $contact_id
 * @property string $tag_id
 * @property Carbon|null $created_at
 */
class ContactTag extends Pivot
{
    /** @use HasFactory<ContactTagFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'contact_tags';

    public $timestamps = true;
}
