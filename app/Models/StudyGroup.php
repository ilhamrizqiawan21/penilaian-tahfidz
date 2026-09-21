<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class StudyGroup extends Model
{
    use HasUlids;

    protected $fillable = ['owner_id', 'name', 'archived_at'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }
}
