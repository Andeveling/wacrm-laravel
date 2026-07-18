<?php

namespace Tests\Unit\Auth;

use App\Support\ApiKeyToken;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiKeyTokenTest extends TestCase
{
    #[Test]
    public function it_issues_a_live_token_with_consistent_hash_and_prefix(): void
    {
        $issued = ApiKeyToken::issue('live');

        $this->assertMatchesRegularExpression('/^wacrm_live_[a-f0-9]{64}$/', $issued['plaintext']);
        $this->assertSame(substr($issued['plaintext'], 0, 17), $issued['key_prefix']);
        $this->assertSame(hash('sha256', $issued['plaintext']), $issued['key_hash']);
        $this->assertSame(64, strlen($issued['key_hash']));
    }

    #[Test]
    public function it_issues_a_test_token(): void
    {
        $issued = ApiKeyToken::issue('test');

        $this->assertMatchesRegularExpression('/^wacrm_test_[a-f0-9]{64}$/', $issued['plaintext']);
    }

    #[Test]
    public function it_rejects_unknown_environments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApiKeyToken::issue('staging');
    }

    #[Test]
    public function hash_is_a_deterministic_sha256_of_the_plaintext(): void
    {
        $plaintext = 'wacrm_live_'.bin2hex(random_bytes(32));

        $this->assertSame(hash('sha256', $plaintext), ApiKeyToken::hash($plaintext));
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function authorizationHeaderProvider(): array
    {
        $valid = 'wacrm_live_'.bin2hex(random_bytes(32));

        return [
            'null header' => [null],
            'empty header' => [''],
            'wrong scheme' => ['Basic '.bin2hex(random_bytes(32))],
            'missing bearer' => [$valid],
            'too short random' => ['Bearer wacrm_live_abc'],
            'wrong prefix' => ['Bearer wkrm_live_'.bin2hex(random_bytes(32))],
            'uppercase prefix' => ['Bearer WACRM_LIVE_'.bin2hex(random_bytes(32))],
            'unknown environment' => ['Bearer wacrm_staging_'.bin2hex(random_bytes(32))],
        ];
    }

    #[Test]
    #[DataProvider('authorizationHeaderProvider')]
    public function it_rejects_malformed_authorization_headers(string|null $header): void
    {
        $this->assertNull(ApiKeyToken::fromAuthorizationHeader($header));
    }

    #[Test]
    public function it_accepts_a_well_formed_bearer_token(): void
    {
        $plaintext = 'wacrm_test_'.bin2hex(random_bytes(32));

        $this->assertSame($plaintext, ApiKeyToken::fromAuthorizationHeader('Bearer '.$plaintext));
    }

    #[Test]
    public function it_is_case_insensitive_on_the_bearer_scheme(): void
    {
        $plaintext = 'wacrm_live_'.bin2hex(random_bytes(32));

        $this->assertSame($plaintext, ApiKeyToken::fromAuthorizationHeader('bearer '.$plaintext));
    }
}
