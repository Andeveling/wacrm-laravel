<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\PipelineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $account_id
 * @property string $name
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'account_id', 'name'])]
class Pipeline extends Model
{
    /** @use HasFactory<PipelineFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return HasMany<PipelineStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('position');
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
