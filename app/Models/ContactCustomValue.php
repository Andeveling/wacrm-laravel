<?php

namespace App\Models;

use Database\Factories\ContactCustomValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Valor de un campo custom para un contacto. Sin account_id propio:
 * se scopea vía su contacto (igual que en Supabase).
 *
 * @property string $id
 * @property string $contact_id
 * @property string $custom_field_id
 * @property string|null $value
 * @property Carbon|null $created_at
 */
#[Fillable(['contact_id', 'custom_field_id', 'value'])]
class ContactCustomValue extends Model
{
    /** @use HasFactory<ContactCustomValueFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<CustomField, $this>
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }
}
