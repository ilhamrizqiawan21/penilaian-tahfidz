<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasUlids;

    protected $fillable = ['owner_id', 'code', 'name', 'contact', 'notes', 'archived_at'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }
}
