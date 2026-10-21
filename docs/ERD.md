# ENTITY RELATIONSHIP DIAGRAM (ERD)
## SISTEM INFORMASI PARKIR BERBASIS WEB (6 TABEL UTAMA)

Dokumen ini memuat diagram relasi antar tabel (ERD) beserta spesifikasi Primary Key (PK), Foreign Key (FK), dan kardinalitas relasi untuk basis data `parkir`.

```mermaid
erDiagram
    tb_user ||--o{ tb_kendaraan : "mendaftarkan (1:N)"
    tb_user ||--o{ tb_transaksi : "melayani (1:N)"
    tb_user ||--o{ tb_log_aktivitas : "melakukan (1:N)"
    tb_kendaraan ||--o{ tb_transaksi : "memiliki (1:N)"
    tb_tarif ||--o{ tb_transaksi : "mengacu (1:N)"
    tb_area_parkir ||--o{ tb_transaksi : "menampung (1:N)"

    tb_user {
        int id_user PK "Auto Increment"
        varchar nama_lengkap "NOT NULL (50)"
        varchar username "UNIQUE, NOT NULL (50)"
        varchar password "NOT NULL (255) Bcrypt Hash"
        enum role "'admin','petugas','owner'"
        tinyint status_aktif "DEFAULT 1"
    }

    tb_kendaraan {
        int id_kendaraan PK "Auto Increment"
        varchar plat_nomor "UNIQUE, NOT NULL (15)"
        varchar jenis_kendaraan "NOT NULL (20)"
        varchar warna "NULL (20)"
        varchar pemilik "NULL (100)"
        int id_user FK "Referensi ke tb_user.id_user"
    }

    tb_tarif {
        int id_tarif PK "Auto Increment"
        enum jenis_kendaraan "UNIQUE, 'motor','mobil','lainnya'"
        decimal tarif_per_jam "DECIMAL(10,0) NOT NULL"
    }

    tb_area_parkir {
        int id_area PK "Auto Increment"
        varchar nama_area "NOT NULL (50)"
        int kapasitas "NOT NULL (> 0)"
        int terisi "DEFAULT 0"
    }

    tb_transaksi {
        int id_parkir PK "Auto Increment"
        varchar kode_tiket "UNIQUE, NOT NULL (64)"
        int id_kendaraan FK "Referensi ke tb_kendaraan.id_kendaraan"
        datetime waktu_masuk "NOT NULL"
        datetime waktu_keluar "NULL"
        int id_tarif FK "Referensi ke tb_tarif.id_tarif"
        decimal tarif_per_jam_saat_masuk "DECIMAL(10,0) NOT NULL (Snapshot Tarif)"
        int durasi_jam "NULL"
        decimal biaya_total "DECIMAL(10,0) NULL"
        enum metode_pembayaran "'tunai','non-tunai' NULL"
        decimal jumlah_bayar "DECIMAL(10,0) NULL"
        decimal kembalian "DECIMAL(10,0) NULL"
        enum status_pembayaran "'belum_bayar','lunas' DEFAULT 'belum_bayar'"
        datetime waktu_pembayaran "NULL"
        enum status "'masuk','keluar' DEFAULT 'masuk'"
        int id_user FK "Referensi ke tb_user.id_user"
        int id_area FK "Referensi ke tb_area_parkir.id_area"
    }

    tb_log_aktivitas {
        int id_log PK "Auto Increment"
        int id_user FK "Referensi ke tb_user.id_user"
        varchar aktivitas "NOT NULL (255)"
        datetime waktu_aktivitas "DEFAULT CURRENT_TIMESTAMP"
    }
```

### Keterangan Relasi & Kardinalitas:
1. **`tb_user` &rarr; `tb_kendaraan` (One to Many / 1:N)**: Satu pengguna (petugas/admin) dapat mendaftarkan banyak kendaraan ke dalam sistem.
2. **`tb_user` &rarr; `tb_transaksi` (One to Many / 1:N)**: Satu petugas dapat menangani banyak transaksi kendaraan masuk dan keluar.
3. **`tb_user` &rarr; `tb_log_aktivitas` (One to Many / 1:N)**: Setiap pengguna tercatat melakukan banyak log aktivitas.
4. **`tb_kendaraan` &rarr; `tb_transaksi` (One to Many / 1:N)**: Satu kendaraan dapat memiliki banyak riwayat transaksi parkir sepanjang waktu.
5. **`tb_tarif` &rarr; `tb_transaksi` (One to Many / 1:N)**: Satu tarif kendaraan menjadi referensi untuk banyak transaksi.
6. **`tb_area_parkir` &rarr; `tb_transaksi` (One to Many / 1:N)**: Satu area parkir menampung banyak transaksi parkir.
