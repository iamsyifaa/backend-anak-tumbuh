<?php

namespace Tests\Feature\Organization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mirror langsung dari Bagian 3 Permission Matrix (01_Role_and_Permission_v2_0)
     * agar kalau matrix berubah di dokumen, test ini WAJIB diupdate juga — jadi
     * config/permissions.php tidak bisa diam-diam menyimpang dari dokumen sumber.
     */
    public static function matrixProvider(): array
    {
        return [
            ['school.view', ['super_admin', 'kepala_sekolah']],
            ['school.manage', ['super_admin', 'kepala_sekolah']],
            ['academic_year.manage', ['super_admin', 'kepala_sekolah']],
            ['class_group.manage', ['super_admin', 'kepala_sekolah']],
            ['teacher.manage', ['super_admin', 'kepala_sekolah']],
            ['student.view', ['super_admin', 'kepala_sekolah', 'wali_kelas', 'siswa']],
            ['student.manage', ['super_admin', 'kepala_sekolah']],
            ['student.import', ['super_admin', 'kepala_sekolah']],
            ['student.enrollment.manage', ['super_admin', 'kepala_sekolah']],
            ['student.account.generate', ['super_admin', 'kepala_sekolah']],
            ['student.qr.revoke', ['super_admin', 'kepala_sekolah']],
            ['habit.view', ['super_admin', 'kepala_sekolah', 'wali_kelas', 'siswa']],
            ['habit.manage', ['super_admin', 'kepala_sekolah']],
            ['point_config.manage', ['super_admin', 'kepala_sekolah']],
            ['activity.view', ['super_admin', 'kepala_sekolah', 'wali_kelas', 'siswa']],
            ['activity.submit.digital', ['siswa']],
            ['comment.create', ['wali_kelas', 'siswa']],
            ['comment.reply', ['wali_kelas', 'siswa']],
            ['dashboard.view', ['super_admin', 'kepala_sekolah', 'wali_kelas', 'siswa']],
            ['report.view', ['super_admin', 'kepala_sekolah', 'wali_kelas', 'siswa']],
            ['report.export', ['super_admin', 'kepala_sekolah', 'wali_kelas']],
            ['award.manage', ['super_admin', 'kepala_sekolah']],
            ['certificate.generate', ['super_admin', 'kepala_sekolah']],
            ['ranking.manage', ['super_admin', 'kepala_sekolah']],
            ['audit.view', ['super_admin']],
        ];
    }

    #[Test]
    #[DataProvider('matrixProvider')]
    public function test_permission_matrix_matches_document(string $permission, array $allowedRoles): void
    {
        foreach (['super_admin', 'kepala_sekolah', 'wali_kelas', 'siswa'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->assertSame(
                in_array($role, $allowedRoles, true),
                Gate::forUser($user)->allows($permission),
                "Role '{$role}' expected ".(in_array($role, $allowedRoles, true) ? 'ALLOWED' : 'DENIED')." for '{$permission}'"
            );
        }
    }

    #[Test]
    public function test_manual_book_input_permissions_do_not_exist_at_all(): void
    {
        // activity.manual_import / manual_bulk_input / manual_copy_previous secara EKSPLISIT
        // tidak diimplementasikan — bukan cuma "false untuk semua role", tapi Gate-nya
        // sendiri tidak pernah didefinisikan (memverifikasi aturan README/Requirement).
        $admin = User::factory()->create(['role' => 'super_admin']);

        foreach (['activity.manual_import', 'activity.manual_bulk_input', 'activity.manual_copy_previous'] as $permission) {
            $this->assertFalse(
                Gate::forUser($admin)->has($permission),
                "Permission '{$permission}' should not be defined at all, even Super Admin."
            );
        }
    }

    #[Test]
    public function test_wali_kelas_has_no_org_management_permissions(): void
    {
        $wali = User::factory()->create(['role' => 'wali_kelas']);

        foreach (['school.manage', 'academic_year.manage', 'class_group.manage', 'teacher.manage'] as $permission) {
            $this->assertFalse(Gate::forUser($wali)->allows($permission));
        }
    }

    #[Test]
    public function test_siswa_cannot_export_report(): void
    {
        $siswa = User::factory()->create(['role' => 'siswa']);

        $this->assertFalse(Gate::forUser($siswa)->allows('report.export'));
        $this->assertTrue(Gate::forUser($siswa)->allows('report.view'));
    }
}
