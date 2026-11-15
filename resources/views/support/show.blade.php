@extends('layouts.app')
@section('content')
<div class="container">
    <a href="{{ route('support.index') }}">← Support</a>
    <p class="brand-accent">{{ $ticket->status }} · {{ $ticket->priority ?? 'NORMAL' }}</p><h1>{{ $ticket->subject }}</h1>
    @if(session('status'))<div class="success-alert" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<p style="color:#ff8080">{{ $errors->first() }}</p>@endif
    @if(auth()->user()->role === 'ADMIN')
        <section class="card" style="margin-top:16px"><h2>Gestion du dossier</h2><form method="POST" action="{{ route('admin.support.update',$ticket) }}">@csrf @method('PUT')<div class="grid"><div><label>État</label><select name="status">@foreach(\App\Models\SupportTicket::STATUSES as $status)<option value="{{ $status }}" @selected($ticket->status===$status)>{{ $status }}</option>@endforeach</select></div><div><label>Priorité</label><select name="priority">@foreach(\App\Models\SupportTicket::PRIORITIES as $priority)<option value="{{ $priority }}" @selected(($ticket->priority??'NORMAL')===$priority)>{{ $priority }}</option>@endforeach</select></div><div><label>Catégorie</label><select name="category">@foreach(\App\Models\SupportTicket::CATEGORIES as $category)<option value="{{ $category }}" @selected(($ticket->category??'GENERAL')===$category)>{{ $category }}</option>@endforeach</select></div><div><label>Motif de modification</label><input name="reason" minlength="5" maxlength="500" required></div></div><p class="muted">Assigné à {{ $ticket->assignedAdmin?->email ?? 'personne' }}. Enregistrer vous assigne ce dossier.</p><button class="button button-small" type="submit">Enregistrer le dossier</button></form></section>
    @endif
    @foreach($ticket->messages as $message)
        <article class="card" style="margin-top:12px"><p class="muted">{{ $message->is_internal ? 'NOTE INTERNE · invisible au client · ' : '' }}{{ $message->user->first_name }} · {{ $message->created_at->format('d/m/Y H:i') }}</p><p style="white-space:pre-wrap">{{ $message->body }}</p></article>
    @endforeach
    @can('reply', $ticket)
        <div class="card" style="margin-top:20px"><form method="POST" action="{{ route('support.reply', $ticket) }}">@csrf
            <label>Votre réponse</label><textarea name="body" maxlength="20000" required></textarea>
            <button class="btn" type="submit">Répondre</button>
        </form></div>
    @endcan
    @if(auth()->user()->role === 'ADMIN')
        <div class="card" style="margin-top:16px"><h2>Note interne</h2><p class="muted">Cette note n’est pas transmise au client.</p><form method="POST" action="{{ route('admin.support.internal-note',$ticket) }}">@csrf<textarea name="body" maxlength="10000" required></textarea><button class="button button-quiet" type="submit">Ajouter une note interne</button></form></div>
    @endif
</div>
@endsection
