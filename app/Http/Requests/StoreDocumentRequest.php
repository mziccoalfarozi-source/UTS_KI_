<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === Role::Admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'institution' => ['required', 'string', 'max:255'],
            'document_date' => ['required', 'date'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'signers' => ['required', 'array', 'min:1'],
            'signers.*.user_id' => [
                'required',
                'integer',
                'distinct:strict',
                Rule::exists('users', 'id')->where('role', Role::Signer->value),
            ],
            'signers.*.sign_order' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'signers.*.position_title' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $signers = $this->input('signers', []);

                if (! is_array($signers) || $signers === []) {
                    return;
                }

                $orders = collect($signers)
                    ->pluck('sign_order')
                    ->map(fn (mixed $order): int => (int) $order)
                    ->sort()
                    ->values()
                    ->all();

                if ($orders !== range(1, count($signers))) {
                    $validator->errors()->add(
                        'signers',
                        'Urutan signer harus dimulai dari 1, unik, dan berurutan tanpa gap.',
                    );
                }
            },
        ];
    }
}
