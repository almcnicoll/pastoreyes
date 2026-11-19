<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Model;

/** A remembered response to a POST with an Idempotency-Key, so an agent's retry doesn't write twice. */
class ApiIdempotencyKey extends Model
{
    protected $fillable = ['user_id', 'api_token_id', 'key', 'request_hash', 'response_status', 'response', 'expires_at'];

    protected function casts(): array
    {
        return [
            'response'   => EncryptedCast::class, // the stored response names people
            'expires_at' => 'datetime',
        ];
    }
}
