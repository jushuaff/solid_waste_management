/* Resident household external transfer receipt form.
   Server validation and private storage remain authoritative. */
(function () {
    'use strict';
    const form = document.querySelector('[data-fee-online-form]');
    if (!form) return;

    const month = form.querySelector('#fee-submit-charge');
    const account = form.querySelector('#fee-submit-account');
    const amount = form.querySelector('#fee-submit-amount');
    const balanceHint = form.querySelector('[data-fee-balance-hint]');
    const receipt = form.querySelector('#fee-submit-proof');
    const receiptHint = form.querySelector('[data-fee-proof-hint]');

    function setAccounts() {
        const choice = month.options[month.selectedIndex];
        const city = choice ? choice.dataset.feeCity : '';
        const balance = Number(choice?.dataset.feeBalance || 0) / 100;

        if (amount) {
            amount.max = balance > 0 ? balance.toFixed(2) : '';
            if (city && amount.value === '') amount.value = balance.toFixed(2);
            if (amount.value && balance > 0 && Number(amount.value) > balance) {
                amount.value = balance.toFixed(2);
            }
        }
        if (balanceHint) {
            balanceHint.textContent = city
                ? 'Outstanding amount: ₱' + balance.toLocaleString('en-PH', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                }) + '. You may submit a partial transfer.'
                : 'Choose a billing month to see the outstanding amount.';
        }
        if (!account) return;
        let firstEligible = null;
        let selectedIsEligible = false;
        Array.from(account.options).forEach(function (option) {
            if (!option.value) return;
            const allowed = !!city && option.dataset.feeCity === city;
            option.hidden = !allowed;
            option.disabled = !allowed;
            if (allowed && !firstEligible) firstEligible = option;
            if (allowed && option.selected) selectedIsEligible = true;
        });
        if (!selectedIsEligible) {
            account.value = firstEligible ? firstEligible.value : '';
        }
    }

    month?.addEventListener('change', setAccounts);
    setAccounts();

    receipt?.addEventListener('change', function () {
        receipt.setCustomValidity('');
        const file = receipt.files && receipt.files[0];
        if (!file) {
            receiptHint.textContent = 'JPG, PNG, WebP or PDF · 5 MB maximum.';
            return;
        }
        const validMimes = ['image/jpeg','image/png','image/webp','application/pdf'];
        if (!validMimes.includes(file.type) || file.size > 5 * 1024 * 1024) {
            receipt.setCustomValidity('Choose a JPG, PNG, WebP or PDF receipt under 5 MB.');
            receiptHint.textContent = 'Receipt must be JPG, PNG, WebP or PDF and no more than 5 MB.';
            receipt.reportValidity();
            return;
        }
        receiptHint.textContent = file.name + ' · ' + (file.size / 1024 / 1024).toFixed(2) + ' MB';
    });
}());
