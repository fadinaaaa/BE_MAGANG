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
        Schema::create('vendors', function (Blueprint $table) {
            $table->id('vendor_id'); // ID Vendor
            $table->string('vendor_name'); // Nama Vendor
            $table->string('contact_name'); // Nama Kontak
            $table->string('contact_no'); // Nomor Kontak
            $table->string('email')->unique(); // Email (unik)
            $table->string('wilayah'); // Wilayah
            $table->integer('tahun'); // Tahun
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
