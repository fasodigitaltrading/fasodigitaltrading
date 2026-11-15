<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminAuditLogger
{
    public function record(
        Request $request,
        string $action,
        ?Model $entity = null,
        array $before = [],
        array $after = [],
    ): void {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'entity_type' => $entity ? $entity::class : null,
            'entity_id' => $entity?->getKey(),
            'old_values' => $before ? json_encode($this->redact($before), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $after ? json_encode($this->redact($after), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function redact(array $values): array
    {
        $safe = [];
        foreach ($values as $key => $value) {
            if (preg_match('/password|secret|token|api.?key|license|otp|credential|private.?key|access.?key/i', (string) $key)) {
                $safe[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $safe[$key] = $this->redact($value);
            } else {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
