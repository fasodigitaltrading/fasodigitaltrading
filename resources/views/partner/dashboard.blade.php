@extends('layouts.app')

@section('content')
<section class="container section">
    <span class="eyebrow">FASO DIGITAL TRADING · PARRAINAGE</span>
    <h1>Mon espace partenaire</h1>
    @if(session('status'))<div class="success-alert" role="status">{{ session('status') }}</div>@endif

    <div class="grid">
        <div class="card"><p class="muted">Filleuls inscrits</p><h2>{{ number_format($partner->referrals_count) }}</h2></div>
        @foreach($commissionTotals as $total)
            <div class="card"><p class="muted">Commissions hors annulations · {{ $total->currency }}</p><h2>{{ number_format((float) $total->total, 2, ',', ' ') }}</h2></div>
        @endforeach
    </div>

    <section class="content-panel" style="margin-top:20px">
        <h2>Votre lien de parrainage</h2>
        <p>Code : <strong>{{ $partner->partner_code }}</strong> · Taux enregistré : <strong>{{ number_format((float) $partner->commission_rate, 2, ',', ' ') }} %</strong></p>
        <p><a class="text-link" href="{{ $referralUrl }}">{{ $referralUrl }}</a></p>
        <p class="muted">Une commission est créée uniquement après confirmation réelle d’un paiement d’abonnement. Un paiement annulé ou non réglé ne crée pas de commission. Les renouvellements payés sont comptabilisés séparément.</p>
    </section>

    <section style="margin-top:32px">
        <h2>Mes filleuls</h2>
        @if($referrals->isEmpty())
            <div class="empty-state">Aucune inscription attribuée à ce code.</div>
        @else
            <div style="overflow-x:auto"><table style="width:100%;text-align:left;border-collapse:collapse"><thead><tr><th>Nom</th><th>Date d’inscription</th></tr></thead><tbody>
                @foreach($referrals as $referral)<tr><td>{{ $referral->user?->first_name }} {{ $referral->user?->last_name }}</td><td>{{ $referral->created_at?->format('d/m/Y') }}</td></tr>@endforeach
            </tbody></table></div>
            {{ $referrals->links() }}
        @endif
    </section>

    <section style="margin-top:32px">
        <h2>Historique des commissions</h2>
        @if($commissions->isEmpty())
            <div class="empty-state">Aucune commission enregistrée.</div>
        @else
            <div style="overflow-x:auto"><table style="width:100%;text-align:left;border-collapse:collapse"><thead><tr><th>Filleul</th><th>Abonnement</th><th>Base réglée</th><th>Commission</th><th>État</th><th>Disponible le</th></tr></thead><tbody>
                @foreach($commissions as $commission)
                    <tr>
                        <td>{{ $commission->user?->first_name }} {{ $commission->user?->last_name }}</td>
                        <td>{{ $commission->payment?->subscription?->plan_name_snapshot ?? 'Abonnement' }}</td>
                        <td>{{ number_format((float) ($commission->payment?->amount ?? 0), 2, ',', ' ') }} {{ $commission->currency }}</td>
                        <td>{{ number_format((float) $commission->amount, 2, ',', ' ') }} {{ $commission->currency }} · {{ number_format((float) $commission->percentage, 2, ',', ' ') }} %</td>
                        <td>{{ ['PENDING'=>'En attente','APPROVED'=>'Approuvée','PAID'=>'Payée','REVERSED'=>'Annulée'][$commission->status] ?? $commission->status }}</td>
                        <td>{{ $commission->available_at?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody></table></div>
            {{ $commissions->links() }}
        @endif
    </section>
</section>
@endsection
