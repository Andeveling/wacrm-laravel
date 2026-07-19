<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\Enums\AutomationLogStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationLog>
 */
class AutomationLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'automation_id' => Automation::factory(),
            'user_id' => User::factory(),
            'trigger_event' => 'message_received',
            'steps_executed' => [],
            'status' => AutomationLogStatus::Success,
        ];
    }
}
