# ANAKTUMBUH.ID — Backup & Restore Database (OPS-001)

**Dibuat oleh:** Anggota B
**Status:** Free tier Supabase — **tidak ada backup otomatis dari Supabase** (Daily Backup & Point-in-Time Recovery hanya tersedia di tier Pro ke atas). Dokumen ini adalah satu-satunya jaring pengaman data sampai project upgrade tier atau pindah infrastruktur.

---

## 1. Kenapa Ini Kritis

Tanpa backup manual, kalau terjadi:
- Salah jalan migration yang menghapus/mengubah data secara destruktif
- Human error (`DELETE`/`UPDATE` tanpa `WHERE` yang benar)
- Bug di kode yang merusak data (misal race condition di scoring engine)

...maka **data hilang permanen**, tidak ada cara recovery dari sisi Supabase di free tier.

---

## 2. Strategi Backup

### 2.1 Kapan backup wajib dilakukan
- **Sebelum** menjalankan migration baru di production
- **Sebelum** deployment besar (rilis fitur besar, perubahan skema)
- **Terjadwal rutin** — minimal 1x sehari selama masa aktif development intensif (H1–H15), idealnya via automasi (lihat 2.3)
- **Setelah** milestone penting (akhir sprint, sebelum demo ke stakeholder)

### 2.2 Cara backup manual (pg_dump)

Supabase menyediakan connection string Postgres standar, jadi `pg_dump` bisa dipakai langsung dari `.env`:

```powershell
# Ganti host/port/user/database sesuai .env kamu (DB_HOST, DB_PORT, dst)
# Password akan diminta interaktif, atau set PGPASSWORD sementara di environment

pg_dump `
  --host=aws-0-ap-northeast-1.pooler.supabase.com `
  --port=6543 `
  --username=postgres.lpmrsnzljhysgteiduia `
  --dbname=postgres `
  --format=custom `
  --file="backup_anaktumbuh_$(Get-Date -Format 'yyyyMMdd_HHmmss').dump"
```

Catatan penting:
- `--format=custom` menghasilkan file terkompresi yang bisa direstore parsial (per tabel) — lebih fleksibel dari plain SQL.
- **Jangan commit file `.dump` ke git** — ukurannya besar dan berisi seluruh data siswa (data sensitif). Simpan di tempat terpisah (lihat 2.4).
- Kalau `pg_dump` versi lokal tidak cocok dengan versi Postgres Supabase, akan muncul warning versi — biasanya tetap bisa jalan, tapi idealnya pakai `pg_dump` dengan versi yang sama/lebih baru dari server.

### 2.3 Automasi (disarankan, bukan wajib untuk H15)

Kalau ada waktu, buat scheduled task Windows (`Task Scheduler`) atau cron (kalau CI/CD server pakai Linux) yang menjalankan `pg_dump` harian otomatis. Untuk sekarang, dokumentasikan sebagai **action item P1** — cukup manual dulu sampai ada waktu setup automasi.

### 2.4 Penyimpanan hasil backup

**Jangan simpan backup di disk lokal developer saja** — kalau laptop rusak, backup ikut hilang. Opsi:
- Upload manual ke Google Drive/OneDrive tim (folder private, akses terbatas)
- Upload ke bucket Supabase Storage terpisah (bucket privat khusus `db-backups`, BUKAN bucket yang sama dengan `anaktumbuh-exports` yang dipakai fitur export laporan)
- Kalau nanti upgrade ke tier berbayar, biarkan Supabase yang handle otomatis dan ini jadi backup sekunder saja

**Retention:** simpan minimal 7 backup terakhir, hapus yang lebih lama untuk hemat ruang (kecuali backup milestone penting — simpan permanen).

---

## 3. Cara Restore

### 3.1 Restore dari file `.dump` (format custom)

⚠️ **PERINGATAN:** Restore bisa menimpa/menghapus data yang sedang ada. Jangan pernah restore langsung ke database production tanpa:
1. Backup dulu kondisi saat ini (biar ada rollback kalau restore ternyata salah)
2. Konfirmasi ke tim dulu (terutama kalau production sedang dipakai orang)
3. Kalau memungkinkan, test restore ke database lain dulu (Supabase project terpisah/lokal) sebelum eksekusi ke production

```powershell
pg_restore `
  --host=aws-0-ap-northeast-1.pooler.supabase.com `
  --port=6543 `
  --username=postgres.lpmrsnzljhysgteiduia `
  --dbname=postgres `
  --clean `
  --if-exists `
  backup_anaktumbuh_20260819_090000.dump
```

- `--clean --if-exists` akan drop objek lama sebelum restore — pastikan ini memang yang diinginkan (restore penuh), bukan restore parsial.
- Untuk restore parsial (misal cuma 1 tabel yang rusak), gunakan `pg_restore --table=nama_tabel ...` — lebih aman karena tidak menyentuh tabel lain.

### 3.2 Restore via Supabase Dashboard (cek cepat, bukan pengganti pg_restore)

Supabase Dashboard punya SQL Editor yang bisa dipakai untuk cek kondisi data cepat atau jalankan query perbaikan manual kecil — tapi **tidak menggantikan** prosedur restore penuh di atas untuk kasus data hilang besar.

---

## 4. Checklist Sebelum Migration/Deployment Besar

- [ ] Jalankan `pg_dump` manual, simpan dengan nama jelas (`backup_sebelum_<nama-migration>_<tanggal>.dump`)
- [ ] Upload ke penyimpanan terpisah (bukan cuma disk lokal)
- [ ] Catat di sini/`TEAM_LOG.md` kapan backup terakhir dibuat
- [ ] Setelah migration sukses dan sudah diverifikasi, backup lama bisa tetap disimpan sebagai arsip (retention 7 hari minimal)

---

## 4b. Verifikasi Prosedur

✅ **Prosedur backup di atas sudah dites dan berhasil** (19 Agustus 2026):
- Command `pg_dump --format=custom` dijalankan langsung ke database Supabase project ini
- Hasil: file `.dump` sebesar ±441 KB berhasil dibuat, tidak kosong/gagal
- File backup hasil tes **tidak disimpan di git** (ditambahkan ke `.gitignore` pola `*.dump`) — sesuai prinsip di bagian 2.3, backup nyata disimpan di lokasi terpisah dari repo, bukan di folder project

Prosedur restore (`pg_restore`) di bagian 3 **belum dites** — masih berdasar dokumentasi resmi Postgres, bukan hasil percobaan langsung. Disarankan sebagai action item lanjutan (lihat Action Items No. 4) sebelum benar-benar dibutuhkan saat darurat.

---

## 5. Action Items Terbuka (untuk tim)

1. **P1** — Setup automasi backup harian (Task Scheduler/cron) — saat ini masih manual.
2. **P2** — Evaluasi upgrade Supabase ke tier Pro setelah production berjalan, untuk dapat backup otomatis + Point-in-Time Recovery dari Supabase langsung (jaring pengaman kedua).
3. **P1** — Tentukan lokasi penyimpanan backup resmi tim (Drive bersama/bucket terpisah) — saat ini belum ada kesepakatan formal.
4. **P2** — Test 1x proses restore penuh ke environment terpisah (bukan production) untuk memvalidasi prosedur ini benar-benar jalan sebelum benar-benar dibutuhkan saat darurat.

---

*Bagian dari OPS-001 — Data/Storage Ops (Anggota B, H15)*
