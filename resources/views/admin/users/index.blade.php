@extends('layouts.app')
@section('content')
<div class="container">
    <p class="brand-accent">FASO DIGITAL TRADING · ADMINISTRATION</p><h1>Utilisateurs</h1>
    @if(session('status'))<div class="success-alert" role="status">{{ session('status') }}</div>@endif
    <div class="grid">
        <div class="card"><p class="muted">Comptes conservés</p><h2>{{ number_format($totalUsers) }}</h2></div>
        <div class="card"><p class="muted">Actifs</p><h2>{{ number_format($activeUsers) }}</h2></div>
        <div class="card"><p class="muted">Suspendus</p><h2>{{ number_format($suspendedUsers) }}</h2></div>
        <div class="card"><p class="muted">Supprimés logiquement</p><h2>{{ number_format($deletedUsers) }}</h2></div>
    </div>
    <form method="GET" class="card" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap;margin:16px 0">
        <div><label for="users-q">Recherche</label><input id="users-q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Nom, e-mail ou téléphone"></div>
        <div><label for="users-status">Statut</label><select id="users-status" name="status"><option value="">Tous les comptes actifs</option>@foreach(['ACTIVE'=>'Actif','SUSPENDED'=>'Suspendu','PENDING'=>'En attente','UNVERIFIED'=>'E-mail non vérifié','DELETED'=>'Supprimé logiquement'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="users-pack">Abonnement</label><select id="users-pack" name="pack"><option value="">Tous les packs</option>@foreach($plans as $plan)<option value="{{ $plan->slug }}" @selected(($filters['pack'] ?? '') === $plan->slug)>{{ $plan->name }}</option>@endforeach</select></div>
        <button class="button button-small" type="submit">Filtrer</button><a class="text-link" href="{{ route('admin.users.index') }}">Réinitialiser</a>
    </form>
    <section class="card"><div class="table-wrap"><table><thead><tr><th>Compte</th><th>Statut</th><th>E-mail</th><th>Dernier abonnement</th><th>Historique</th><th></th></tr></thead><tbody>
        @forelse($users as $user)
            <tr><td><strong>{{ $user->first_name }} {{ $user->last_name }}</strong><br><span class="muted">{{ $user->role }} · créé le {{ $user->created_at?->format('d/m/Y') }}</span></td><td>{{ $user->trashed() ? 'SUPPRIMÉ LOGIQUEMENT' : $user->status }}</td><td>{{ $user->email }}<br><span class="muted">{{ $user->email_verified_at ? 'vérifié' : 'non vérifié' }}</span></td><td>{{ $user->latestSubscription?->plan_name_snapshot ?? $user->latestSubscription?->plan?->name ?? 'Aucun' }}<br><span class="muted">{{ $user->latestSubscription?->status ?? '—' }}</span></td><td>{{ $user->subscriptions_count }} abo. · {{ $user->payments_count }} paiements · {{ $user->licenses_count }} licences</td><td><a class="button button-small button-quiet" href="{{ route('admin.users.show', $user->id) }}">Ouvrir</a></td></tr>
        @empty<tr><td colspan="6" class="muted">Aucun compte pour ces filtres.</td></tr>@endforelse
    </tbody></table></div>{{ $users->links() }}</section>
</div>
@endsection
