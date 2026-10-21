# FLOWCHART SISTEM INFORMASI PARKIR BERBASIS WEB

Berikut adalah kumpulan 6 flowchart alur proses bisnis utama sistem informasi parkir yang digambar menggunakan sintaks Mermaid standar.

---

## 1. Flowchart Login dan Autentikasi Peran

```mermaid
flowchart TD
    Start([Mulai]) --> Input[User memasukkan Username & Password]
    Input --> ValidInput{Input Lengkap & CSRF Valid?}
    ValidInput -- Tidak --> AlertInput[Tampilkan Pesan Kesalahan] --> Input
    ValidInput -- Ya --> QueryUser[(Query tb_user WHERE username)]
    QueryUser --> UserExist{User Ditemukan?}
    UserExist -- Tidak --> FailLogin[Tampilkan Pesan Username/Password Salah] --> Input
    UserExist -- Ya --> VerifyPass{password_verify Cocok?}
    VerifyPass -- Tidak --> FailLogin
    VerifyPass -- Ya --> CheckAktif{status_aktif == 1?}
    CheckAktif -- Tidak --> Blocked[Tampilkan Akun Dinonaktifkan] --> End([Selesai])
    CheckAktif -- Ya --> RegenSession[session_regenerate_id]
    RegenSession --> SaveSession[Simpan Session: id, nama, role]
    SaveSession --> LogLogin[(Catat ke tb_log_aktivitas)]
    LogLogin --> CheckRole{Cek Peran / Role}
    CheckRole -- admin --> DashAdmin[Redirect ke /admin/dashboard.php]
    CheckRole -- petugas --> DashPetugas[Redirect ke /petugas/dashboard.php]
    CheckRole -- owner --> DashOwner[Redirect ke /owner/dashboard.php]
    DashAdmin --> End
    DashPetugas --> End
    DashOwner --> End
```

---

## 2. Flowchart Transaksi Parkir Masuk (Dengan Auto-Register & Concurrency Locking)

```mermaid
flowchart TD
    Start([Kendaraan Tiba di Gerbang Masuk]) --> InputPlat[Petugas Input Plat Nomor]
    InputPlat --> Normalize[Normalisasi Plat Nomor: Uppercase & Spasi Tunggal]
    Normalize --> CekPlat[(Cek tb_kendaraan)]
    CekPlat --> Terdaftar{Kendaraan Terdaftar?}
    Terdaftar -- Tidak --> AutoReg[Opsi Auto-Register On-the-Fly / Pendaftaran Baru]
    AutoReg --> SimpanKendaraan[(INSERT tb_kendaraan)] --> PilihArea
    Terdaftar -- Ya --> PilihArea[Petugas Pilih Area Parkir Sesuai Jenis]
    PilihArea --> StartTrx[(Mulai Transaksi: START TRANSACTION)]
    StartTrx --> LockArea[(SELECT tb_area_parkir FOR UPDATE)]
    LockArea --> CekKapasitas{terisi < kapasitas?}
    CekKapasitas -- Tidak/Penuh --> RollbackPenuh[(ROLLBACK Transaksi)]
    RollbackPenuh --> PesanPenuh[Tampilkan Pesan: Area Parkir Penuh] --> End([Selesai])
    CekKapasitas -- Ya/Tersedia --> LockActive[(SELECT tb_transaksi WHERE status='masuk' FOR UPDATE)]
    LockActive --> CekAktif{Ada Transaksi Aktif?}
    CekAktif -- Ya --> RollbackAktif[(ROLLBACK Transaksi)]
    RollbackAktif --> PesanAktif[Tampilkan: Kendaraan Masih Sedang Parkir] --> End
    CekAktif -- Tidak --> GetTarif[(Ambil Tarif tb_tarif Sesuai Jenis)]
    GetTarif --> GenTiket[Hasilkan Kode Tiket Unik TRX-YYMMDD-XXXX]
    GenTiket --> InsertTrx[(INSERT tb_transaksi dengan Snapshot Tarif)]
    InsertTrx --> IncArea[(UPDATE tb_area_parkir SET terisi = terisi + 1)]
    IncArea --> LogMasuk[(INSERT tb_log_aktivitas)]
    LogMasuk --> CommitMasuk[(COMMIT Transaksi Basis Data)]
    CommitMasuk --> GenQR[Generate SVG QR Code Tiket]
    GenQR --> PrintTiket[Tampilkan Tiket & Tombol Cetak 58mm/80mm]
    PrintTiket --> End
```

---

## 3. Flowchart Transaksi Parkir Keluar, Kalkulasi Biaya & Pembayaran

