# SEC-012 — Security Checklist & Production Hardening

**Dependency:** TEST-001 (selesai, lihat `SECURITY_FINDINGS_TEST-001.md`)
**Prinsip:** *"Jangan mematikan security untuk mempermudah demo."*

## 1. Secrets & Environment

| Item | Status | Catatan |
|---|---|---|
| Tidak ada secret di-commit ke repo | ✅ Sejauh yang saya buat | Semua file saya tidak pernah hardcode API key/password/token. `.env` tidak pernah saya sentuh/buat isinya. |
| `.env.example` terdokumentasi | ⚠️ **ACTION REQUIRED** | Saya tidak melihat file `.env` project ini — siapa pun yang pegang deployment (kemungkinan `OPS-003`, Anggota D) WAJIB pastikan `APP_DEBUG=false`, `APP_ENV=production` di production, dan `.env` **tidak pernah** ter-commit (cek `.gitignore` mengandung `.env`). |
| `APP_KEY` unik per environment | ⚠️ Perlu diverifikasi manual | `php artisan key:generate` harus dijalankan terpisah untuk production, jangan reuse dari local/staging. |

## 2. Authentication & Session

| Item | Status | Catatan |
|---|---|---|
| Password di-hash, tidak plaintext | ✅ | `Hash::make()` sejak AUTH-001, diverifikasi test. |
| Rate limiting endpoint auth | ✅ **DIPERBAIKI DI SEC-012** | `/login` (5/menit), `/forgot-password` (3/menit), `/reset-password` (5/menit), `/account/change-password` (5/menit), `/users/{id}/force-reset-password` (10/menit) — **sebelumnya TIDAK ADA sama sekali**, celah brute-force nyata yang baru ditutup di task ini. |
| Token revocation bekerja | ✅ | Diuji `LoginTest::test_user_can_logout_and_token_is_revoked`. |
| Anti user-enumeration | ✅ | Pesan error generik di login & forgot-password (AUTH-001, AUTH-003). |
| Session/CSRF config | ⚠️ **PERLU REVIEW MANUAL** | Saya tidak punya akses ke `config/session.php`/`config/cors.php` project ini. API ini murni token-based (Sanctum Bearer token, bukan cookie session untuk mobile/SPA cross-domain), jadi CSRF middleware **seharusnya** tidak relevan untuk route `api/*` — tapi WAJIB dicek langsung: pastikan `SESSION_SECURE_COOKIE=true` & `SESSION_SAME_SITE=strict` di production kalau ada bagian yang tetap pakai cookie (mis. kalau nanti ada web dashboard terpisah pakai Sanctum SPA authentication). |

## 3. CORS

| Item | Status | Catatan |
|---|---|---|
| Origin whitelist eksplisit (bukan `*`) | ⚠️ **PERLU REVIEW MANUAL** | Saya tidak melihat `config/cors.php` project ini. **WAJIB**: `allowed_origins` diisi domain frontend production yang eksplisit, JANGAN `['*']` — terutama karena endpoint ini pakai Bearer token (kalau ada endpoint yang butuh cookie/credentials, `allowed_origins: ['*']` + `supports_credentials: true` adalah kombinasi berbahaya). |

## 4. Mass Assignment Protection

| Item | Status | Catatan |
|---|---|---|
| Semua model pakai `$fillable` eksplisit | ✅ | Diverifikasi — setiap model yang saya buat (User, School, AcademicYear, HabitConfig, PointConfig, ActivitySubmission, Badge, Award, Rombel, dst) pakai `$fillable`, TIDAK ADA yang pakai `$guarded = []` (yang membuka semua kolom). |
| Field sensitif tidak bisa di-overpost | ✅ | Diuji eksplisit: `CrossScopeRegressionMatrixTest::test_role_cannot_be_spoofed_via_request_payload` & `test_school_id_cannot_be_spoofed_via_request_payload` (TEST-001) — role dan school_id di request body diabaikan, scope selalu dari server-side data. |

## 5. SQL Injection Protection

| Item | Status | Catatan |
|---|---|---|
| Semua query lewat Eloquent/Query Builder | ✅ | Saya tidak pernah menulis raw SQL dengan interpolasi string user input di kode manapun yang saya buat. Semua `where()`, `whereHas()`, dst pakai parameter binding otomatis dari Eloquent. |
| File `supabase_rls_policies.sql` | ✅ | Ini SQL statis (DDL), bukan menerima input user — aman dari injection by design. |

## 6. Row Level Security (RLS)

