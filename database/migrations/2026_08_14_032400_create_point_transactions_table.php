<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->integer('amount'); // bisa positif (dapat poin) atau negatif (koreksi/penalti)

            // Traceability wajib: transaksi harus bisa ditelusuri ke sumber penghasilnya.
            // Contoh: source_type = 'submission_answer', source_id = id baris submission_answers.
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');

            $table->date('period_date'); // tanggal periode harian saat poin didapat

            $table->timestamp('created_at')->useCurrent();
            // TIDAK ada updated_at secara sengaja — tabel ini insert-only,
            // sesuai aturan "perubahan konfigurasi tidak mengubah histori transaksi".

            $table->index(['user_id', 'period_date']); // percepat SUM() per siswa untuk ranking real-time
            $table->index(['source_type', 'source_id']); // percepat penelusuran balik ke sumber
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};