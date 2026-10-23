<?php

namespace Pipeandpiper\ApiTokens\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $sha256
 * @property string $token_prefix
 * @property string $token_suffix
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read string $display_token
 * @method static create(array $array)
 */
class ApiToken extends Model
{
    protected $table = 'api_tokens';

    protected $fillable = [
        'user_id',
        'name',
        'sha256',
        'token_prefix',
        'token_suffix',
        'expires_at',
        'revoked_at',
    ];

    public const array API_ALLOWED_METHODS = [ ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDisplayTokenAttribute(): string
    {
        return "$this->token_prefix...$this->token_suffix";
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isActive(): bool
    {
        return !$this->isExpired() && !$this->isRevoked();
    }
}
