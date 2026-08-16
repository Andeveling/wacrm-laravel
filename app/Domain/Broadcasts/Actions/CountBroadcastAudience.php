<?php

declare(strict_types=1);

namespace App\Domain\Broadcasts\Actions;

use App\Domain\Broadcasts\Support\BroadcastAudience;
use App\Http\Requests\Broadcasts\CountBroadcastAudienceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final readonly class CountBroadcastAudience
{
    public function __construct(private BroadcastAudience $audience) {}

    public function __invoke(CountBroadcastAudienceRequest $request): JsonResponse
    {
        $data = $request->validated();
        /** @var list<string> $tagIds */
        $tagIds = array_values($data['tag_ids'] ?? []);

        if (! $this->audience->tagsBelongToCurrentAccount($tagIds)) {
            throw ValidationException::withMessages(['tag_ids' => 'Las etiquetas seleccionadas no están disponibles.']);
        }

        return response()->json(['count' => $this->audience->contacts($tagIds)->count()]);
    }
}
