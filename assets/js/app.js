/**
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 * JavaScript Interaktivitas UI & Utilitas
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Sidebar Toggler untuk Layar Mobile
    const sidebarToggler = document.querySelector('.sidebar-toggler');
    const sidebar = document.querySelector('.app-sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');

    if (sidebarToggler && sidebar) {
        sidebarToggler.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            if (backdrop) backdrop.classList.toggle('show');
        });
    }

    if (backdrop && sidebar) {
        backdrop.addEventListener('click', function () {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
        });
    }

    // 2. Real-time Digital Clock (WIB)
    const clockElement = document.getElementById('live-clock');
    if (clockElement) {
        function updateClock() {
            const now = new Date();
            const options = {
                timeZone: 'Asia/Jakarta',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            };
            clockElement.textContent = now.toLocaleTimeString('id-ID', options) + ' WIB';
        }
        updateClock();
        setInterval(updateClock, 1000);
    }

    // 3. Format Otomatis Huruf Kapital pada Input Plat Nomor
    const platInputs = document.querySelectorAll('.input-plat');
    platInputs.forEach(input => {
        input.addEventListener('input', function () {
            this.value = this.value.toUpperCase();
        });
    });

    // 4. Modal / Konfirmasi Tindakan Penting
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    confirmButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            const message = this.getAttribute('data-confirm') || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
});
