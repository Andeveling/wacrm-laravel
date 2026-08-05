<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\ContactNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $contact_id
 * @property int $user_id
 * @property string $account_id
 * @property string $note_text
 * @property Carbon|null $created_at
 */
#[Fillable(['contact_id', 'user_id', 'account_id', 'note_text'])]
class ContactNote extends Model
{
    /** @use HasFactory<ContactNoteFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
