<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class AhsImportTemplateExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // Mengembalikan collection kosong, kita hanya butuh heading.
        return collect([]);
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        // Menyesuaikan heading agar cocok dengan importir
        return [
            'group_key',
            'ahs_deskripsi',
            'ahs_satuan',
            'ahs_provinsi', // Diubah dari ahs_wilayah
            'ahs_kab',      // Ditambahkan
            'ahs_tahun',
            'item_no',
            'item_volume',
        ];
    }

    /**
     * Fungsi baru untuk memberi style pada heading
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        // Beri style pada baris pertama (baris heading)
        $sheet->getStyle('1:1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'], // Warna font putih
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF4F81BD'], // Warna background biru tua
            ]
        ]);

        return []; // Kembalikan array kosong
    }
}