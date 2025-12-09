<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemFile extends Model
{
    
    protected $fillable = [
        'fileable_id',
        'fileable_type',
        'file_path',
        'file_type'
    ];

    public function fileable()
    {
        return $this->morphTo();
    }
}
