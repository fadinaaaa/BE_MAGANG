<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ahs', function (Blueprint $table) {
            $table->id('ahs_id'); // ID AHS
            $table->string('ahs'); // Nama AHS
            $table->string('deskripsi'); // Deskripsi
            $table->string('satuan'); // Satuan
            $table->string('provinsi'); // Diubah dari wilayah
            $table->string('kab'); // Ditambahkan
            $table->year('tahun'); // Tahun
            $table->decimal('harga_pokok_total', 15, 2); // Harga Pokok Total
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ahs');
    }
};
