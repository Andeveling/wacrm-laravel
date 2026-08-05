<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageReaction>
 */
class MessageReactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // message_id se crea primero; el closure anota el conversation_id
            // del message recién creado para mantener la desnormalización
            // coherente con la fila del message (la DB no lo exige, pero
            // sin esto los joins por conversation fallan).
            'message_id' => Message::factory(),
            'conversation_id' => function (array $attributes): string {
                /** @var Message $message */
                $message = Message::query()->findOrFail($attributes['message_id']);

                return $message->conversation_id;
            },
            'actor_type' => 'agent',
            'actor_id' => User::factory(),
            'emoji' => fake()->randomElement(['👍', '❤️', '😂', '🎉', '👀']),
        ];
    }
}
