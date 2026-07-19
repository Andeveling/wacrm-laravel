<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\AiKnowledgeChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Unidad de recuperación RAG. `account_id` viene denormalizado del
 * documento para filtrar sin join. `fts` (solo pgsql) es GENERATED —
 * nunca se escribe. `embedding` es vector(1536) en pgsql / text en
 * sqlite; se escribe como literal '[0.1,...]' y lo puebla
 * Embeddings::for() del SDK (#37) — NULL = cuenta solo-léxica.
 *
 * @property string $id
 * @property string $document_id
 * @property string $account_id
 * @property int $chunk_index
 * @property string $content
 * @property string|null $embedding
 * @property Carbon|null $created_at
 */
#[Fillable(['document_id', 'account_id', 'chunk_index', 'content', 'embedding'])]
class AiKnowledgeChunk extends Model
{
    /** @use HasFactory<AiKnowledgeChunkFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<AiKnowledgeDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeDocument::class, 'document_id');
    }
}
