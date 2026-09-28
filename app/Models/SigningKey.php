<?php

namespace App\Models;

use App\Enums\Algorithm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'algorithm',
    'public_key',
    'encrypted_private_key',
    'salt',
    'nonce',
    'auth_tag',
    'kdf',
    'kdf_opslimit',
    'kdf_memlimit',
])]
#[Hidden(['encrypted_private_key', 'salt', 'nonce', 'auth_tag'])]
class SigningKey extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $attributes = [
        'algorithm' => 'RSA-2048-PSS-SHA256',
        'kdf' => 'ARGON2ID13',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documentSigners(): HasMany
    {
        return $this->hasMany(DocumentSigner::class, 'key_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'algorithm' => Algorithm::class,
            'kdf_opslimit' => 'integer',
            'kdf_memlimit' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
