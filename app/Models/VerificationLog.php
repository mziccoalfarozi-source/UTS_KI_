<?php

namespace App\Models;

use App\Enums\VerificationCode;
use App\Enums\VerificationMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_id', 'method', 'result_code', 'token_prefix'])]
class VerificationLog extends Model
{
    public const UPDATED_AT = null;

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => VerificationMethod::class,
            'result_code' => VerificationCode::class,
            'created_at' => 'datetime',
        ];
    }
}
