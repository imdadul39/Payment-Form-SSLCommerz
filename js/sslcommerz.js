document.addEventListener('DOMContentLoaded', function () {

    const guestNumberInput = document.getElementById('sslcommerz-cus-guest-num');
    const amountDisplay = document.getElementById('payment-amount');

    const baseAmount = parseFloat(amountDisplay.textContent);

    guestNumberInput.addEventListener('input', function () {

        const guestNumber = parseInt(guestNumberInput.value);

        if (!isNaN(guestNumber) && guestNumber > 0) {

            const totalAmount = baseAmount * guestNumber + baseAmount;

            amountDisplay.textContent = totalAmount.toFixed(2); // দুই ঘর দশমিক সহ দেখাবে
        }
    });


    // Form Data validator

    const form = document.getElementById('sslcommerz-payment-form');

    form.addEventListener('submit', function (e) {
        let isValid = true;
        let errorMessage = '';

        const name = document.getElementById('sslcommerz-cus-name').value.trim();
        const email = document.getElementById('sslcommerz-cus-email').value.trim();
        const phone = document.getElementById('sslcommerz-cus-phone').value.trim();

        if (name === '') {
            isValid = false;
            errorMessage += 'Full Name is required.\n';
        }

        if (email === '') {
            isValid = false;
            errorMessage += 'Email is required.\n';
        }

        if (phone === '') {
            isValid = false;
            errorMessage += 'Phone Number is required.\n';
        }

        if (!isValid) {
            e.preventDefault();  // Stop form submit
            alert(errorMessage); // Show error
        }
    });
});
