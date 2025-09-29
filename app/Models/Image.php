<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'variants' => 'array',
    ];

    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }
}
