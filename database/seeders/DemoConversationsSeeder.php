<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Enums\ConversationStatus;
use App\Models\Enums\MessageStatus;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Database\Seeder;

/**
 * Conversaciones del equipo demo con su hilo de mensajes: entrantes del
 * contacto, respuestas del agente, alguna respuesta automática del bot y
 * reacciones. Depende de DemoContactsSeeder.
 */
class DemoConversationsSeeder extends Seeder
{
    /**
     * Una conversación por estado; la mezcla hace que la bandeja demo
     * muestre los tres filtros con contenido.
     *
     * @var list<ConversationStatus>
     */
    private const STATUSES = [
        ConversationStatus::Open,
        ConversationStatus::Open,
        ConversationStatus::Pending,
        ConversationStatus::Closed,
        ConversationStatus::Closed,
        ConversationStatus::Open,
    ];

    private const INBOUND_TEXTS = [
        'Hola, ¿tienen disponibilidad?',
        'Gracias por la información.',
        '¿Pueden enviarme más detalles?',
        'Perfecto, confirmemos para mañana.',
        '¿A qué hora pueden enviar?',
        'No me interesa por ahora.',
    ];

    private const AGENT_TEXTS = [
        '¡Hola! Sí, tenemos disponibilidad esta semana.',
        'Con gusto, te envío el catálogo ahora.',
        '¿Cuál es tu presupuesto aproximado?',
        'Perfecto, te confirmo el agendamiento.',
        '¿Te parece bien a las 3 PM?',
        'Entendido, cualquier cosa me avisas.',
    ];

    private const BOT_TEXT = 'Gracias por tu mensaje. Un agente te responderá pronto.';

    public function run(Account $team, User $owner): void
    {
        if (Conversation::where('account_id', $team->id)->exists()) {
            return;
        }

        $connection = WhatsappPhoneNumberConnection::query()
            ->where('account_id', $team->id)
            ->where('is_default', true)
            ->firstOrFail();

        $contacts = Contact::where('account_id', $team->id)
            ->limit(count(self::STATUSES))
            ->get();

        foreach ($contacts as $index => $contact) {
            $status = self::STATUSES[$index];

            $conversation = Conversation::create([
                'account_id' => $team->id,
                'user_id' => $owner->id,
                'contact_id' => $contact->id,
                'connection_id' => $connection->id,
                'status' => $status,
                'last_message_at' => now()->subHours($index + 1),
                'unread_count' => $status === ConversationStatus::Open ? fake()->numberBetween(0, 3) : 0,
            ]);

            $this->populateThread($conversation, $owner);
        }
    }

    private function populateThread(Conversation $conversation, User $agent): void
    {
        $messageCount = fake()->numberBetween(3, 8);

        for ($i = 0; $i < $messageCount; $i++) {
            $isInbound = $i % 2 === 0;

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => $isInbound ? 'customer' : 'agent',
                'sender_id' => $isInbound ? null : $agent->id,
                'content_type' => 'text',
                'content_text' => fake()->randomElement($isInbound ? self::INBOUND_TEXTS : self::AGENT_TEXTS),
                'status' => $isInbound
                    ? fake()->randomElement([MessageStatus::Delivered, MessageStatus::Read])
                    : MessageStatus::Read,
                'ai_generated' => false,
                'created_at' => now()->subMinutes(($messageCount - $i) * 15),
            ]);

            if ($isInbound && $i > 0 && $i % 4 === 0) {
                $this->addBotReply($conversation, $message);
            }

            if (! $isInbound && $i > 0 && $i % 3 === 0) {
                $this->addCustomerReaction($conversation, $message);
            }
        }
    }

    private function addBotReply(Conversation $conversation, Message $repliedTo): void
    {
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'bot',
            'content_text' => self::BOT_TEXT,
            'content_type' => 'text',
            'status' => MessageStatus::Read,
            'ai_generated' => true,
            'created_at' => $repliedTo->created_at->copy()->addMinute(),
        ]);
    }

    private function addCustomerReaction(Conversation $conversation, Message $message): void
    {
        MessageReaction::create([
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'actor_type' => 'customer',
            'emoji' => '👍',
        ]);
    }
}
