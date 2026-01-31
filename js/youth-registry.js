// Youth Registry JS: search, filter, modal, QR, age calc
// Requires: QRCode.js (for QR code generation)

document.addEventListener('DOMContentLoaded', function () {
    // Age auto-calc in registration form
    const bdayInput = document.querySelector('#youth-register-form input[name="birthday"]');
    const ageInput = document.querySelector('#youth-register-form input[name="age"]');
    if (bdayInput && ageInput) {
        bdayInput.addEventListener('input', function () {
            const val = this.value;
            if (!val) { ageInput.value = ''; return; }
            const birth = new Date(val);
            const today = new Date();
            let age = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            ageInput.value = age >= 0 ? age : '';
        });
    }

    // Modal open/close logic
    window.openYouthModal = function(memberId) {
        document.getElementById('youth-details-modal').style.display = 'flex';
        // For demo: set QR code and member id
        document.getElementById('modal-member-id').textContent = memberId;
        if (window.QRCode) {
            const qrCanvas = document.getElementById('modal-qr-code');
            qrCanvas.getContext('2d').clearRect(0,0,qrCanvas.width,qrCanvas.height);
            new QRCode(qrCanvas, { text: memberId, width: 120, height: 120 });
        }
    };
    window.closeYouthModal = function() {
        document.getElementById('youth-details-modal').style.display = 'none';
    };

    // Simple search/filter (static demo)
    const searchInput = document.getElementById('youth-search');
    const barangayFilter = document.getElementById('barangay-filter');
    const table = document.getElementById('youth-table');
    if (searchInput && barangayFilter && table) {
        function filterTable() {
            const search = searchInput.value.toLowerCase();
            const brgy = barangayFilter.value;
            Array.from(table.tBodies[0].rows).forEach(row => {
                const cells = row.children;
                const matches = (!search || row.textContent.toLowerCase().includes(search)) &&
                    (!brgy || cells[3].textContent === brgy);
                row.style.display = matches ? '' : 'none';
            });
        }
        searchInput.addEventListener('input', filterTable);
        barangayFilter.addEventListener('change', filterTable);
    }
});
