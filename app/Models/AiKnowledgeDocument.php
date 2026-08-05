<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\AiKnowledgeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Documento de la knowledge base (FAQs, políticas, catálogo). Se trocea
 * en ai_knowledge_chunks para recuperación; re-chunkear al editar es
 * responsabilidad del módulo IA (#37).
 *
 * @property string $id
 * @property string $account_id
 * @property int|null $created_by
 * @property string $title
 * @property string $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['account_id', 'created_by', 'title', 'content'])]
class AiKnowledgeDocument extends Model
{
    /** @use HasFactory<AiKnowledgeDocumentFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return HasMany<AiKnowledgeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(AiKnowledgeChunk::class, 'document_id');
    }
}
