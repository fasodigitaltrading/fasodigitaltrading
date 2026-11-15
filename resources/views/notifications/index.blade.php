@extends('layouts.app')
@section('content')
<div class="container"><p class="brand-accent">ACTUALITÉS</p><h1>Notifications</h1>
    @forelse($notifications as $notification)
        <div class="card" style="margin-top:12px"><h3>{{ $notification->data['title'] ?? 'Notification' }}</h3><p>{{ $notification->data['subject'] ?? '' }}</p>
            @if(!$notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf<button class="btn" type="submit">Ouvrir</button></form>@else<a class="btn secondary" href="{{ $notification->data['url'] ?? route('notifications.index') }}">Ouvrir</a>@endif
        </div>
    @empty<p class="muted">Aucune notification.</p>@endforelse
</div>
@endsection
