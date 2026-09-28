<?php

namespace Tests\Unit\Foundation;

use App\Enums\Algorithm;
use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Enums\SignerStatus;
use App\Enums\VerificationCode;
use App\Enums\VerificationMethod;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SigningKey;
use App\Models\User;
use App\Models\VerificationLog;
use App\Services\Crypto\KeyPairService;
use App\Services\Crypto\PrivateKeyVault;
use App\Services\Crypto\SignatureService;
use App\Services\Document\DocumentFinalizer;
use App\Services\Document\SigningWorkflowService;
use App\Services\Verification\VerificationService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

class FoundationStructureTest extends TestCase
{
    public function test_enum_values_match_the_contract(): void
    {
        $this->assertSame(['ADMIN', 'SIGNER'], $this->values(Role::cases()));
        $this->assertSame(
            ['WAITING_SIGNATURE', 'PARTIALLY_SIGNED', 'COMPLETED'],
            $this->values(DocumentStatus::cases()),
        );
        $this->assertSame(['PENDING', 'SIGNED'], $this->values(SignerStatus::cases()));
        $this->assertSame(
            [
                'VALID',
                'INCOMPLETE',
                'INVALID_DOCUMENT_MODIFIED',
                'INVALID_SIGNATURE',
                'INVALID_PUBLIC_KEY',
                'REJECTED_TOKEN_NOT_FOUND',
            ],
            $this->values(VerificationCode::cases()),
        );
        $this->assertSame(
            ['TOKEN', 'UPLOAD', 'UPLOAD_CUSTOM_KEY'],
            $this->values(VerificationMethod::cases()),
        );
        $this->assertSame(['RSA-2048-PSS-SHA256'], $this->values(Algorithm::cases()));
    }

    public function test_models_use_the_contract_key_and_timestamp_configuration(): void
    {
        $user = new User;
        $signingKey = new SigningKey;
        $document = new Document;
        $documentSigner = new DocumentSigner;
        $verificationLog = new VerificationLog;

        $this->assertTrue($user->getIncrementing());
        $this->assertSame('int', $user->getKeyType());
        $this->assertFalse($signingKey->getIncrementing());
        $this->assertSame('string', $signingKey->getKeyType());
        $this->assertFalse($document->getIncrementing());
        $this->assertSame('string', $document->getKeyType());
        $this->assertTrue($documentSigner->getIncrementing());
        $this->assertSame('int', $documentSigner->getKeyType());
        $this->assertTrue($verificationLog->getIncrementing());
        $this->assertSame('int', $verificationLog->getKeyType());

        $this->assertSame('updated_at', $user->getUpdatedAtColumn());
        $this->assertNull($signingKey->getUpdatedAtColumn());
        $this->assertNull($document->getUpdatedAtColumn());
        $this->assertNull($documentSigner->getUpdatedAtColumn());
        $this->assertNull($verificationLog->getUpdatedAtColumn());
        $this->assertSame('created_at', $documentSigner->getCreatedAtColumn());
    }

    public function test_model_casts_match_the_contract(): void
    {
        $this->assertSame(Role::class, (new User)->getCasts()['role']);
        $this->assertSame(Algorithm::class, (new SigningKey)->getCasts()['algorithm']);
        $this->assertSame(DocumentStatus::class, (new Document)->getCasts()['status']);
        $this->assertSame('date', (new Document)->getCasts()['document_date']);
        $this->assertSame(SignerStatus::class, (new DocumentSigner)->getCasts()['status']);
        $this->assertSame('datetime', (new DocumentSigner)->getCasts()['signed_at']);
        $this->assertSame(VerificationMethod::class, (new VerificationLog)->getCasts()['method']);
        $this->assertSame(VerificationCode::class, (new VerificationLog)->getCasts()['result_code']);
    }

    public function test_model_relationships_match_the_foreign_keys(): void
    {
        $this->assertInstanceOf(HasOne::class, (new User)->signingKey());
        $this->assertInstanceOf(HasMany::class, (new User)->createdDocuments());
        $this->assertInstanceOf(HasMany::class, (new User)->documentSigners());
        $this->assertInstanceOf(BelongsTo::class, (new SigningKey)->user());
        $this->assertInstanceOf(HasMany::class, (new SigningKey)->documentSigners());
        $this->assertInstanceOf(BelongsTo::class, (new Document)->creator());
        $this->assertInstanceOf(HasMany::class, (new Document)->signers());
        $this->assertInstanceOf(HasMany::class, (new Document)->verificationLogs());
        $this->assertInstanceOf(BelongsTo::class, (new DocumentSigner)->document());
        $this->assertInstanceOf(BelongsTo::class, (new DocumentSigner)->user());
        $this->assertInstanceOf(BelongsTo::class, (new DocumentSigner)->signingKey());
        $this->assertInstanceOf(BelongsTo::class, (new VerificationLog)->document());
    }

    public function test_service_stubs_are_autoloadable_and_container_resolvable(): void
    {
        $services = [
            KeyPairService::class,
            PrivateKeyVault::class,
            SignatureService::class,
            DocumentFinalizer::class,
            SigningWorkflowService::class,
            VerificationService::class,
        ];

        foreach ($services as $service) {
            $this->assertInstanceOf($service, $this->app->make($service));
        }
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return array<int, int|string>
     */
    private function values(array $cases): array
    {
        return array_map(
            static fn (\BackedEnum $case): int|string => $case->value,
            $cases,
        );
    }
}
