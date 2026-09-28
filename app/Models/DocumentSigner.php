<?php

namespace App\Models;

use App\Enums\SignerStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id',
    'user_id',
    'sign_order',
    'position_title',
    'status',
    'key_id',
    'signature',
    'signed_at',
])]
class DocumentSigner extends Model
{
    public const UPDATED_AT = null;

    protected $attributes = [
        'status' => 'PENDING',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signingKey(): BelongsTo
    {
        return $this->belongsTo(SigningKey::class, 'key_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sign_order' => 'integer',
            'status' => SignerStatus::class,
            'signed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
