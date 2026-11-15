(() => {
    const estimates = [...document.querySelectorAll('[data-fx-estimate]')];
    if (!estimates.length) return;

    const endpoint = estimates[0].dataset.fxEndpoint;
    if (!endpoint) return;

    const rateFormat = new Intl.NumberFormat('fr-FR', {
        minimumFractionDigits: 4,
        maximumFractionDigits: 4,
    });
    const amountFormat = new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 0,
    });
    const timeFormat = new Intl.DateTimeFormat('fr-FR', {
        timeZone: 'UTC',
        dateStyle: 'short',
        timeStyle: 'medium',
    });

    async function refreshMarketRate() {
        try {
            const response = await fetch(endpoint, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Rate endpoint unavailable');

            const quote = await response.json();
            const rate = Number(quote.rate);
            const timestamp = new Date(quote.updated_at);

            for (const estimate of estimates) {
                const price = Number(estimate.dataset.fxUsd);
                if (!quote.available || !Number.isFinite(rate) || !Number.isFinite(price) || Number.isNaN(timestamp.valueOf())) {
                    estimate.textContent = 'Équivalence XOF indisponible : aucun cours de marché récent reçu.';
                    continue;
                }

                let text = `≈ ${amountFormat.format(Math.round(price * rate))} XOF · 1 USD = ${rateFormat.format(rate)} XOF · ${quote.provider} actualisé le ${timeFormat.format(timestamp)} UTC`;
                if (!quote.usable) text += ' (cours trop ancien, paiement désactivé)';
                estimate.textContent = text;
            }
        } catch {
            for (const estimate of estimates) {
                estimate.textContent = 'Cours non actualisé : le montant exact sera recalculé par le serveur à la commande.';
            }
        }
    }

    refreshMarketRate();
    window.setInterval(refreshMarketRate, 60_000);
})();
