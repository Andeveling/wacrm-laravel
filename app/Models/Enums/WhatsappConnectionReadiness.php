<?php

namespace App\Models\Enums;

enum WhatsappConnectionReadiness: string
{
    case CredentialsVerified = 'credentials_verified';
    case Subscribed = 'subscribed';
    case WebhookWaiting = 'webhook_waiting';
    case Active = 'active';
    case AttentionRequired = 'attention_required';
    case Disconnected = 'disconnected';
}
