@extends('layouts.app')

@section('content')
<div class="container">
    <p class="brand-accent">FASO DIGITAL TRADING · ADMINISTRATION</p>
    <h1>Suivi des commissions</h1>
    <p class="muted">Les approbations exigent un paiement confirmé et la fin du délai de réserve. Le rapprochement consigne une référence de versement externe déjà effectué; la plateforme ne transfère pas d’argent.</p>

    <section class="card" aria-labelledby="commission-totals-title">
        <h2 id="commission-totals-title">Totaux enregistrés par devise et statut</h2>
        @if($totals->isEmpty())
            <p class="muted">Aucune commission enregistrée.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Devise</th><th>Statut</th><th>Total calculé</th></tr></thead>
                <tbody>@foreach($totals as $total)
                    <tr>
                        <td>{{ $total->currency }}</td>
                        <td>{{ ['PENDING'=>'En attente','APPROVED'=>'Approuvée','PAID'=>'Payée / rapprochée','REVERSED'=>'Annulée'][$total->status] ?? $total->status }}</td>
                        <td>{{ number_format((float) $total->total, 2, ',', ' ') }} {{ $total->currency }}</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>

    <form method="GET" class="card" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap;margin-top:16px">
        <label for="commission-status">Filtrer le statut</label>
        <select id="commission-status" name="status">
            <option value="">Tous les statuts</option>
            @foreach(['PENDING'=>'En attente','APPROVED'=>'Approuvée','PAID'=>'Payée / rapprochée','REVERSED'=>'Annulée'] as $value => $label)
                <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div><label for="commission-q">E-mail partenaire ou filleul</label><input id="commission-q" name="q" maxlength="120" value="{{ $search }}"></div>
        <button class="button button-small" type="submit">Filtrer</button>
    </form>

    <section class="card" style="margin-top:16px">
        <h2>Historique</h2>
        @if($commissions->isEmpty())
            <p class="muted">Aucune commission pour ce filtre.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Parrain</th><th>Filleul</th><th>Abonnement payé</th><th>Base</th><th>Commission</th><th>Statut</th><th>Réserve / versement</th><th>Action</th></tr></thead>
                <tbody>@foreach($commissions as $commission)
                    <tr>
                        <td>{{ $commission->partner?->user?->first_name }} {{ $commission->partner?->user?->last_name }}<br><span class="muted">{{ $commission->partner?->partner_code }}</span></td>
                        <td>{{ $commission->user?->first_name }} {{ $commission->user?->last_name }}<br><span class="muted">{{ $commission->user?->email }}</span></td>
                        <td>{{ $commission->payment?->subscription?->plan_name_snapshot ?? 'Abonnement' }}<br><span class="muted">Paiement {{ ['PENDING'=>'en attente','PAID'=>'confirmé','FAILED'=>'échoué','CANCELLED'=>'annulé','REFUNDED'=>'remboursé'][$commission->payment?->status] ?? 'indisponible' }}</span></td>
                        <td>{{ number_format((float) ($commission->payment?->amount ?? 0), 2, ',', ' ') }} {{ $commission->payment?->currency ?? $commission->currency }}</td>
                        <td>{{ number_format((float) $commission->amount, 2, ',', ' ') }} {{ $commission->currency }} · {{ number_format((float) $commission->percentage, 2, ',', ' ') }} %</td>
                        <td>{{ ['PENDING'=>'En attente','APPROVED'=>'Approuvée','PAID'=>'Payée / rapprochée','REVERSED'=>'Annulée'][$commission->status] ?? $commission->status }}</td>
                        <td>{{ $commission->available_at?->format('d/m/Y H:i') ?? '—' }}<br><small class="muted">{{ $commission->payout_reference ? 'Réf. versement : '.$commission->payout_reference : ($commission->paid_at ? 'Rapprochée le '.$commission->paid_at->format('d/m/Y H:i') : 'Aucun versement rapproché') }}</small></td>
                        <td>
                            @if($commission->status === 'PENDING')
                                <form method="POST" action="{{ route('admin.commissions.control',$commission) }}">@csrf<input type="hidden" name="action" value="approve"><label>Motif</label><input name="reason" minlength="10" maxlength="500" required><button class="button button-small" type="submit" @disabled($commission->payment?->status !== 'PAID' || !$commission->available_at || $commission->available_at->isFuture())>Approuver</button></form>
                            @elseif($commission->status === 'APPROVED')
                                <form method="POST" action="{{ route('admin.commissions.control',$commission) }}">@csrf<input type="hidden" name="action" value="record-payout"><label>Référence du versement effectué</label><input name="payout_reference" minlength="4" maxlength="191" required><label class="check-row"><input type="checkbox" name="confirm_payout" value="yes" required><span>Le transfert externe a réellement été effectué.</span></label><label>Motif</label><input name="reason" minlength="10" maxlength="500" required><button class="button button-small" type="submit" @disabled($commission->payment?->status !== 'PAID')>Enregistrer le rapprochement</button></form>
                            @else — @endif
                        </td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            {{ $commissions->links() }}
        @endif
    </section>
</div>
@endsection
