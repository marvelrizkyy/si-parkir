<?php
/**
 * Footer Layout Template
 * Sistem Informasi Parkir Berbasis Web (SMK RPL)
 */
?>
<?php if (isLoggedIn()): ?>
        </main>
        
        <footer class="py-3 px-4 bg-white border-top text-center text-muted small mt-auto no-print">
            <div class="container-fluid">
                <span>&copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong> &mdash; Uji Kompetensi Keahlian Rekayasa Perangkat Lunak (RPL).</span>
            </div>
        </footer>
    </div>
</div>
<?php endif; ?>

<!-- Bootstrap 5.3 Bundle JS (with Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS Application -->
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script src="<?= BASE_URL ?>/assets/js/qr-scanner.js"></script>

</body>
</html>
