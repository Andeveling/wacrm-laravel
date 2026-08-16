<?php

namespace App\Models\Enums;

enum WhatsappLegacyMigrationIssueKind: string
{
    case MissingLegacyConnection = 'missing_legacy_connection';
    case AmbiguousConversationConnection = 'ambiguous_conversation_connection';
    case WabaClaimedByAnotherAccount = 'waba_claimed_by_another_account';
    case PhoneNumberClaimedByAnotherAccount = 'phone_number_claimed_by_another_account';
    case IncompleteLegacyConfig = 'incomplete_legacy_config';

    public function canAssignConnection(): bool
    {
        return $this === self::AmbiguousConversationConnection
            || $this === self::MissingLegacyConnection;
    }

    public function canDismiss(): bool
    {
        return $this === self::WabaClaimedByAnotherAccount
            || $this === self::PhoneNumberClaimedByAnotherAccount
            || $this === self::IncompleteLegacyConfig;
    }
}