```mermaid
flowchart TD
    Start([Kendaraan Tiba di Gerbang Keluar]) --> ReadScan[Petugas Scan QR Tiket atau Ketik Plat Nomor]
    ReadScan --> FindTrx[(Query tb_transaksi JOIN tb_kendaraan)]
    FindTrx --> AdaTrx{Transaksi Ditemukan?}
    AdaTrx -- Tidak --> NotFound[Tampilkan: Tiket Tidak Ditemukan] --> End([Selesai])
    AdaTrx -- Ya --> CekStatus{status == 'masuk'?}
    CekStatus -- Tidak/Keluar --> SudahSelesai[Tampilkan: Transaksi Sudah Selesai & Tombol Cetak Ulang] --> End
    CekStatus -- Ya --> CalcBiaya[Hitung Durasi: selisih menit, ceil ke jam, min 1 jam. Biaya = jam * tarif_saat_masuk]
    CalcBiaya --> ShowPreview[Tampilkan Rincian Durasi & Total Tagihan]
    ShowPreview --> ChoosePayment{Pilih Metode Pembayaran}
    ChoosePayment -- Tunai --> InputCash[Petugas Input Uang Diterima]
    InputCash --> ValidateCash{Uang Diterima >= Total Tagihan?}
    ValidateCash -- Tidak --> AlertCash[Tampilkan: Uang Kurang] --> InputCash
    ValidateCash -- Ya --> CalcChange[Hitung Kembalian = Uang - Tagihan] --> BeginKeluar
    ChoosePayment -- Non-Tunai --> ScanQris[Petugas Tampilkan QRIS / EDC Simulasi]
    ScanQris --> ConfirmNonCash[Verifikasi Pembayaran Diterima] --> BeginKeluar
    BeginKeluar[(START TRANSACTION)] --> LockTrx[(SELECT tb_transaksi WHERE id FOR UPDATE)]
    LockTrx --> RecheckStatus{Masih 'masuk'?}
    RecheckStatus -- Tidak --> RollbackDouble[(ROLLBACK Transaksi)] --> End
    RecheckStatus -- Ya --> UpdateTrx[(UPDATE tb_transaksi: waktu_keluar, durasi_jam, biaya_total, metode_pembayaran, jumlah_bayar, kembalian, status_pembayaran='lunas', status='keluar')]
    UpdateTrx --> DecArea[(UPDATE tb_area_parkir SET terisi = GREATEST 0, terisi - 1)]
    DecArea --> LogKeluar[(INSERT tb_log_aktivitas)]
    LogKeluar --> CommitKeluar[(COMMIT Transaksi Basis Data)]
    CommitKeluar --> ShowStruk[Buka Halaman Struk Thermal 58mm/80mm] --> End
```

---

## 4. Flowchart Pencetakan Struk Thermal

```mermaid
flowchart TD
    Start([Buka Halaman Struk: struk.php?id=...]) --> CheckAuth{Pengguna Terautentikasi?}
    CheckAuth -- Tidak --> RedirectLogin[Redirect ke login.php] --> End([Selesai])
    CheckAuth -- Ya --> FetchData[(Query Detail Transaksi Selesai)]
    FetchData --> ValidTrx{Data Valid & Status Keluar?}
    ValidTrx -- Tidak --> ShowError[Tampilkan Peringatan Error] --> End
    ValidTrx -- Ya --> RenderHTML[Render Struk Thermal Format 58mm / 80mm]
    RenderHTML --> TriggerPrint[Petugas Klik 'Cetak Struk Thermal' atau window.print]
    TriggerPrint --> CSSPrint[Aplikasi Memuat @media print: Sembunyikan Navbar, Sidebar, Tombol]
    CSSPrint --> SendToPrinter[Kirim Data Mentah Teks ke Printer Kasir Thermal]
    SendToPrinter --> StrukFisik[Printer Mencetak Struk Kertas Kasir]
    StrukFisik --> End
```

---

## 5. Flowchart Pemindaian QR Code (Kamera & Alternatif Manual)

```mermaid
flowchart TD
    Start([Buka Modul scan_qr.php]) --> ClickStart[Petugas Klik 'Buka Kamera Pemindai']
    ClickStart --> RequestCam{Izin Kamera Diberikan & HTTPS/Localhost?}
    RequestCam -- Ditolak / Tidak Ada Kamera --> ErrorCam[Tampilkan Pesan Peringatan Akses Kamera Gagal]
    ErrorCam --> FallbackManual[Arahkan Petugas ke Input Manual Tiket / Plat]
    FallbackManual --> SubmitManual[Petugas Ketik Kode & Klik Proses] --> GoCheckout
    RequestCam -- Diberikan --> InitCam[Inisialisasi Html5Qrcode Scanner]
    InitCam --> Scanning[Kamera Memindai Objek]
    Scanning --> DetectQR{QR Code Tiket Terdeteksi?}
    DetectQR -- Belum --> Scanning
    DetectQR -- Ya --> StopCam[Hentikan Aliran Kamera]
    StopCam --> FillInput[Isi Kode Tiket Otomatis ke Form]
    FillInput --> GoCheckout[Kirim Permintaan ke /petugas/transaksi_keluar.php?kode=...]
    GoCheckout --> ServerValidate[(Validasi Kode Tiket di Server Database)]
    ServerValidate --> End([Lanjut ke Proses Pembayaran])
```

---

## 6. Flowchart Rekapitulasi & Ekspor CSV Owner

```mermaid
flowchart TD
    Start([Owner Membuka /owner/laporan.php]) --> CheckRole{Role == 'owner'?}
    CheckRole -- Tidak --> Forbidden[Tampilkan 403 Akses Ditolak] --> End([Selesai])
    CheckRole -- Ya --> InputFilter[Owner Menentukan Filter: Tanggal, Plat, Jenis, Status]
    InputFilter --> QuerySummary[(Query Agregasi SUM biaya_total & COUNT transaksi)]
    QuerySummary --> QueryTable[(Query Rincian Transaksi Sesuai Filter)]
    QueryTable --> DisplayReport[Tampilkan Kartu Ringkasan & Tabel Laporan di Layar]
    DisplayReport --> ClickExport{Owner Klik 'Ekspor ke CSV'?}
    ClickExport -- Tidak --> End
    ClickExport -- Ya --> CallExport[Request ke export_csv.php dengan Parameter Filter]
    CallExport --> StreamCSV[Buka php://output & Tulis Header UTF-8 BOM]
    StreamCSV --> SanitizeFormula[Sanitasi Spreadsheet Injection: safeCSVCell]
    SanitizeFormula --> OutputRows[Kirim Baris Data dengan Pembatas Titik Koma ;]
    OutputRows --> DownloadCSV[File Laporan_Parkir_YYYYMMDD_HHMMSS.csv Terunduh] --> End
```
