<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClarityFetchCounter extends Model
{
    protected $table = 'clarity_fetch_counters';

    protected $fillable = [
        'project_id',
        'date',
        'fetch_count',
    ];
}
