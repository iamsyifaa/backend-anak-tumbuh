<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Base controller aplikasi.
 *
 * WAJIB menggunakan trait AuthorizesRequests — seluruh controller yang
 * memanggil $this->authorize(...) (seperti SchoolController, AcademicYearController,
 * HabitConfigController, dll.) bergantung pada trait ini.
 *
 * JANGAN HAPUS trait AuthorizesRequests atau ValidatesRequests agar tidak
 * menyebabkan error "Call to undefined method" pada seluruh endpoint authorization.
 */
abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;
}
