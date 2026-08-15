<?php

declare(strict_types=1);

namespace App\Domain\Broadcasts\Support;

use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;

final class BroadcastAudience
{
    /**
     * @param  list<string>  $tagIds
     * @return Builder<Contact>
     */
    public function contacts(array $tagIds): Builder
    {
        return Contact::query()->when(
            $tagIds !== [],
            fn (Builder $query) => $query->whereHas(
                'tags',
                fn (Builder $query) => $query->whereKey($tagIds),
            ),
        );
    }

    /** @param list<string> $tagIds */
    public function tagsBelongToCurrentAccount(array $tagIds): bool
    {
        return Tag::query()->whereKey($tagIds)->count() === count($tagIds);
    }
}
