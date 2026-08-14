<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false; // hanya created_at, di-set manual via useCurrent() di migration.

    protected $fillable = ['user_id', 'action', 'entity_type', 'entity_id', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Baris audit tidak pernah diubah setelah dibuat — override save() untuk
     * baris yang sudah punya id supaya tidak ada yang bisa "mengedit sejarah"
     * lewat kode lain di masa depan tanpa sadar (mis. $log->update([...])).
     */
    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new \LogicException('AuditLog bersifat append-only dan tidak boleh diubah setelah dibuat.');
        }

        return parent::save($options);
    }

    public function delete()
    {
        throw new \LogicException('AuditLog bersifat append-only dan tidak boleh dihapus.');
    }
}