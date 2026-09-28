<?php

namespace Tests\Feature;

use App\Enums\Algorithm;
use App\Models\SigningKey;
use App\Models\User;
use App\Services\Crypto\KeyPairService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class SigningKeyManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_open_or_create_signing_key(): void
    {
        $this->get(route('keys.index'))->assertRedirect(route('login'));
        $this->post(route('keys.store'), $this->validPayload())->assertRedirect(route('login'));
    }

    public function test_admin_cannot_open_or_create_signer_key(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('keys.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('keys.store'), $this->validPayload())->assertForbidden();
        $this->assertDatabaseCount('signing_keys', 0);
    }

    public function test_signer_without_key_can_open_creation_workflow(): void
    {
        $signer = User::factory()->signer()->create();

        $this->actingAs($signer)
            ->get(route('keys.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Keys/Index')
                ->where('signingKey', null));
    }

    public function test_signer_can_create_key_owned_by_authenticated_user_without_persisting_plaintext_secrets(): void
    {
        $signer = User::factory()->signer()->create();
        $otherSigner = User::factory()->signer()->create();
        $payload = [
            ...$this->validPayload(),
            'user_id' => $otherSigner->id,
            'private_key' => 'attacker-private-key',
            'encrypted_private_key' => 'attacker-ciphertext',
        ];

        $this->actingAs($signer)
            ->post(route('keys.store'), $payload)
            ->assertRedirect(route('keys.index'));

        $signingKey = SigningKey::query()->sole();

        $this->assertSame($signer->id, $signingKey->user_id);
        $this->assertSame(Algorithm::Rsa2048PssSha256, $signingKey->algorithm);
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $signingKey->public_key);
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $signingKey->encrypted_private_key);
        $this->assertStringNotContainsString('correct-signing-passphrase', $signingKey->encrypted_private_key);
        $this->assertNotSame('attacker-ciphertext', $signingKey->encrypted_private_key);
        $this->assertArrayNotHasKey('signing_passphrase', $signingKey->getAttributes());
        $this->assertArrayNotHasKey('private_key', $signingKey->getAttributes());
        $this->assertSame('ARGON2ID13', $signingKey->kdf);
        $this->assertSame(SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE, $signingKey->kdf_opslimit);
        $this->assertSame(SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE, $signingKey->kdf_memlimit);

        foreach (['encrypted_private_key', 'salt', 'nonce', 'auth_tag'] as $field) {
            $decoded = base64_decode($signingKey->getAttribute($field), true);

            $this->assertIsString($decoded);
            $this->assertSame($signingKey->getAttribute($field), base64_encode($decoded));
        }
    }

    public function test_signer_with_existing_key_cannot_create_second_key_or_overwrite_original(): void
    {
        $signer = User::factory()->signer()->create();
        $original = $this->createSigningKey($signer);

        $this->actingAs($signer)
            ->from(route('keys.index'))
            ->post(route('keys.store'), $this->validPayload())
            ->assertRedirect(route('keys.index'))
            ->assertSessionHasErrors('signing_passphrase');

        $this->assertDatabaseCount('signing_keys', 1);
        $this->assertSame($original->public_key, $original->fresh()->public_key);
    }

    public function test_failed_key_generation_does_not_leave_partial_record(): void
    {
        $signer = User::factory()->signer()->create();
        Exceptions::fake();

        $this->mock(KeyPairService::class)
            ->shouldReceive('generate')
            ->once()
            ->andThrow(new RuntimeException('simulated generation failure'));

        $this->actingAs($signer)
            ->post(route('keys.store'), $this->validPayload())
            ->assertServerError();

        Exceptions::assertReported(RuntimeException::class);
        $this->assertDatabaseCount('signing_keys', 0);
    }

    public function test_signing_passphrase_requires_minimum_length_and_confirmation(): void
    {
        $signer = User::factory()->signer()->create();

        $this->actingAs($signer)
            ->from(route('keys.index'))
            ->post(route('keys.store'), [
                'signing_passphrase' => 'short',
                'signing_passphrase_confirmation' => 'different',
            ])
            ->assertRedirect(route('keys.index'))
            ->assertSessionHasErrors('signing_passphrase')
            ->assertSessionMissing('_old_input.signing_passphrase')
            ->assertSessionMissing('_old_input.signing_passphrase_confirmation');

        $this->assertDatabaseCount('signing_keys', 0);
    }

    public function test_sensitive_crypto_fields_are_not_sent_in_inertia_props(): void
    {
        $signer = User::factory()->signer()->create();
        $signingKey = $this->createSigningKey($signer);

        $this->actingAs($signer)
            ->get(route('keys.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Keys/Index')
                ->where('signingKey.algorithm', Algorithm::Rsa2048PssSha256->value)
                ->has('signingKey.created_at')
                ->missing('signingKey.public_key')
                ->missing('signingKey.encrypted_private_key')
                ->missing('signingKey.salt')
                ->missing('signingKey.nonce')
                ->missing('signingKey.auth_tag')
                ->missing('signingKey.kdf_opslimit')
                ->missing('signingKey.kdf_memlimit'));

        $this->assertNotEmpty($signingKey->encrypted_private_key);
    }

    public function test_signer_dashboard_only_exposes_safe_key_status(): void
    {
        $signer = User::factory()->signer()->create();
        $this->createSigningKey($signer);

        $this->actingAs($signer)
            ->get(route('signer.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('signingKey.created_at')
                ->missing('signingKey.encrypted_private_key')
                ->missing('signingKey.salt')
                ->missing('signingKey.nonce')
                ->missing('signingKey.auth_tag'));
    }

    /** @return array{signing_passphrase: string, signing_passphrase_confirmation: string} */
    private function validPayload(): array
    {
        return [
            'signing_passphrase' => 'correct-signing-passphrase',
            'signing_passphrase_confirmation' => 'correct-signing-passphrase',
        ];
    }

    private function createSigningKey(User $user): SigningKey
    {
        return SigningKey::query()->create([
            'user_id' => $user->id,
            'algorithm' => Algorithm::Rsa2048PssSha256,
            'public_key' => "-----BEGIN PUBLIC KEY-----\ntest\n-----END PUBLIC KEY-----\n",
            'encrypted_private_key' => base64_encode('encrypted-private-key'),
            'salt' => base64_encode(random_bytes(16)),
            'nonce' => base64_encode(random_bytes(12)),
            'auth_tag' => base64_encode(random_bytes(16)),
            'kdf' => 'ARGON2ID13',
            'kdf_opslimit' => SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
            'kdf_memlimit' => SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
        ]);
    }
}
