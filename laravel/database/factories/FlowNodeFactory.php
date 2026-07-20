<?php

namespace Database\Factories;

use App\Models\Enums\FlowNodeType;
use App\Models\Flow;
use App\Models\FlowNode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlowNode>
 */
class FlowNodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flow_id' => Flow::factory(),
            'node_key' => 'node_'.fake()->unique()->bothify('??##'),
            'node_type' => FlowNodeType::SendMessage,
            'config' => [],
            'position_x' => fake()->numberBetween(0, 800),
            'position_y' => fake()->numberBetween(0, 600),
        ];
    }

    /**
     * Indicate that this node is the entry/start of the flow.
     */
    public function start(): static
    {
        return $this->state(fn (): array => ['node_type' => FlowNodeType::Start]);
    }

    /**
     * Indicate that this node is a `send_message` step.
     */
    public function sendMessage(): static
    {
        return $this->state(fn (): array => ['node_type' => FlowNodeType::SendMessage]);
    }

    /**
     * Indicate that this node collects the user's reply.
     */
    public function collectInput(): static
    {
        return $this->state(fn (): array => ['node_type' => FlowNodeType::CollectInput]);
    }

    /**
     * Indicate that this node branches on a condition.
     */
    public function condition(): static
    {
        return $this->state(fn (): array => ['node_type' => FlowNodeType::Condition]);
    }

    /**
     * Indicate that this node hands off to a human agent.
     */
    public function handoff(): static
    {
        return $this->state(fn (): array => ['node_type' => FlowNodeType::Handoff]);
    }

    /**
     * Indicate that this node is the terminal end of the flow.
     */
    public function end(): static
    {
        return $this->state(fn (): array => ['node_type' => FlowNodeType::End]);
    }
}
