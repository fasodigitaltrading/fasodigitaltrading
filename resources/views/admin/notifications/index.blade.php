@extends('layouts.app')
@section('content')
<div class="container">
    <p class="brand-accent">FASO DIGITAL TRADING · ADMINISTRATION</p><h1>Notifications internes</h1>
    <p class="muted">Historique des notifications enregistrées dans l’application. Cette page ne déclenche aucun e-mail ni envoi massif.</p>
    <div class="card"><p>Notifications non lues en base : <strong>{{ number_format($unreadCount) }}</strong></p></div>
    <form method="GET" class="card" style="margin:16px 0"><label>État</label><select name="status"><option value="">Toutes</option><option value="unread" @selected($selectedStatus==='unread')>Non lues</option><option value="read" @selected($selectedStatus==='read')>Lues</option></select> <button class="button button-small" type="submit">Filtrer</button></form>
    <section class="card"><div class="table-wrap"><table><thead><tr><th>Date</th><th>Destinataire</th><th>Type</th><th>Message</th><th>État</th><th></th></tr></thead><tbody>@forelse($notifications as $notification)<tr><td>{{ $notification->created_at }}</td><td>{{ $notification->recipient_email ?? 'Destinataire supprimé ou inconnu' }}</td><td>{{ class_basename($notification->type) }}</td><td><strong>{{ $notification->safe_data['title'] ?? 'Notification' }}</strong><br>{{ $notification->safe_data['subject'] ?? '' }}</td><td>{{ $notification->read_at ? 'Lue' : 'Non lue' }}</td><td>@if(!empty($notification->safe_data['url']))<a class="text-link" href="{{ $notification->safe_data['url'] }}">Ouvrir</a>@endif</td></tr>@empty<tr><td colspan="6">Aucune notification.</td></tr>@endforelse</tbody></table></div>{{ $notifications->links() }}</section>
</div>
@endsection
