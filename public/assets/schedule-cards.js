/* Select a collection schedule card, and focus a card linked from a notice. */
(function () {
    'use strict';

    function initialize() {
        const grid = document.querySelector('[data-schedule-grid]');
        if (!grid) return;

        const cards = Array.from(grid.querySelectorAll('[data-schedule-card]'));
        function choose(card) {
            cards.forEach(function (other) {
                const selected = other === card;
                other.classList.toggle('is-selected', selected);
                other.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
        }

        cards.forEach(function (card) {
            card.addEventListener('click', function (event) {
                // Leave any future links or controls inside cards independently clickable.
                if (event.target.closest('a, button, input, select, textarea')) return;
                choose(card);
            });
            card.addEventListener('keydown', function (event) {
                if (event.target !== card || (event.key !== 'Enter' && event.key !== ' ')) return;
                event.preventDefault();
                choose(card);
            });
        });

        const highlightId = grid.dataset.highlightSchedule || '';
        const selected = cards.find(function (card) {
            return card.dataset.scheduleId === highlightId;
        });
        if (selected) {
            choose(selected);
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.requestAnimationFrame(function () {
                selected.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
}());
