# Aplikasi Manajemen Proyek (Gantt Chart & Critical Path)

Aplikasi web manajemen dan penjadwalan proyek perangkat lunak, dibuat untuk
Tugas Pertemuan 6 (Bab 4) mata kuliah [NAMA MATA KULIAH]. Fitur mengikuti
materi video tentang perencanaan dan penjadwalan proyek (Gantt chart, PERT,
dan critical path). Aplikasi dibuat dengan bantuan Freebuff CLI.

**Pembuat:** Mohamad Syarief Ali Saman (531424011)

## Tampilan Aplikasi

### Gantt Chart
![Gantt Chart](docs/gantt.png)

### Analisis Jalur Kritis
![Analisis Jalur Kritis](docs/critical-path.png)

## Fitur

- **Manajemen proyek:** tambah, ubah, dan hapus proyek (nama, organisasi, website, deskripsi).
- **Manajemen tugas:** nama, tanggal mulai, durasi (hari kerja), prioritas, warna, dan sub-tugas.
- **Dependensi antar tugas:** Finish-Start, Start-Start, Finish-Finish, dan Start-Finish dengan delay. Dependensi melingkar ditolak.
- **Manajemen resource:** data anggota tim dan alokasi ke tugas.
- **Hari libur:** libur sekali (one-off) dan berulang (recurring).
- **Penjadwalan otomatis:** tanggal tugas dihitung dalam hari kerja (Sabtu-Minggu dan hari libur dilewati) sesuai dependensi.
- **Gantt chart:** daftar tugas, timeline, panah dependensi, dan tombol Show/Hide Critical Path.
- **PERT chart:** tugas sebagai node dengan panah urutan.
- **Analisis jalur kritis manual:** semua jalur, total durasi, critical path, dan simulasi keterlambatan.
- **Data contoh:** proyek PT XYZ (6 tahap SDLC, 7 anggota tim) dan proyek 10 aktivitas A-J (jalur kritis B-E-H-J = 33 hari).

## Teknologi

Laravel 13, PHP 8.3 atau lebih baru, SQLite, Blade, dan Tailwind CSS (CDN).

## Cara Menjalankan

Prasyarat: PHP 8.3+ dan Composer.

```bash
git clone https://github.com/Syarief-hub/tugasarsi
cd [tugasarsi]
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Buka `http://127.0.0.1:8000`. Di Linux atau macOS, gunakan `cp` sebagai pengganti `copy`.

Jika Laravel menanyakan pembuatan file database SQLite, jawab Yes.

## Pengujian

```bash
php artisan test
```

## Catatan

Tampilan memakai Tailwind lewat CDN sehingga membutuhkan koneksi internet.