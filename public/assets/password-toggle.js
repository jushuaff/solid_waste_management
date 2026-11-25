/**
 * Accessible show/hide password control for all Laravel Blade password inputs.
 * No npm, JavaScript framework, browser storage or third-party library required.
 * This only reveals what the user has typed, never a stored password/hash.
 */
(function () {
    'use strict';

    function addToggle(input) {
        if (!input || input.dataset.visibilityInitialized === 'true') {
            return;
        }
        if (input.type !== 'password' || !input.parentNode) {
            return;
        }

        input.dataset.visibilityInitialized = 'true';

        var wrap = document.createElement('div');
        wrap.className = 'password-input-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'password-visibility-toggle';
        toggle.textContent = 'Show';
        toggle.setAttribute('aria-label', 'Show password');
        toggle.setAttribute('aria-pressed', 'false');
        if (input.id) {
            toggle.setAttribute('aria-controls', input.id);
        }
        toggle.addEventListener('click', function () {
            var revealing = input.type === 'password';
            input.type = revealing ? 'text' : 'password';
            toggle.textContent = revealing ? 'Hide' : 'Show';
            toggle.setAttribute('aria-label', revealing ? 'Hide password' : 'Show password');
            toggle.setAttribute('aria-pressed', revealing ? 'true' : 'false');
            input.focus({ preventScroll: true });
        });
        wrap.appendChild(toggle);
    }

    function initialize() {
        document.querySelectorAll('input[type="password"]').forEach(addToggle);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
}());
