@extends('layouts.app')

@section('content')
<div class="container">
    <p class="brand-accent">FASO DIGITAL TRADING · ADMINISTRATION</p>
    <h1>Contrôle des abonnements</h1>
    <p class="muted">L’activation manuelle ne confirme ni ne modifie un paiement. Vérifiez le règlement avant d’accorder l’accès. Chaque action est inscrite au journal d’audit.</p>
    @if(session('status'))<div class="success-alert" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif

    <form method="GET" class="card" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap;margin:20px 0">
        <label for="status-filter">Filtrer par statut</label>
        <select id="status-filter" name="status" style="min-width:180px">
            <option value="">Tous</option>
            @foreach(['PENDING' => 'En attente', 'ACTIVE' => 'Actifs', 'EXPIRED' => 'Expirés', 'CANCELLED' => 'Annulés'] as $value => $label)
                <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="button button-small" type="submit">Filtrer</button>
        <a class="text-link" href="{{ route('admin.subscriptions.index') }}">Réinitialiser</a>
    </form>

    <div style="overflow-x:auto">
        <table style="width:100%;min-width:920px;text-align:left;border-collapse:collapse">
            <thead><tr><th>Client</th><th>Formule</th><th>Abonnement</th><th>Paiement le plus récent</th><th>Période</th><th>Action admin</th></tr></thead>
            <tbody>
            @forelse($subscriptions as $subscription)
                @php($active = $subscription->isOperationallyActive())
                @php($latestPayment = $subscription->latestPayment)
                <tr style="border-top:1px solid var(--line)">
                    <td style="padding:14px 8px"><strong>{{ $subscription->user?->first_name }} {{ $subscription->user?->last_name }}</strong><br><small class="muted">{{ $subscription->user?->email }}</small></td>
                    <td>{{ $subscription->plan_name_snapshot ?: $subscription->plan?->name ?: 'Formule supprimée' }}</td>
                    <td><strong>{{ $active ? 'ACTIF' : ($subscription->admin_override === 'DISABLED' ? 'SUSPENDU ADMIN' : $subscription->status) }}</strong><br><small class="muted">{{ $subscription->admin_override === 'ACTIVE' ? 'Activation manuelle' : '' }}</small></td>
                    <td>{{ $latestPayment?->status ?? 'Aucun' }}<br><small class="muted">{{ $latestPayment?->amount ? number_format((float) $latestPayment->amount, 2).' '.$latestPayment->currency : '—' }}{{ $latestPayment?->provider ? ' · '.$latestPayment->provider : '' }}</small></td>
                    <td>{{ $subscription->starts_at?->format('d/m/Y') ?? '—' }}<br><small class="muted">au {{ $subscription->ends_at?->format('d/m/Y') ?? '—' }}</small></td>
                    <td style="padding:14px 8px;min-width:250px">
                        <form method="POST" action="{{ route('admin.subscriptions.control', $subscription) }}">
                            @csrf
                            <input type="hidden" name="action" value="{{ $active ? 'deactivate' : 'activate' }}">
                            <input name="reason" required minlength="5" maxlength="500" placeholder="Motif obligatoire" aria-label="Motif de l’action admin">
                            @unless($active)
                                <label>Référence de paiement externe vérifiée</label><input name="payment_reference" minlength="4" maxlength="191" required placeholder="Référence transaction / reçu">
                                <label class="check-row"><input type="checkbox" name="confirm_payment_received" value="yes" required><span>J’ai vérifié la réception réelle des fonds.</span></label>
                            @endunless
                            <button class="button button-small {{ $active ? 'button-quiet' : '' }}" type="submit" style="margin-top:7px">{{ $active ? 'Désactiver' : 'Activer après vérification' }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted" style="padding:18px">Aucun abonnement enregistré.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin:18px 0">{{ $subscriptions->links() }}</div>
</div>
@endsection
