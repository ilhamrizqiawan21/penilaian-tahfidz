<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class GroupMembership extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['group_id', 'student_id', 'active_student_id', 'joined_at', 'left_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'left_at' => 'datetime'];
    }
}
