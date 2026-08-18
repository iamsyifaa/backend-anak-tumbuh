<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherRombelAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['teacher_id', 'rombel_id', 'status', 'assigned_at', 'ended_at'];

    protected $casts = [
        'status' => 'string',
        'assigned_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function rombel()
    {
        return $this->belongsTo(Rombel::class);
    }
}
