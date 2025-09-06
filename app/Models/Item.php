<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $table = 'items';
    protected $primaryKey = 'item_id'; // sesuaikan dengan migrasi kamu

    protected $fillable = [
        'ahs',
        'deskripsi',
        'merek',
        'satuan',
        'hpp',
        'vendor_id',
        'wilayah',
        'tahun',
        'produk_foto',
        'produk_deskripsi',
        'produk_dokumen',
        'produk_hitungan',
        'spesifikasi',
    ];

    protected $casts = [
        'hpp' => 'decimal:2',
        'tahun' => 'integer',
        'produk_foto' => 'array',
        'produk_dokumen' => 'array',
        'produk_hitungan' => 'array',
    ];

    /**
     * Relasi ke Vendor
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'vendor_id');
    }
}
