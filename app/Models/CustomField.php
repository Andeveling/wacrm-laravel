<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\CustomFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Definición de campo custom por cuenta; los valores por contacto viven
 * en contact_custom_values.
 *
 * @property string $id
 * @property int $user_id
 * @property string $account_id
 * @property string $field_name
 * @property string $field_type
 * @property array<array-key, mixed>|null $field_options
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'account_id', 'field_name', 'field_type', 'field_options'])]
class CustomField extends Model
{
    /** @use HasFactory<CustomFieldFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'field_type' => 'text',
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return HasMany<ContactCustomValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(ContactCustomValue::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field_options' => 'array',
        ];
    }
}
