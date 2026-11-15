<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', 'in:unread,read']]);
        $notifications = DB::table('notifications')
            ->leftJoin('users', function ($join): void {
                $join->on('users.id', '=', 'notifications.notifiable_id')->where('notifications.notifiable_type', '=', \App\Models\User::class);
            })
            ->select('notifications.id', 'notifications.type', 'notifications.data', 'notifications.read_at', 'notifications.created_at', 'users.email as recipient_email')
            ->when(($filters['status'] ?? '') === 'unread', fn ($query) => $query->whereNull('notifications.read_at'))
            ->when(($filters['status'] ?? '') === 'read', fn ($query) => $query->whereNotNull('notifications.read_at'))
            ->orderByDesc('notifications.created_at')->paginate(40)->withQueryString();
        $notifications->getCollection()->transform(function (object $row): object {
            $data = json_decode((string) $row->data, true);
            $row->safe_data = is_array($data) ? array_intersect_key($data, array_flip(['title', 'subject', 'url'])) : [];
            $url = (string) ($row->safe_data['url'] ?? '');
            $host = parse_url($url, PHP_URL_HOST);
            $scheme = parse_url($url, PHP_URL_SCHEME);
            $path = parse_url($url, PHP_URL_PATH);
            if (($host !== null && strcasecmp($host, request()->getHost()) !== 0)
                || ($scheme !== null && !in_array(strtolower($scheme), ['http', 'https'], true))
                || ($host === null && ($scheme !== null || !is_string($path) || !str_starts_with($url, '/') || str_starts_with($url, '//')))) {
                unset($row->safe_data['url']);
            }
            return $row;
        });

        return view('admin.notifications.index', [
            'notifications' => $notifications,
            'selectedStatus' => $filters['status'] ?? '',
            'unreadCount' => DB::table('notifications')->whereNull('read_at')->count(),
        ]);
    }
}
