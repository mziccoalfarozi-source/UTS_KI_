<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\VerificationCode;
use App\Enums\VerificationMethod;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\VerificationLog;
use App\Services\Verification\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class VerificationController extends Controller
{
    public function __construct(
        private readonly VerificationService $verificationService,
    ) {}

    /**
     * GET /verify — form input token.
     */
    public function index(): Response
    {
        return Inertia::render('Verify/Index');
    }

    /**
     * GET /verify/{token} — lookup token, verifikasi dengan file server, tampilkan hasil.
     */
    public function show(string $token): Response
    {
        $document = Document::query()
            ->where('verification_token', $token)
            ->first();

        if (! $document instanceof Document) {
            $this->writeLog(null, VerificationMethod::Token, VerificationCode::RejectedTokenNotFound, $token);

            return Inertia::render('Verify/Show', [
                'token' => $token,
                'result' => $this->resultProps(VerificationCode::RejectedTokenNotFound, 0, 0),
                'document' => null,
            ]);
        }

        $result = $this->verificationService->verify($document, null, null, null);
        $this->writeLog($document->id, VerificationMethod::Token, $result['code'], $token);

        return Inertia::render('Verify/Show', [
            'token' => $token,
            'result' => $this->resultProps($result['code'], $result['signed_count'], $result['total_signers']),
            'document' => $this->documentProps($document),
        ]);
    }

    /**
     * POST /verify/{token} — verifikasi dengan PDF upload dan optional custom public key.
     */
    public function verify(Request $request, string $token): Response
    {
        $validated = $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'document_signer_id' => ['nullable', 'integer', 'min:1'],
            'custom_public_key' => ['nullable', 'string', 'max:8192'],
        ]);

        $hasAdvanced = ! empty($validated['document_signer_id']) && ! empty($validated['custom_public_key']);
        $method = $hasAdvanced ? VerificationMethod::UploadCustomKey : VerificationMethod::Upload;

        $document = Document::query()
            ->where('verification_token', $token)
            ->first();

        if (! $document instanceof Document) {
            $this->writeLog(null, $method, VerificationCode::RejectedTokenNotFound, $token);

            return Inertia::render('Verify/Show', [
                'token' => $token,
                'result' => $this->resultProps(VerificationCode::RejectedTokenNotFound, 0, 0),
                'document' => null,
            ]);
        }

        $pdfFile = $validated['pdf'];
        $pdfBytes = file_get_contents($pdfFile->getRealPath());

        if ($pdfBytes === false) {
            $pdfBytes = '';
        }

        $advancedSignerId = $hasAdvanced ? (int) $validated['document_signer_id'] : null;
        $customPublicKeyPem = $hasAdvanced ? (string) $validated['custom_public_key'] : null;

        $result = $this->verificationService->verify($document, $pdfBytes, $advancedSignerId, $customPublicKeyPem);
        $this->writeLog($document->id, $method, $result['code'], $token);

        return Inertia::render('Verify/Show', [
            'token' => $token,
            'result' => $this->resultProps($result['code'], $result['signed_count'], $result['total_signers']),
            'document' => $this->documentProps($document),
        ]);
    }

    /**
     * GET /admin/logs — daftar verification log untuk administrator.
     */
    public function logs(Request $request): Response
    {
        abort_unless($request->user()?->role === Role::Admin, HttpResponse::HTTP_FORBIDDEN);

        $logs = VerificationLog::query()
            ->with('document:id,title')
            ->latest('created_at')
            ->paginate(50)
            ->through(fn (VerificationLog $log): array => [
                'id' => $log->id,
                'document_id' => $log->document_id,
                'document_title' => $log->document?->title,
                'method' => $log->method->value,
                'result_code' => $log->result_code->value,
                'token_prefix' => $log->token_prefix,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('Admin/Logs/Index', [
            'logs' => $logs,
        ]);
    }

    private function writeLog(
        ?string $documentId,
        VerificationMethod $method,
        VerificationCode $code,
        string $token,
    ): void {
        $prefix = mb_strlen($token) >= 8 ? mb_substr($token, 0, 8) : $token;

        VerificationLog::query()->create([
            'document_id' => $documentId,
            'method' => $method,
            'result_code' => $code,
            'token_prefix' => $prefix,
        ]);
    }

    /**
     * @return array{code: string, signed_count: int, total_signers: int}
     */
    private function resultProps(VerificationCode $code, int $signedCount, int $totalSigners): array
    {
        return [
            'code' => $code->value,
            'signed_count' => $signedCount,
            'total_signers' => $totalSigners,
        ];
    }

    /**
     * @return array{id: string, title: string, institution: string, document_date: string, document_hash: string, status: string, signers: list<array{id: int, name: string, position_title: string, sign_order: int, status: string, signed_at: string|null}>}
     */
    private function documentProps(Document $document): array
    {
        $document->loadMissing(['signers' => fn ($q) => $q->with('user:id,name')->orderBy('sign_order')]);

        return [
            'id' => $document->id,
            'title' => $document->title,
            'institution' => $document->institution,
            'document_date' => $document->document_date->toDateString(),
            'document_hash' => $document->document_hash,
            'status' => $document->status->value,
            'signers' => $document->signers->map(fn (DocumentSigner $signer): array => [
                'id' => $signer->id,
                'name' => $signer->user->name,
                'position_title' => $signer->position_title,
                'sign_order' => $signer->sign_order,
                'status' => $signer->status->value,
                'signed_at' => $signer->signed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
