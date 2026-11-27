/* Receiving account QR preview and client-side upload feedback.
   Server-side image type and size checks remain authoritative. */
(function () {
    'use strict';

    const maxBytes = 2 * 1024 * 1024;
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

    document.querySelectorAll('[data-fee-qr-upload]').forEach(function (container) {
        const input = container.querySelector('input[type="file"][name="qr_code"]');
        const preview = container.querySelector('[data-fee-qr-preview]');
        const filename = container.querySelector('[data-fee-qr-filename]');
        const remove = container.querySelector('input[name="remove_qr"]');
        if (!input || !preview || !filename) return;

        let objectUrl = null;
        const release = function () {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
            preview.removeAttribute('src');
            preview.hidden = true;
        };

        input.addEventListener('change', function () {
            release();
            input.setCustomValidity('');
            const file = input.files && input.files[0];
            if (!file) {
                filename.textContent = '';
                return;
            }
            if (!allowedTypes.includes(file.type)) {
                input.setCustomValidity('Choose a JPG, PNG or WebP QR code image.');
                filename.textContent = 'Unsupported image format.';
                input.reportValidity();
                return;
            }
            if (file.size > maxBytes) {
                input.setCustomValidity('The QR code must be 2 MB or smaller.');
                filename.textContent = 'The selected image exceeds 2 MB.';
                input.reportValidity();
                return;
            }
            if (remove) remove.checked = false;
            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            preview.hidden = false;
            filename.textContent = file.name + ' · ' + (file.size / 1024 / 1024).toFixed(2) + ' MB';
        });

        window.addEventListener('pagehide', release, { once: true });
    });
}());
