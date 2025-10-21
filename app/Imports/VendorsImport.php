<?php

namespace App\Imports;

use App\Models\Vendor;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class VendorsImport implements ToModel, WithHeadingRow, WithValidation
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Sesuaikan key ini agar cocok dengan header di file Excel
        return new Vendor([
            'vendor_no'    => $row['nomor_vendor'], // Ditambahkan
            'vendor_name'  => $row['nama_vendor'],
            'contact_name' => $row['nama_kontak'],
            'contact_no'   => $row['nomor_kontak'],
            'email'        => $row['email'],
            'wilayah'      => $row['wilayah'],
            'tahun'        => $row['tahun'],
        ]);
    }

    public function rules(): array
    {
        // Sesuaikan juga key validasi ini
        return [
            '*.nomor_vendor' => 'required|string|unique:vendors,vendor_no', // Ditambahkan
            '*.nama_vendor'  => 'required|string|max:255',
            '*.nama_kontak'  => 'required|string|max:255',
            '*.nomor_kontak' => 'required|string|max:20',
            '*.email'        => 'required|email|unique:vendors,email',
            '*.wilayah'      => 'required|string',
            '*.tahun'        => 'required|integer',
        ];
    }
}
