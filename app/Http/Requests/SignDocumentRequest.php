<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SignDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $this->user()?->role === Role::Signer
            && $document instanceof Document
            && $document->signers()->where('user_id', $this->user()->getKey())->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'signing_passphrase' => ['required', 'string', 'max:4096'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->request->remove('signing_passphrase');

        parent::failedValidation($validator);
    }
}
