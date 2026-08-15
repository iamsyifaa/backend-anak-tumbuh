-- ============================================================================
-- SEC-012 — Supabase Row Level Security (RLS) Policies
-- ============================================================================
-- ⚠️ INI REKOMENDASI/TITIK AWAL, BUKAN SUDAH DITERAPKAN OTOMATIS.
-- Saya (Anggota A) tidak punya akses ke instance Supabase project ini,
-- jadi file ini adalah SQL yang harus di-review lalu dijalankan manual oleh
-- siapa pun yang pegang akses Supabase dashboard/CLI (kemungkinan Anggota B,
-- lihat OPS-001 "Supabase storage policies").
--
-- KENAPA RLS PENTING SEBAGAI DEFENSE IN DEPTH:
-- Laravel Policy (SchoolPolicy, SubmissionPolicy, dst — sudah lengkap sejak
-- ORG-002 s/d SEC-011) adalah lapis otorisasi di APLIKASI. Kalau suatu saat
-- ada:
--   - bug di kode Laravel yang lupa panggil authorize(),
--   - akses langsung ke database (mis. dari BI tool, script maintenance,
--     atau kalau Supabase API/PostgREST diaktifkan langsung ke tabel),
-- RLS di level DATABASE tetap mencegah kebocoran data lintas sekolah/rombel/
-- siswa — dua lapis independen, bukan cuma satu titik kegagalan.
--
-- PRASYARAT: RLS mengasumsikan koneksi ke Postgres membawa identitas user
-- (lewat Supabase Auth JWT claim, atau custom session variable seperti
-- `app.current_user_id`/`app.current_user_role`). Laravel TIDAK memakai
-- Supabase Auth (pakai Sanctum sendiri) — jadi supaya RLS ini benar-benar
-- aktif, koneksi dari Laravel ke Postgres HARUS meng-set session variable
-- di setiap request (mis. lewat DB::statement("SET app.current_user_id = ?")
-- di middleware). INI BELUM DIIMPLEMENTASIKAN — lihat catatan di
-- SECURITY_CHECKLIST_SEC-012.md bagian "RLS — Batasan Implementasi Saat Ini".

-- ── schools ──────────────────────────────────────────────────────────────
ALTER TABLE schools ENABLE ROW LEVEL SECURITY;

CREATE POLICY schools_select_scope ON schools
    FOR SELECT
    USING (
        current_setting('app.current_user_role', true) = 'super_admin'
        OR id = current_setting('app.current_user_school_id', true)::bigint
    );

-- ── academic_years / habit_configs / point_configs (semua punya school_id) ──
CREATE POLICY academic_years_select_scope ON academic_years
    FOR SELECT
    USING (
        current_setting('app.current_user_role', true) = 'super_admin'
        OR school_id = current_setting('app.current_user_school_id', true)::bigint
    );
ALTER TABLE academic_years ENABLE ROW LEVEL SECURITY;

CREATE POLICY habit_configs_select_scope ON habit_configs
    FOR SELECT
    USING (
        current_setting('app.current_user_role', true) = 'super_admin'
        OR school_id = current_setting('app.current_user_school_id', true)::bigint
    );
ALTER TABLE habit_configs ENABLE ROW LEVEL SECURITY;

CREATE POLICY point_configs_select_scope ON point_configs
    FOR SELECT
    USING (
        current_setting('app.current_user_role', true) = 'super_admin'
        OR school_id = current_setting('app.current_user_school_id', true)::bigint
    );
ALTER TABLE point_configs ENABLE ROW LEVEL SECURITY;

-- ── activity_submissions / student_badges / student_awards / certificates ──
-- (semua punya student_profile_id — scope siswa hanya diri sendiri)
CREATE POLICY activity_submissions_student_scope ON activity_submissions
    FOR SELECT
    USING (
        current_setting('app.current_user_role', true) = 'super_admin'
        OR student_profile_id = current_setting('app.current_user_student_profile_id', true)::bigint
    );
ALTER TABLE activity_submissions ENABLE ROW LEVEL SECURITY;

CREATE POLICY certificates_student_scope ON certificates
    FOR SELECT
    USING (
        current_setting('app.current_user_role', true) = 'super_admin'
        OR student_profile_id = current_setting('app.current_user_student_profile_id', true)::bigint
    );
ALTER TABLE certificates ENABLE ROW LEVEL SECURITY;

-- ── audit_logs — HANYA Super Admin, dan HANYA SELECT (append-only, tidak
--    ada UPDATE/DELETE policy sama sekali — mengunci di level DB, konsisten
--    dengan AuditLog::save()/delete() yang sudah dikunci di level model). ──
CREATE POLICY audit_logs_super_admin_only ON audit_logs
    FOR SELECT
    USING (current_setting('app.current_user_role', true) = 'super_admin');
ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;
-- SENGAJA tidak ada CREATE POLICY untuk INSERT/UPDATE/DELETE — default
-- Postgres RLS: kalau tidak ada policy untuk suatu command, command itu
-- DITOLAK TOTAL kecuali lewat service_role key (dipakai backend Laravel
-- untuk INSERT saat AuditLogService->record() jalan, BUKAN oleh user biasa).

-- ============================================================================
-- CATATAN: Tabel yang scope-nya lebih kompleks (rombel-based: submission
-- Wali Kelas, report_exports 3-scope-type) TIDAK dibuatkan RLS di sini —
-- logic-nya terlalu dinamis untuk SQL predicate sederhana (butuh subquery ke
-- teacher_rombel_assignments). Kalau mau RLS penuh untuk itu juga, perlu
-- policy dengan subquery, didiskusikan terpisah karena berdampak ke performa
-- query (RLS predicate dieksekusi di SETIAP row scan).
-- ============================================================================