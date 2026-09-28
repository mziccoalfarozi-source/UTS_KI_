<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Enums\SignerStatus;
use App\Exceptions\DocumentFinalizationException;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\User;
use App\Services\Document\DocumentFinalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentFinalizer $documentFinalizer) {}

    public function index(Request $request): Response
    {
        if ($request->user()->role === Role::Signer) {
            return $this->signerIndex($request);
        }

        abort_unless($request->user()->role === Role::Admin, 403);

        $documents = Document::query()
            ->withCount('signers')
            ->latest('created_at')
            ->get()
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'title' => $document->title,
                'institution' => $document->institution,
                'document_date' => $document->document_date->toDateString(),
                'status' => $document->status->value,
                'signers_count' => $document->signers_count,
                'created_at' => $document->created_at->toIso8601String(),
            ]);

        $signers = User::query()
            ->where('role', Role::Signer)
            ->orderBy('name')
            ->get(['id', 'name', 'position_title', 'institution']);

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'availableSigners' => $signers,
        ]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $upload = $request->file('pdf');
        $documentId = (string) Str::uuid();
        $relativePath = 'documents/'.$documentId.'.pdf';
        $absolutePath = Storage::disk('local')->path($relativePath);
        $verificationToken = $this->uniqueVerificationToken();
        $assignments = collect($validated['signers'])->sortBy('sign_order')->values();
        $users = User::query()
            ->whereIn('id', $assignments->pluck('user_id'))
            ->get()
            ->keyBy('id');
        $signers = $assignments->map(fn (array $assignment): array => [
            'name' => $users->get($assignment['user_id'])->name,
            'position_title' => $assignment['position_title'],
            'sign_order' => $assignment['sign_order'],
        ])->all();

        try {
            $finalized = $this->documentFinalizer->finalize(
                $upload->getRealPath(),
                $absolutePath,
                [
                    'title' => $validated['title'],
                    'institution' => $validated['institution'],
                    'document_date' => $validated['document_date'],
                ],
                $signers,
                $verificationToken,
            );
        } catch (DocumentFinalizationException) {
            throw ValidationException::withMessages([
                'pdf' => 'PDF tidak dapat diproses untuk finalization.',
            ]);
        }

        try {
            $document = DB::transaction(function () use (
                $request,
                $validated,
                $upload,
                $documentId,
                $relativePath,
                $verificationToken,
                $finalized,
                $assignments,
            ): Document {
                $document = new Document;
                $document->id = $documentId;
                $document->title = $validated['title'];
                $document->institution = $validated['institution'];
                $document->document_date = $validated['document_date'];
                $document->original_filename = mb_substr(basename($upload->getClientOriginalName()), 0, 255);
                $document->file_path = $relativePath;
                $document->file_size = $finalized['file_size'];
                $document->document_hash = $finalized['document_hash'];
                $document->verification_token = $verificationToken;
                $document->status = DocumentStatus::WaitingSignature;
                $document->created_by = $request->user()->getKey();
                $document->save();

                foreach ($assignments as $assignment) {
                    $documentSigner = new DocumentSigner;
                    $documentSigner->document()->associate($document);
                    $documentSigner->user_id = $assignment['user_id'];
                    $documentSigner->sign_order = $assignment['sign_order'];
                    $documentSigner->position_title = $assignment['position_title'];
                    $documentSigner->status = SignerStatus::Pending;
                    $documentSigner->key_id = null;
                    $documentSigner->signature = null;
                    $documentSigner->signed_at = null;
                    $documentSigner->save();
                }

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($relativePath);

            throw $exception;
        }

        return redirect()->route('documents.show', $document);
    }

    public function show(Request $request, Document $document): Response
    {
        if ($request->user()->role === Role::Signer) {
            return $this->signerShow($request, $document);
        }

        abort_unless($request->user()->role === Role::Admin, 403);

        $document->load(['creator:id,name', 'signers' => fn ($query) => $query
            ->with('user:id,name')
            ->orderBy('sign_order')]);

        return Inertia::render('Documents/Show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'institution' => $document->institution,
                'document_date' => $document->document_date->toDateString(),
                'original_filename' => $document->original_filename,
                'file_size' => $document->file_size,
                'document_hash' => $document->document_hash,
                'verification_token' => $document->verification_token,
                'verification_url' => $this->documentFinalizer->verificationUrl($document->verification_token),
                'status' => $document->status->value,
                'created_by' => $document->creator->name,
                'created_at' => $document->created_at->toIso8601String(),
                'signers' => $document->signers->map(fn (DocumentSigner $signer): array => [
                    'id' => $signer->id,
                    'name' => $signer->user->name,
                    'position_title' => $signer->position_title,
                    'sign_order' => $signer->sign_order,
                    'status' => $signer->status->value,
                ]),
            ],
        ]);
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $isAdmin = $request->user()->role === Role::Admin;
        $isAssignedSigner = $request->user()->role === Role::Signer
            && $document->signers()->where('user_id', $request->user()->getKey())->exists();

        abort_unless($isAdmin || $isAssignedSigner, 403);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function signerIndex(Request $request): Response
    {
        $hasSigningKey = $request->user()->signingKey()->exists();
        $assignments = DocumentSigner::query()
            ->where('user_id', $request->user()->getKey())
            ->with(['document.signers' => fn ($query) => $query->orderBy('sign_order')])
            ->latest('created_at')
            ->get()
            ->map(function (DocumentSigner $assignment) use ($hasSigningKey): array {
                $previousSignersAreComplete = $assignment->document->signers->every(
                    fn (DocumentSigner $candidate): bool => $candidate->sign_order >= $assignment->sign_order
                        || $candidate->status === SignerStatus::Signed,
                );

                return [
                    'id' => $assignment->document->id,
                    'title' => $assignment->document->title,
                    'institution' => $assignment->document->institution,
                    'document_date' => $assignment->document->document_date->toDateString(),
                    'document_status' => $assignment->document->status->value,
                    'assignment_status' => $assignment->status->value,
                    'sign_order' => $assignment->sign_order,
                    'can_sign' => $hasSigningKey
                        && $assignment->status === SignerStatus::Pending
                        && $previousSignersAreComplete,
                ];
            });

        return Inertia::render('Signer/Documents/Index', [
            'documents' => $assignments,
            'hasSigningKey' => $hasSigningKey,
        ]);
    }

    private function signerShow(Request $request, Document $document): Response
    {
        $document->load(['creator:id,name', 'signers' => fn ($query) => $query
            ->with('user:id,name')
            ->orderBy('sign_order')]);
        $assignment = $document->signers->firstWhere('user_id', $request->user()->getKey());

        abort_unless($assignment instanceof DocumentSigner, 403);

        $hasSigningKey = $request->user()->signingKey()->exists();
        $previousSignersAreComplete = $document->signers->every(
            fn (DocumentSigner $candidate): bool => $candidate->sign_order >= $assignment->sign_order
                || $candidate->status === SignerStatus::Signed,
        );

        return Inertia::render('Signer/Documents/Show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'institution' => $document->institution,
                'document_date' => $document->document_date->toDateString(),
                'original_filename' => $document->original_filename,
                'file_size' => $document->file_size,
                'document_hash' => $document->document_hash,
                'status' => $document->status->value,
                'created_by' => $document->creator->name,
                'created_at' => $document->created_at->toIso8601String(),
                'signers' => $document->signers->map(fn (DocumentSigner $signer): array => [
                    'name' => $signer->user->name,
                    'position_title' => $signer->position_title,
                    'sign_order' => $signer->sign_order,
                    'status' => $signer->status->value,
                ]),
            ],
            'assignment' => [
                'status' => $assignment->status->value,
                'sign_order' => $assignment->sign_order,
                'can_sign' => $hasSigningKey
                    && $assignment->status === SignerStatus::Pending
                    && $previousSignersAreComplete,
                'has_signing_key' => $hasSigningKey,
                'waiting_for_previous' => ! $previousSignersAreComplete,
            ],
        ]);
    }

    private function uniqueVerificationToken(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $token = $this->documentFinalizer->generateToken();

            if (! Document::query()->where('verification_token', $token)->exists()) {
                return $token;
            }
        }

        throw new RuntimeException('Verification token collision.');
    }
}
