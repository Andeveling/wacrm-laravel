<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Support;

use App\Models\Contact;
use App\Models\Tag;

final class ContactTags
{
    /** @param array<int, string> $tagIds */
    public function sync(Contact $contact, array $tagIds): void
    {
        $allowedTagIds = Tag::query()->whereKey($tagIds)->pluck('id')->all();

        $contact->tags()->sync($allowedTagIds);
    }
}
