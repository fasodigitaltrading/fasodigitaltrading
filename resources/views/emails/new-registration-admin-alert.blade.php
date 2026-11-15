<h1>Nouvelle inscription FASO DIGITAL TRADING</h1>
<p>Un nouveau compte investisseur a été créé.</p>
<ul>
    <li>Nom : {{ $registeredUser->first_name }} {{ $registeredUser->last_name }}</li>
    <li>E-mail : {{ $registeredUser->email }}</li>
    <li>Pays : {{ $registeredUser->country ?: 'Non renseigné' }}</li>
    <li>Date : {{ $registeredUser->created_at?->format('d/m/Y H:i') }}</li>
</ul>
