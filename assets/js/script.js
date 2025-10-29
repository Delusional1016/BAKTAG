document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const hamburger = document.querySelector('.hamburger');
    
    if (hamburger && sidebar) {
        hamburger.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
        });
    }

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    // Password visibility toggle with event delegation
    document.querySelectorAll('.password-toggle-icon').forEach(icon => {
        icon.addEventListener('click', function() {
            const inputId = this.parentElement.querySelector('input').id;
            const input = document.getElementById(inputId);
            const toggleIcon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            } else {
                input.type = 'password';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            }
        });
    });

    // Barcode generation on input for Add New Product
    function generateBarcode() {
        const nameInput = document.getElementById('name');
        const codeInput = document.getElementById('code');
        const barcodeDisplay = document.getElementById('barcodeDisplay');
        if (nameInput && codeInput && barcodeDisplay) {
            const productName = nameInput.value.trim();
            if (productName) {
                // Generate a unique barcode (e.g., FB + timestamp + random string)
                const timestamp = Date.now().toString(36);
                const randomStr = Math.random().toString(36).substr(2, 5);
                const barcode = 'FB' + timestamp + randomStr.substr(0, 8).toUpperCase();
                codeInput.value = barcode;

                // Clear previous barcode display
                barcodeDisplay.innerHTML = '';
                // Create SVG for barcode
                const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
                svg.setAttribute("id", "barcodeSvg");
                barcodeDisplay.appendChild(svg);
                JsBarcode("#barcodeSvg", barcode, {
                    format: "CODE128",
                    width: 2,
                    height: 40,
                    displayValue: true
                });
            } else {
                codeInput.value = '';
                barcodeDisplay.innerHTML = '';
            }
        }
    }

    // Trigger barcode generation on input
    const nameInput = document.getElementById('name');
    if (nameInput) {
        nameInput.addEventListener('input', generateBarcode);
    }
});