<nav class="admin-console-nav" aria-label="Navigation du centre administrateur">
    <a class="{{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}">Vue d’ensemble</a>
    <a class="{{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}" href="{{ route('admin.users.index') }}">Utilisateurs</a>
    <a class="{{ request()->routeIs('admin.payments.*','admin.manual-payments.*') ? 'is-active' : '' }}" href="{{ route('admin.payments.index') }}">Paiements</a>
    <a class="{{ request()->routeIs('admin.subscriptions.*') ? 'is-active' : '' }}" href="{{ route('admin.subscriptions.index') }}">Abonnements</a>
    <a class="{{ request()->routeIs('admin.licenses.*') ? 'is-active' : '' }}" href="{{ route('admin.licenses.index') }}">Licences</a>
    <a class="{{ request()->routeIs('admin.trading-accounts.*') ? 'is-active' : '' }}" href="{{ route('admin.trading-accounts.index') }}">Comptes MT5</a>
    <a class="{{ request()->routeIs('admin.partners.*') ? 'is-active' : '' }}" href="{{ route('admin.partners.index') }}">Partenaires</a>
    <a class="{{ request()->routeIs('admin.commissions.*') ? 'is-active' : '' }}" href="{{ route('admin.commissions.index') }}">Commissions</a>
    <a class="{{ request()->routeIs('admin.plans.*') ? 'is-active' : '' }}" href="{{ route('admin.plans.index') }}">Catalogue</a>
    <a class="{{ request()->routeIs('admin.brokers.*') ? 'is-active' : '' }}" href="{{ route('admin.brokers.index') }}">Brokers</a>
    <a class="{{ request()->routeIs('admin.support.*') ? 'is-active' : '' }}" href="{{ route('admin.support.index') }}">Support</a>
    <a class="{{ request()->routeIs('admin.brvm-orders.*') ? 'is-active' : '' }}" href="{{ route('admin.brvm-orders.index') }}">Demandes BRVM</a>
    <a class="{{ request()->routeIs('admin.contact-requests.*') ? 'is-active' : '' }}" href="{{ route('admin.contact-requests.index') }}">Contacts</a>
    <a class="{{ request()->routeIs('admin.reports.*') ? 'is-active' : '' }}" href="{{ route('admin.reports.index') }}">Rapports</a>
    <a class="{{ request()->routeIs('admin.logs.*') ? 'is-active' : '' }}" href="{{ route('admin.logs.index') }}">Journaux</a>
    <a class="{{ request()->routeIs('admin.notifications.*') ? 'is-active' : '' }}" href="{{ route('admin.notifications.index') }}">Notifications</a>
    <a class="{{ request()->routeIs('admin.settings.*','admin.brokers.*') ? 'is-active' : '' }}" href="{{ route('admin.settings.index') }}">Services & contenu</a>
</nav>
