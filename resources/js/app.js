import QRCode from 'qrcode';

const updateReviewCountdowns = () => {
    document.querySelectorAll('[data-review-countdown][data-deadline-ms]').forEach((element) => {
        const deadline = Number(element.getAttribute('data-deadline-ms'));
        if (!Number.isFinite(deadline)) return;

        const remainingMinutes = Math.ceil((deadline - Date.now()) / 60_000);
        element.textContent = remainingMinutes > 0
            ? `Objectif indicatif : encore ${remainingMinutes} min`
            : `Délai indicatif dépassé de ${Math.abs(remainingMinutes)} min · aucun paiement n’est validé automatiquement`;
        element.setAttribute('data-review-state', remainingMinutes > 0 ? 'on-time' : 'overdue');
        element.style.color = remainingMinutes > 0 ? '' : '#ff7d62';
    });
};

updateReviewCountdowns();
if (document.querySelector('[data-review-countdown]')) {
    window.setInterval(updateReviewCountdowns, 30_000);
}

document.querySelectorAll('[data-qr-address]').forEach((canvas) => {
    const address = canvas.getAttribute('data-qr-address');
    if (!address) return;

    QRCode.toCanvas(canvas, address, {
        errorCorrectionLevel: 'M',
        margin: 2,
        width: 180,
        color: { dark: '#10131b', light: '#ffffff' },
    }).catch(() => {
        const notice = document.createElement('p');
        notice.className = 'field-help';
        notice.setAttribute('role', 'status');
        notice.textContent = 'QR indisponible. Copiez l’adresse affichée.';
        canvas.replaceWith(notice);
    });
});

document.querySelectorAll('[data-copy-address]').forEach((button) => {
    button.addEventListener('click', async () => {
        const address = button.getAttribute('data-copy-address') ?? '';
        const feedback = button.parentElement?.querySelector('[data-copy-feedback]');
        try {
            await navigator.clipboard.writeText(address);
            if (feedback) feedback.textContent = 'Adresse copiée.';
        } catch {
            if (feedback) feedback.textContent = 'Copie automatique refusée. Sélectionnez et copiez l’adresse affichée.';
        }
    });
});
