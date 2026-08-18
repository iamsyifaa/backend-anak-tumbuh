<?php

/**
 * Permission matrix — mirror 1:1 dari 01_Role_and_Permission_v2_0 (Bagian 3).
 * Satu sumber kebenaran; Gate didefinisikan otomatis dari sini di AuthServiceProvider.
 *
 * PENTING: activity.manual_import, activity.manual_bulk_input, activity.manual_copy_previous
 * SENGAJA TIDAK ADA di sini sama sekali — bukan cuma false untuk semua role, tapi memang
 * tidak diimplementasikan (tidak ada Gate, tidak ada endpoint). Lihat README aturan penting.
 */
return [

    'super_admin' => [
        'school.view', 'school.manage',
        'academic_year.manage',
        'class_group.manage',
        'teacher.manage',
        'student.view', 'student.manage', 'student.import',
        'student.enrollment.manage', 'student.account.generate', 'student.qr.revoke',
        'habit.view', 'habit.manage',
        'point_config.manage',
        'activity.view',
        'dashboard.view',
        'report.view', 'report.export',
        'award.manage', 'certificate.generate', 'ranking.manage',
        'audit.view',
    ],

    'kepala_sekolah' => [
        'school.view', 'school.manage',
        'academic_year.manage',
        'class_group.manage',
        'teacher.manage',
        'student.view', 'student.manage', 'student.import',
        'student.enrollment.manage', 'student.account.generate', 'student.qr.revoke',
        'habit.view', 'habit.manage',
        'point_config.manage',
        'activity.view',
        'dashboard.view',
        'report.view', 'report.export',
        'award.manage', 'certificate.generate', 'ranking.manage',
        // TIDAK ADA audit.view — hanya Super Admin.
    ],

    'wali_kelas' => [
        // TIDAK ADA school.*, academic_year.*, class_group.manage, teacher.manage.
        'student.view', // scope: siswa di rombelnya saja (dicek di Policy, bukan di sini)
        'habit.view',
        'activity.view', // scope: rombelnya saja
        'comment.create', 'comment.reply',
        'dashboard.view',
        'report.view', 'report.export',
    ],

    'siswa' => [
        'student.view', // scope: diri sendiri
        'habit.view',
        'activity.view', // scope: aktivitas diri sendiri
        'activity.submit.digital', // scope: diri sendiri
        'comment.create', 'comment.reply', // scope: aktivitas terkait diri sendiri
        'dashboard.view',
        'report.view', // scope: diri sendiri
        // TIDAK ADA report.export untuk siswa.
    ],

];
