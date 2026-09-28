<?php

namespace App\Http\Controllers;

use App\Exceptions\PrivateKeyDecryptionException;
use App\Exceptions\SigningWorkflowException;
use App\Http\Requests\SignDocumentRequest;
use App\Models\Document;
use App\Services\Document\SigningWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class DocumentSigningController extends Controller
{
    public function __construct(private readonly SigningWorkflowService $signingWorkflowService) {}

    public function store(SignDocumentRequest $request, Document $document): RedirectResponse
    {
        $validated = $request->validated();
        $passphrase = $validated['signing_passphrase'];
        $request->request->remove('signing_passphrase');

        try {
            $this->signingWorkflowService->sign($document, $request->user(), $passphrase);
        } catch (PrivateKeyDecryptionException) {
            throw ValidationException::withMessages([
                'signing_passphrase' => 'Passphrase salah atau data kunci rusak',
            ]);
        } catch (SigningWorkflowException $exception) {
            throw ValidationException::withMessages([
                'signing' => $exception->getMessage(),
            ]);
        } finally {
            sodium_memzero($passphrase);
        }

        return back();
    }
}
