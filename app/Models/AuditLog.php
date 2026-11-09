<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['actor_id', 'action', 'auditable_type', 'auditable_id', 'metadata', 'ip_address'])]
class AuditLog extends Model
{
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function record(User $actor, string $action, Model $target, array $metadata = [], ?string $ipAddress = null): self
    {
        return static::query()->create([
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'auditable_type' => $target::class,
            'auditable_id' => $target->getKey(),
            'metadata' => static::sanitizeMetadata($metadata),
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private static function sanitizeMetadata(array $metadata): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'password_hash',
            'access_token',
            'refresh_token',
            'client_secret',
            'authorization',
            'cookie',
            'csrf_token',
        ];
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, $sensitiveKeys, true) || str_contains($normalizedKey, 'secret')) {
                continue;
            }

            $sanitized[$key] = is_array($value)
                ? static::sanitizeMetadata($value)
                : $value;
        }

        return $sanitized;
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
