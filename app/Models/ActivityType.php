<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ActivityType extends Model
{
    use HasUlids;

    protected $fillable = ['owner_id', 'name', 'counts_toward_progress', 'archived_at'];

    protected function casts(): array
    {
        return ['counts_toward_progress' => 'boolean', 'archived_at' => 'datetime'];
    }
}
