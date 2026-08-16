<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_submission_id')->constrained('activity_submissions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable(); // FK ditambah terpisah di bawah
            $table->text('body');
            $table->timestamps();
        });

        // Self-referencing FK dipisah jadi ALTER TABLE tersendiri —
        // menghindari masalah urutan constraint saat tabel merujuk dirinya
        // sendiri dalam satu statement CREATE TABLE.
        Schema::table('activity_comments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('activity_comments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });
        Schema::dropIfExists('activity_comments');
    }
};