<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', 'in:audit,security'],
            'q' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        if (!empty($filters['from']) && $this->rangeTooWide($filters['from'], $filters['to'] ?? null)) {
            throw ValidationException::withMessages(['from' => 'La période de recherche est limitée à 366 jours.']);
        }

        $kind = $filters['type'] ?? 'audit';
        $logs = $kind === 'security'
            ? $this->securityQuery($filters)->paginate(50)->withQueryString()
            : $this->auditQuery($filters)->paginate(50)->withQueryString();
        $logs->getCollection()->transform(function (object $row) use ($kind): object {
            $row->safe_context = $kind === 'audit'
                ? $this->safeAuditJson($row->old_values, $row->new_values)
                : $this->safeJson($row->context);
            return $row;
        });

        return view('admin.logs.index', ['logs' => $logs, 'filters' => $filters, 'kind' => $kind]);
    }

    private function auditQuery(array $filters): Builder
    {
        return DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->select('audit_logs.*', 'users.email as actor_email')
            ->when($filters['q'] ?? null, fn ($query, $needle) => $query->where(fn ($where) => $where->where('action', 'like', '%'.$needle.'%')->orWhere('entity_type', 'like', '%'.$needle.'%')->orWhere('users.email', 'like', '%'.$needle.'%')))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('audit_logs.created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('audit_logs.created_at', '<=', $date))
            ->orderByDesc('audit_logs.id');
    }

    private function securityQuery(array $filters): Builder
    {
        return DB::table('security_logs')->leftJoin('users', 'users.id', '=', 'security_logs.user_id')
            ->select('security_logs.*', 'users.email as actor_email')
            ->when($filters['q'] ?? null, fn ($query, $needle) => $query->where(fn ($where) => $where->where('event', 'like', '%'.$needle.'%')->orWhere('users.email', 'like', '%'.$needle.'%')))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('security_logs.created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('security_logs.created_at', '<=', $date))
            ->orderByDesc('security_logs.id');
    }

    private function safeJson(?string $serialized): string
    {
        if (!$serialized) {
            return '';
        }
        $decoded = json_decode($serialized, true);
        if (!is_array($decoded)) {
            return '[Données historiques masquées]';
        }
        $safe = $this->redact($decoded);
        return json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '[Données masquées]';
    }

    private function safeAuditJson(?string $old, ?string $new): string
    {
        $before = $old ? json_decode($old, true) : [];
        $after = $new ? json_decode($new, true) : [];
        if (!is_array($before) || !is_array($after)) {
            return '[Données historiques masquées]';
        }
        return json_encode($this->redact(['avant' => $before, 'après' => $after]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ?: '[Données masquées]';
    }

    private function rangeTooWide(string $from, ?string $to): bool
    {
        $start = \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $from);
        $end = $to ? \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $to) : \Carbon\CarbonImmutable::today();
        return !$start || !$end || $start->diffInDays($end, false) > 366;
    }

    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (preg_match('/password|secret|token|api.?key|license|otp|credential|private.?key|access.?key/i', (string) $key)) {
                $data[$key] = '[MASQUÉ]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }
        return $data;
    }
}
