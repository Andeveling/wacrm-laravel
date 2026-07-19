<?php

namespace App\Models;

use Database\Factories\PipelineStageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Etapa de un pipeline. Sin account_id propio: se scopea vía su pipeline
 * (igual que en Supabase).
 *
 * @property string $id
 * @property string $pipeline_id
 * @property string $name
 * @property int $position
 * @property string $color
 * @property Carbon|null $created_at
 */
#[Fillable(['pipeline_id', 'name', 'position', 'color'])]
class PipelineStage extends Model
{
    /** @use HasFactory<PipelineStageFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
        'color' => '#3b82f6',
    ];

    /**
     * @return BelongsTo<Pipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'stage_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }
}
