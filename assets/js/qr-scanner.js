/**
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 * Modul QR Scanner menggunakan Html5Qrcode Library
 */

let html5QrCodeScanner = null;
let isScanning = false;

function initQrScanner(targetInputId, autoSubmitFormId = null) {
    const readerElement = document.getElementById('qr-reader');
    const startBtn = document.getElementById('btn-start-scanner');
    const stopBtn = document.getElementById('btn-stop-scanner');
    const statusMsg = document.getElementById('scanner-status');
    const targetInput = document.getElementById(targetInputId);

    if (!readerElement || !startBtn) return;

    if (typeof Html5Qrcode === 'undefined') {
        if (statusMsg) {
            statusMsg.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Library Html5Qrcode belum dimuat. Pastikan terhubung internet jika menggunakan CDN.</span>';
        }
        return;
    }

    html5QrCodeScanner = new Html5Qrcode("qr-reader");

    startBtn.addEventListener('click', function () {
        if (isScanning) return;

        statusMsg.innerHTML = '<span class="text-primary"><i class="bi bi-camera"></i> Mengakses kamera perangkat...</span>';
        
        const config = {
            fps: 10,
            qrbox: { width: 250, height: 250 }
        };

        // Mulai kamera (utamakan kamera belakang jika perangkat mobile)
        html5QrCodeScanner.start(
            { facingMode: "environment" },
            config,
            (decodedText, decodedResult) => {
                // QR Code berhasil dipindai
                statusMsg.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle"></i> Terdeteksi: ${decodedText}</span>`;
                
                // Masukkan hasil ke input
                if (targetInput) {
                    targetInput.value = decodedText;
                }

                // Hentikan scanner setelah berhasil
                stopScanner();

                // Auto submit form jika disetel
                if (autoSubmitFormId) {
                    const form = document.getElementById(autoSubmitFormId);
                    if (form) {
                        form.submit();
                    }
                }
            },
            (errorMessage) => {
                // Error scanning frame biasa (bukan error fatal)
            }
        ).then(() => {
            isScanning = true;
            startBtn.classList.add('d-none');
            if (stopBtn) stopBtn.classList.remove('d-none');
            statusMsg.innerHTML = '<span class="text-success"><i class="bi bi-camera-video"></i> Kamera aktif. Arahkan QR Code tiket ke dalam kotak pemindai.</span>';
        }).catch((err) => {
            isScanning = false;
            statusMsg.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle"></i> Gagal membuka kamera: ${err}. Pastikan izin kamera diberikan atau gunakan input manual di bawah.</span>`;
        });
    });

    if (stopBtn) {
        stopBtn.addEventListener('click', function () {
            stopScanner();
        });
    }

    function stopScanner() {
        if (html5QrCodeScanner && isScanning) {
            html5QrCodeScanner.stop().then(() => {
                isScanning = false;
                startBtn.classList.remove('d-none');
                if (stopBtn) stopBtn.classList.add('d-none');
                if (statusMsg) {
                    statusMsg.innerHTML = '<span class="text-muted"><i class="bi bi-info-circle"></i> Kamera dihentikan.</span>';
                }
            }).catch((err) => {
                console.error("Gagal menghentikan scanner: ", err);
            });
        }
    }
}
