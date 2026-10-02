/**
 * Magal Creator Multi-Vendor Marketplace
 * Dynamic Real-Time Offer Countdown Engine
 * Location: /assets/js/countdown.js
 */

document.addEventListener('DOMContentLoaded', () => {
    initOfferCountdowns();
});

/**
 * Initializes all countdown ticker elements across the page
 */
function initOfferCountdowns() {
    const countdownElements = document.querySelectorAll('[data-countdown-target]');
    if (!countdownElements.length) return;

    // Run immediately once
    updateAllCountdowns(countdownElements);

    // Tick every 1000 milliseconds
    setInterval(() => {
        updateAllCountdowns(countdownElements);
    }, 1000);
}

/**
 * Updates every countdown element on the current page
 * @param {NodeList} elements
 */
function updateAllCountdowns(elements) {
    const now = new Date().getTime();

    elements.forEach(el => {
        const targetIso = el.getAttribute('data-countdown-target');
        const countdownType = el.getAttribute('data-countdown-type') || 'end'; // 'end' or 'start'
        const normalPrice = el.getAttribute('data-normal-price');

        if (!targetIso) return;

        const targetTime = new Date(targetIso).getTime();
        const diff = targetTime - now;

        const digitsContainer = el.querySelector('.countdown-digits') || el;

        if (diff <= 0) {
            // Timer expired!
            if (countdownType === 'end') {
                el.innerHTML = '<i class="fa-solid fa-clock-rotate-left"></i> <span style="font-weight:600;opacity:0.9;">Offer Ended</span>';
                el.classList.add('countdown-expired');

                // If on product details page or card has normal price tag, restore normal price
                const card = el.closest('.product-card') || document.querySelector('.product-details-container');
                if (card && normalPrice) {
                    const priceCurrent = card.querySelector('.price-current');
                    const priceOriginal = card.querySelector('.price-original');
                    const discountTag = card.querySelector('.price-discount-tag');
                    const offerBadge = card.querySelector('.badge-offer');

                    if (priceCurrent) priceCurrent.textContent = normalPrice;
                    if (priceOriginal) priceOriginal.style.display = 'none';
                    if (discountTag) discountTag.style.display = 'none';
                    if (offerBadge) offerBadge.style.display = 'none';
                }
            } else if (countdownType === 'start') {
                el.innerHTML = '<i class="fa-solid fa-bolt"></i> <span style="font-weight:700;color:#fbbf24;">Offer Active Now!</span>';
                // Trigger reload in 3 seconds to re-fetch backend offer prices
                setTimeout(() => { window.location.reload(); }, 3000);
            }
            return;
        }

        // Calculate Days, Hours, Minutes, Seconds
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        // Pad with leading zeroes
        const pad = (n) => String(n).padStart(2, '0');

        let displayString = '';
        if (days > 0) {
            displayString = `${days}d ${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
        } else {
            displayString = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
        }

        if (digitsContainer) {
            digitsContainer.textContent = displayString;
        }
    });
}

