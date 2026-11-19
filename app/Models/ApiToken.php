<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    /** Each scope includes the ones before it: delete > write > read. */
    public const SCOPES = [
        'read'   => 'Read: look people and entries up',
        'write'  => 'Write: also add and change things',
        'delete' => 'Delete: also delete entries, tasks and relationships',
    ];

    protected $fillable = ['user_id', 'name', 'token_hash', 'token_hint', 'scopes', 'expires_at'];

    protected function casts(): array
    {
        return [
            'scopes'       => 'array',
            'expires_at'   => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at'   => 'datetime',
        ];
    }

    /**
     * Make a token. The plain text is returned once and never stored: only its hash is.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(User $user, string $name, string $scope, ?\DateTimeInterface $expiresAt): array
    {
        $plain = 'pe_' . Str::random(48);

        $token = static::create([
            'user_id'    => $user->id,
            'name'       => $name,
            'token_hash' => static::hash($plain),
            'token_hint' => substr($plain, 0, 7),
            'scopes'     => [$scope],
            'expires_at' => $expiresAt,
        ]);

        return [$token, $plain];
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function findUsable(string $plain): ?self
    {
        $token = static::with('user')->where('token_hash', static::hash($plain))->first();

        return $token && $token->isUsable() ? $token : null;
    }

    public function isUsable(): bool
    {
        return !$this->revoked_at
            && (!$this->expires_at || $this->expires_at->isFuture())
            && $this->user?->is_active;
    }

    public function allows(string $scope): bool
    {
        $rank = ['read' => 1, 'write' => 2, 'delete' => 3];
        $held = max(array_map(fn($s) => $rank[$s] ?? 0, $this->scopes ?? []) ?: [0]);

        return $held >= ($rank[$scope] ?? PHP_INT_MAX);
    }

    public function highestScope(): string
    {
        foreach (['delete', 'write', 'read'] as $scope) {
            if ($this->allows($scope)) {
                return $scope;
            }
        }
        return 'none';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
