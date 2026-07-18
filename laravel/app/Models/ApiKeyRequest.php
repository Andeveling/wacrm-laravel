<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\ApiKeyRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per request made through the `api_key` guard. Written by
 * `AuthenticateApiKey::terminate()` after the response is generated, so
 * `duration_ms` is accurate and failed requests are logged too.
 *
 * @property int $id
 * @property string $api_key_id
 * @property string $account_id
 * @property string $method
 * @property string $path
 * @property int $status
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $request_id
 * @property int $duration_ms
 * @property string|null $scope_used
 * @property Carbon $created_at
 */
#[Fillable(['api_key_id', 'account_id', 'method', 'path', 'status', 'ip', 'user_agent', 'request_id', 'duration_ms', 'scope_used'])]
class ApiKeyRequest extends Model
{
    /** @use HasFactory<ApiKeyRequestFactory> */
    use BelongsToAccount, HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'api_key_requests';

    /**
     * The key that made this request.
     *
     * @return BelongsTo<ApiKey, $this>
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
