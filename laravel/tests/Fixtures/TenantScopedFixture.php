<?php

namespace Tests\Fixtures;

use App\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal tenant-scoped model used only to exercise the BelongsToAccount
 * trait and AccountScope in tests. No real domain model exists yet.
 */
class TenantScopedFixture extends Model
{
    use BelongsToAccount;

    protected $table = 'tenant_scoped_fixtures';

    protected $guarded = [];
}
