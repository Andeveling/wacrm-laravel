<?php

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Tests\BrowserTestCase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

pest()->extend(BrowserTestCase::class)->in('Browser');

pest()->browser()->timeout(15_000);

/**
 * Submits the login form in the browser. Stops there: where the app sends the
 * user next depends on the account and on two-factor, so each caller asserts
 * its own landing page.
 */
function signIn(User $user, string $password = 'password'): void
{
    test()->visit('/login')
        ->type('input[name="email"]', $user->email)
        ->type('input[name="password"]', $password)
        ->press('button[type="submit"]');
}

/**
 * Signs the user in. The middleware resolves their Current Account, so a
 * Browser test that logs in lands on the Dashboard without a switch page.
 */
function signInAndSelectAccount(User $user, string $password = 'password'): void
{
    signIn($user, $password);

    test()->visit('/dashboard')->assertPathIs('/dashboard');
}

/**
 * @return array{0: Account, 1: User}
 */
function seedAccountWithRole(AccountRole $role): array
{
    $account = Account::factory()->create();
    $user = User::factory()->create();
    $account->users()->attach($user->id, ['role' => $role->value, 'joined_at' => now()]);

    return [$account, $user];
}

function attachUserToAccount(Account $account, AccountRole $role): User
{
    $user = User::factory()->create();
    $account->users()->attach($user->id, ['role' => $role->value, 'joined_at' => now()]);

    return $user;
}

/**
 * @return array{0: User, 1: Account}
 */
function memberWithRole(string $role): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $account->users()->attach($user->id, ['role' => $role, 'joined_at' => now()]);

    return [$user, $account];
}

const META_WEBHOOK_SECRET = 'test-app-secret-for-meta-webhook';
const META_WEBHOOK_VERIFY_TOKEN = 'shared-meta-webhook-verify-token';

function signMetaWebhook(string $body): string
{
    return 'sha256='.hash_hmac('sha256', $body, META_WEBHOOK_SECRET);
}

/**
 * @param  list<array{phone_number_id: string, wa_id: string, name: string, message_id: string, text: string, waba_id?: string}>  $messages
 */
function inboundMessagesPayload(array $messages): string
{
    $entries = [];

    foreach ($messages as $message) {
        $wabaId = $message['waba_id'] ?? 'waba-'.$message['phone_number_id'];
        $entries[] = [
            'id' => $wabaId,
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => [
                        'display_phone_number' => $message['wa_id'],
                        'phone_number_id' => $message['phone_number_id'],
                    ],
                    'contacts' => [[
                        'profile' => ['name' => $message['name']],
                        'wa_id' => $message['wa_id'],
                    ]],
                    'messages' => [[
                        'from' => $message['wa_id'],
                        'id' => $message['message_id'],
                        'timestamp' => '1712000000',
                        'type' => 'text',
                        'text' => ['body' => $message['text']],
                    ]],
                ],
            ]],
        ];
    }

    return json_encode([
        'object' => 'whatsapp_business_account',
        'entry' => $entries,
    ], JSON_THROW_ON_ERROR);
}

/**
 * @return array<string, string>
 */
function signedWebhookServer(string $body): array
{
    return [
        'HTTP_X_HUB_SIGNATURE_256' => signMetaWebhook($body),
        'CONTENT_TYPE' => 'application/json',
    ];
}