| Item | Status | Catatan |
|---|---|---|
| RLS policy dibuat untuk tabel kritis | ✅ Draft dibuat | Lihat `database/sql/supabase_rls_policies.sql` — mencakup `schools`, `academic_years`, `habit_configs`, `point_configs`, `activity_submissions`, `certificates`, `audit_logs`. |
| **RLS aktif secara fungsional** | ❌ **BELUM, lihat batasan di bawah** | — |

### ⚠️ RLS — Batasan Implementasi Saat Ini (WAJIB DIBACA)

RLS Postgres butuh koneksi database membawa identitas user (lewat session variable atau JWT claim) supaya predicate `current_setting('app.current_user_...')` di policy SQL bisa dievaluasi. **Laravel tidak otomatis melakukan ini** — koneksi Eloquent ke Postgres pakai satu kredensial database yang sama untuk semua request (connection pooling), BUKAN per-user.

Supaya RLS di atas benar-benar aktif dan tidak diam-diam mem-block SEMUA query (karena `current_setting` akan NULL/kosong kalau tidak pernah di-set), dibutuhkan middleware tambahan yang menjalankan `SET app.current_user_id`, dst di awal setiap request — **ini BELUM saya implementasikan**, karena scope SEC-012 dependency-nya `TEST-001` bukan task database infrastructure penuh, dan saya tidak punya visibilitas ke koneksi database aktual project ini (apakah benar pakai Supabase Postgres langsung, atau Laravel connect ke Postgres biasa).

**Rekomendasi konkret untuk tim:**
1. Kalau RLS mau benar-benar aktif: buat middleware `SetDatabaseSessionContext` yang jalan SEBELUM query apa pun, isi dari `$request->user()`.
2. Kalau RLS tidak akan diaktifkan penuh (karena kompleksitas di atas), maka **Laravel Policy TETAP jadi satu-satunya lapis otorisasi** — file SQL ini tetap berguna sebagai dokumentasi "kalau nanti mau tambah RLS, ini titik awalnya", tapi **JANGAN di-`ALTER TABLE ... ENABLE ROW LEVEL SECURITY`** di production tanpa middleware session-context di atas, karena akan membuat SEMUA query gagal (predicate selalu `false`/`NULL`).

## 7. Audit Trail

| Item | Status | Catatan |
|---|---|---|
| Audit untuk perubahan sensitif | ⚠️ Parsial | `AuditLogService` (SEC-005) sudah dipakai untuk `point_config.*`. **BELUM** dipakai untuk: `habit_config.*` (AUTH-004), perubahan `school`/`academic_year` (ORG-001), assignment wali kelas (SEC-009), admin force-reset-password (AUTH-003 — ini seharusnya diaudit juga, tapi belum). |
| Rekomendasi | — | Karena `AuditLogService` sudah reusable (SEC-005), tinggal tambah 1 baris `$this->auditLog->record(...)` di controller-controller yang disebut di atas. Saya TIDAK menambahkannya sekarang di SEC-012 supaya tidak mengubah behavior/test yang sudah pass tanpa diskusi tim dulu — ini saya tandai sebagai **rekomendasi**, bukan silently diubah (sesuai instruksi "jangan mengubah requirement diam-diam, tandai sebagai change request"). |

## 8. Storage / File Access (terkait `OPS-001`, Anggota B)

| Item | Status | Catatan |
|---|---|---|
| File report/certificate tidak di disk public | ✅ | `ReportExportController` (SEC-011) pakai disk `local`, bukan `public`. |
| Signed URL dengan expiry | ✅ | 5 menit, `URL::temporarySignedRoute`. |

## Ringkasan Acceptance Criteria SEC-012

| Kriteria | Status |
|---|---|
| No secrets in repo | ✅ (sejauh kode yang saya buat) |
| Protected endpoints tested | ✅ (TEST-001 — 100+ test case) |
| RLS active where designed | ⚠️ Draft dibuat, **belum fungsional** — lihat batasan di atas |
| Production config documented | ⚠️ Sebagian — CORS/session config perlu direview manual oleh yang punya akses `config/` project |

## Rekomendasi Prioritas untuk Sebelum Deploy

1. **P0** — Review `config/cors.php` & `.env` production (saya tidak punya akses).
2. **P0** — Putuskan: aktifkan RLS penuh (butuh middleware session-context) atau tetap murni Laravel Policy.
3. **P1** — Tambah audit logging ke `habit_config`, `school`, `academic_year`, `force-reset-password` (pola sudah ada, tinggal reuse).
4. **P1** — Isi `QrCredentialRevokeSecurityTest` setelah fitur QR (`MASTER-003`/`BE-004`) selesai.
5. **P2** — Verifikasi `APP_DEBUG=false` di production (mencegah stack trace bocor ke response).
