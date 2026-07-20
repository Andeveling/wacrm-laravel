<?php

namespace Database\Factories;

use App\Models\Enums\FlowRunEventType;
use App\Models\FlowRun;
use App\Models\FlowRunEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlowRunEvent>
 */
class FlowRunEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flow_run_id' => FlowRun::factory(),
            'event_type' => FlowRunEventType::NodeEntered,
            'node_key' => 'node_'.fake()->bothify('??##'),
            'payload' => [],
        ];
    }

    /**
     * Indicate that this event marks the run started.
     */
    public function started(): static
    {
        return $this->state(fn (): array => ['event_type' => FlowRunEventType::Started]);
    }

    /**
     * Indicate that this event records a user reply.
     */
    public function replyReceived(): static
    {
        return $this->state(fn (): array => ['event_type' => FlowRunEventType::ReplyReceived]);
    }

    /**
     * Indicate that this event records a handoff.
     */
    public function handoff(): static
    {
        return $this->state(fn (): array => ['event_type' => FlowRunEventType::Handoff]);
    }

    /**
     * Indicate that this event records the run completing.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => ['event_type' => FlowRunEventType::Completed]);
    }
}
