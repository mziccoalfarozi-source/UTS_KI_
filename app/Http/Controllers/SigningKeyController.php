<?php

namespace App\Http\Controllers;

use App\Enums\Algorithm;
use App\Http\Requests\StoreSigningKeyRequest;
use App\Models\SigningKey;
use App\Models\User;
use App\Services\Crypto\KeyPairService;
use App\Services\Crypto\PrivateKeyVault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SigningKeyController extends Controller
{
    public function __construct(
        private readonly KeyPairService $keyPairService,
        private readonly PrivateKeyVault $privateKeyVault,
    ) {}

    public function index(Request $request): Response
    {
        $signingKey = $request->user()->signingKey()->first(['algorithm', 'created_at']);

        return Inertia::render('Keys/Index', [
            'signingKey' => $signingKey === null ? null : [
                'algorithm' => $signingKey->algorithm->value,
                'created_at' => $signingKey->created_at->toIso8601String(),
            ],
        ]);
    }

    public function store(StoreSigningKeyRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $passphrase = $validated['signing_passphrase'];

        try {
            DB::transaction(function () use ($request, &$passphrase): void {
                $user = User::query()
                    ->whereKey($request->user()->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($user->signingKey()->exists()) {
                    throw ValidationException::withMessages([
                        'signing_passphrase' => 'Signing key sudah tersedia dan tidak dapat diganti.',
                    ]);
                }

                $keyPair = null;

                try {
                    $keyPair = $this->keyPairService->generate();
                    $protectedPrivateKey = $this->privateKeyVault->encrypt($keyPair['private_key'], $passphrase);

                    $signingKey = new SigningKey;
                    $signingKey->user()->associate($user);
                    $signingKey->algorithm = Algorithm::Rsa2048PssSha256;
                    $signingKey->public_key = $keyPair['public_key'];
                    $signingKey->encrypted_private_key = $protectedPrivateKey['encrypted_private_key'];
                    $signingKey->salt = $protectedPrivateKey['salt'];
                    $signingKey->nonce = $protectedPrivateKey['nonce'];
                    $signingKey->auth_tag = $protectedPrivateKey['auth_tag'];
                    $signingKey->kdf = $protectedPrivateKey['kdf'];
                    $signingKey->kdf_opslimit = $protectedPrivateKey['kdf_opslimit'];
                    $signingKey->kdf_memlimit = $protectedPrivateKey['kdf_memlimit'];
                    $signingKey->save();
                } finally {
                    if (is_array($keyPair) && isset($keyPair['private_key'])) {
                        sodium_memzero($keyPair['private_key']);
                    }
                }
            });
        } finally {
            sodium_memzero($passphrase);
        }

        return redirect()->route('keys.index');
    }
}
