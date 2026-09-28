<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'institution',
    'document_date',
    'original_filename',
    'file_path',
    'file_size',
    'document_hash',
    'verification_token',
    'status',
    'created_by',
])]
class Document extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $attributes = [
        'status' => 'WAITING_SIGNATURE',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signers(): HasMany
    {
        return $this->hasMany(DocumentSigner::class);
    }

    public function verificationLogs(): HasMany
    {
        return $this->hasMany(VerificationLog::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'file_size' => 'integer',
            'status' => DocumentStatus::class,
            'created_at' => 'datetime',
        ];
    }
}
