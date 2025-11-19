<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ahs extends Model
{
    use HasFactory;

    protected $table = 'ahs';
    protected $primaryKey = 'ahs_id';

    /**
     * Kolom yang bisa diisi (mass assignment).
     */
    protected $fillable = [
        'ahs',
        'deskripsi',
        'satuan',
        'provinsi', // Ditambahkan
        'kab', // Menggantikan 'wilayah'
        'tahun',
        'harga_pokok_total',
    ];

    /**
     * Cast otomatis untuk angka
     */
    protected $casts = [
        'tahun' => 'integer',
        'harga_pokok_total' => 'decimal:2',
    ];

    /**
     * Relasi ke AhsItem (detail)
     */
    public function items()
    {
        return $this->hasMany(AhsItem::class, 'ahs_id', 'ahs_id');
    }
}
