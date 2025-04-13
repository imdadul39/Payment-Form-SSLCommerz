document.addEventListener('DOMContentLoaded', function () {

    const guestNumberInput = document.getElementById('sslcommerz-cus-guest-num');
    const amountDisplay = document.getElementById('payment-amount');

    const baseAmount = parseFloat(amountDisplay.textContent);

    guestNumberInput.addEventListener('input', function () {

        const guestNumber = parseInt(guestNumberInput.value);

        let totalAmount;

        if (!isNaN(guestNumber) && guestNumber > 0) {
            totalAmount = baseAmount * guestNumber + baseAmount;
        } else {
            totalAmount = baseAmount;
        }

        amountDisplay.textContent = totalAmount.toFixed(2); // দুই ঘর দশমিক সহ দেখাবে
    });


    // Form Data validator

    const form = document.getElementById('sslcommerz-payment-form');

    form.addEventListener('submit', function (e) {
        let isValid = true;
        let errorMessage = '';

        const eventTitle = document.getElementById('event_title').value.trim();
        const name = document.getElementById('sslcommerz-cus-name').value.trim();
        const email = document.getElementById('sslcommerz-cus-email').value.trim();
        const phone = document.getElementById('sslcommerz-cus-phone').value.trim();

        if (eventTitle === '') {
            isValid = false;
            errorMessage += 'Please select an Event Title.\n';
        }

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
