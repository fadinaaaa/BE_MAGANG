<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Vendor; // --- PERUBAHAN: Import model Vendor ---
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ItemsExport;
use App\Imports\ItemsImport;
use Maatwebsite\Excel\Validators\ValidationException;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ItemController extends Controller
{
    /**
     * Ambil semua data Item dengan filter.
     */
    public function index(Request $request)
    {
        // ... (Tidak ada perubahan di sini)

        $request->validate([
            'provinsi' => 'nullable|string|max:255',
            'kab'      => 'nullable|string|max:255',
            'tahun'    => 'nullable|integer',
            'item_no'  => 'nullable|string',
        ]);

        $query = Item::with('vendor');

        $query->when($request->filled('provinsi'), function ($q) use ($request) {
            return $q->where('provinsi', $request->provinsi);
        });

        $query->when($request->filled('kab'), function ($q) use ($request) {
            return $q->where('kab', $request->kab);
        });

        $query->when($request->filled('tahun'), function ($q) use ($request) {
            return $q->where('tahun', $request->tahun);
        });

        $query->when($request->filled('item_no'), function ($q) use ($request) {
            return $q->where('item_no', 'like', '%' . $request->item_no . '%');
        });

        $items = $query->get();

        return response()->json([
            'success' => true,
            'data'    => $items,
        ]);
    }

    /**
     * Simpan Item baru.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'ahs'              => 'required|string',
                'deskripsi'        => 'required|string',
                'merek'            => 'nullable|string',
                'satuan'           => 'required|string',
                'hpp'              => 'required|numeric',
                // --- PERUBAHAN: Validasi 'vendor_no' alih-alih 'vendor_id' ---
                'vendor_no'        => 'nullable|string|exists:vendors,vendor_no',
                // --- AKHIR PERUBAHAN ---
                'provinsi'         => 'nullable|string',
                'kab'              => 'nullable|string',
                'tahun'            => 'required|integer',
                'produk_foto'      => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'produk_deskripsi' => 'nullable|string',
                'produk_dokumen'   => 'nullable|file|mimes:pdf,doc,docx|max:5120',
                'spesifikasi'      => 'nullable|string',
            ]);

            // Pindahkan logika upload file ke method terpisah
            $validated['produk_foto']    = $this->uploadFile($request, 'produk_foto', 'uploads/foto');
            $validated['produk_dokumen'] = $this->uploadFile($request, 'produk_dokumen', 'uploads/dokumen');

            // --- PERUBAHAN: Konversi vendor_no ke vendor_id ---
            $vendorId = null;
            if ($request->filled('vendor_no')) {
                // Cari vendor_id berdasarkan vendor_no yang unik
                $vendor = Vendor::where('vendor_no', $validated['vendor_no'])->first();
                if ($vendor) {
                    $vendorId = $vendor->vendor_id;
                }
            }

            // Siapkan data untuk disimpan
            $dataToCreate = $validated;
            $dataToCreate['vendor_id'] = $vendorId; // Tambahkan vendor_id yang sudah ditemukan
            unset($dataToCreate['vendor_no']);      // Hapus vendor_no dari data yang akan disimpan
            // --- AKHIR PERUBAHAN ---

            // Generate Item No baru secara otomatis
            $dataToCreate['item_no'] = $this->generateNewItemNo();

            // Baris kritis yang akan kita pantau
            $item = Item::create($dataToCreate); // Gunakan data yang sudah disiapkan

            Log::info('Item baru berhasil ditambahkan dengan ID: ' . $item->item_id);

            return response()->json([
                'success' => true,
                'message' => 'Item berhasil ditambahkan',
                'data'    => $item,
            ], 201);
        } catch (ValidationException $e) {
            // ... (Tidak ada perubahan di sini)
            Log::error('Eror validasi saat menyimpan item: ', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Data yang diberikan tidak valid.',
                'errors'  => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            // ... (Tidak ada perubahan di sini)
            Log::error('GAGAL MENYIMPAN ITEM: ' . $e->getMessage());
            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat mencoba menyimpan data.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tampilkan detail Item berdasarkan ID atau item_no.
     */
    public function show($id)
    {
        // ... (Tidak ada perubahan di sini)
        $item = $this->findItem($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $item,
        ]);
    }

    /**
     * Update Item.
     * Menggunakan POST karena form-data (untuk file upload) tidak sepenuhnya didukung oleh PUT/PATCH.
     */
    public function update(Request $request, $id)
    {
        $item = $this->findItem($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item tidak ditemukan',
            ], 404);
        }

        // 'sometimes' berarti validasi hanya jika field ada di request
        $validated = $request->validate([
            'item_no'          => ['sometimes', 'string', Rule::unique('items')->ignore($item->item_id, 'item_id')],
            'ahs'              => 'sometimes|string',
            'deskripsi'        => 'sometimes|string',
            'merek'            => 'nullable|string',
            'satuan'           => 'sometimes|string',
            'hpp'              => 'sometimes|numeric',
            // --- PERUBAHAN: Validasi 'vendor_no' alih-alih 'vendor_id' ---
            'vendor_no'        => 'nullable|string|exists:vendors,vendor_no',
            // --- AKHIR PERUBAHAN ---
            'provinsi'         => 'nullable|string',
            'kab'              => 'nullable|string',
            'tahun'            => 'sometimes|integer',
            'produk_foto'      => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'produk_deskripsi' => 'nullable|string',
            'produk_dokumen'   => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'spesifikasi'      => 'nullable|string',
        ]);

        // --- PERUBAHAN: Siapkan data update, konversi vendor_no ke vendor_id ---
        $dataToUpdate = $validated;

        // Cek jika 'vendor_no' ada dalam request
        if ($request->has('vendor_no')) {
            $vendorId = null;
            if ($request->filled('vendor_no')) {
                // Cari vendor_id berdasarkan vendor_no
                $vendor = Vendor::where('vendor_no', $validated['vendor_no'])->first();
                if ($vendor) {
                    $vendorId = $vendor->vendor_id;
                }
            }
            $dataToUpdate['vendor_id'] = $vendorId; // Set vendor_id
        }
        unset($dataToUpdate['vendor_no']); // Hapus vendor_no dari data update
        // --- AKHIR PERUBAHAN ---


        // Cek dan proses upload file baru
        if ($request->hasFile('produk_foto')) {
            if ($item->produk_foto) {
                Storage::disk('public')->delete($item->produk_foto);
            }
            // --- PERUBAHAN: Simpan path ke $dataToUpdate ---
            $dataToUpdate['produk_foto'] = $this->uploadFile($request, 'produk_foto', 'uploads/foto');
        }

        if ($request->hasFile('produk_dokumen')) {
            if ($item->produk_dokumen) {
                Storage::disk('public')->delete($item->produk_dokumen);
            }
            // --- PERUBAHAN: Simpan path ke $dataToUpdate ---
            $dataToUpdate['produk_dokumen'] = $this->uploadFile($request, 'produk_dokumen', 'uploads/dokumen');
        }

        $item->update($dataToUpdate); // Gunakan $dataToUpdate
        Log::info('Item ID ' . $id . ' berhasil diupdate');

        return response()->json([
            'success' => true,
            'message' => 'Item berhasil diupdate',
            'data'    => $item,
        ]);
    }

    /**
     * Hapus Item.
     */
    public function destroy($id)
    {
        // ... (Tidak ada perubahan di sini)
        $item = $this->findItem($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item tidak ditemukan',
            ], 404);
        }

        if ($item->produk_foto) {
            Storage::disk('public')->delete($item->produk_foto);
        }
        if ($item->produk_dokumen) {
            Storage::disk('public')->delete($item->produk_dokumen);
        }

        $item->delete();
        Log::info('Item dengan ID ' . $id . ' berhasil dihapus');

        return response()->json([
            'success' => true,
            'message' => 'Item berhasil dihapus',
        ]);
    }

    /**
     * Ekspor semua data Item ke Excel.
     */
    public function export()
    {
        // ... (Tidak ada perubahan di sini, pastikan ItemsExport Anda mengekspor $item->vendor->vendor_no)
        Log::info('Memulai proses ekspor semua item.');
        return Excel::download(new ItemsExport, 'item.xlsx');
    }

    /**
     * Impor data Item dari Excel.
     */
    public function import(Request $request)
    {
        // 1. Validasi File
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv']);

        DB::beginTransaction();

        try {
            // 2. Baca file Excel ke dalam Collection
            $collection = Excel::toCollection(new ItemsImport, $request->file('file'));
            $rows = $collection[0]; // Ambil sheet pertama

            // -------------------------------------------------------
            // OPTIMALISASI 1: MAPPING VENDOR (Cepat & Efisien)
            // -------------------------------------------------------
            // Kumpulkan semua vendor_no unik dari file excel
            $vendorNos = $rows->pluck('vendor_no')->filter()->unique()->toArray();

            // Ambil data vendor dari database berdasarkan no tersebut
            $vendors = Vendor::whereIn('vendor_no', $vendorNos)->get();

            // Buat Peta: 'V-001' => 15 (id)
            // Agar nanti kita tidak perlu query database berulang-ulang di dalam loop
            $vendorMap = $vendors->pluck('vendor_id', 'vendor_no');


            // -------------------------------------------------------
            // OPTIMALISASI 2: LOGIKA NOMOR ITEM (FIX ANTI DUPLIKAT)
            // -------------------------------------------------------
            // Gunakan DB::table (bukan Model Item) agar data yang terhapus (soft delete) tetap terbaca.
            // Ini mencegah error "Duplicate entry 'M_001'".
            $lastItem = DB::table('items')->orderBy('item_id', 'desc')->first();

            $lastNumber = 0;

            if ($lastItem && !empty($lastItem->item_no)) {
                // Ambil angka dari string "M_005" -> diambil 5.
                // Fungsi preg_replace ini hanya mengambil digit angka saja, lebih aman daripada substr.
                $angkaSaja = preg_replace('/[^0-9]/', '', $lastItem->item_no);
                $lastNumber = intval($angkaSaja);
            }

            // -------------------------------------------------------
            // LOOPING DATA & SIMPAN
            // -------------------------------------------------------
            foreach ($rows as $row) {
                // 1. Cari Vendor ID dari Peta Map
                $vendorId = $vendorMap[$row['vendor_no'] ?? ''] ?? null;

                // 2. Generate Nomor Baru (Increment variabel PHP)
                $lastNumber++; // Tambah 1
                $newItemNo = 'M_' . str_pad($lastNumber, 3, '0', STR_PAD_LEFT);
                // Hasilnya: M_006, M_007, dst...

                // 3. Parsing Foto (Jika ada koma, jadikan array)
                $fotoArray = null;
                if (!empty($row['produk_foto'])) {
                    $fotoArray = array_map('trim', explode(',', $row['produk_foto']));
                }

                // 4. Parsing Dokumen (Jika ada koma, jadikan array)
                $dokumenArray = null;
                if (!empty($row['produk_dokumen'])) {
                    $dokumenArray = array_map('trim', explode(',', $row['produk_dokumen']));
                }

                // 5. Simpan ke Database
                Item::create([
                    'item_no'          => $newItemNo,
                    'ahs'              => $row['ahs'],
                    'deskripsi'        => $row['deskripsi'],
                    'merek'            => $row['merek'] ?? null,
                    'satuan'           => $row['satuan'],
                    'hpp'              => $row['hpp'],
                    'vendor_id'        => $vendorId,
                    'provinsi'         => $row['provinsi'] ?? null,
                    'kab'              => $row['kab'] ?? null,
                    'tahun'            => $row['tahun'],
                    'spesifikasi'      => $row['spesifikasi'] ?? null,
                    'produk_deskripsi' => $row['produk_deskripsi'] ?? null,
                    'produk_foto'      => $fotoArray,    // Masuk sebagai JSON Array
                    'produk_dokumen'   => $dokumenArray, // Masuk sebagai JSON Array
                ]);
            }

            DB::commit();
            Log::info('File item berhasil diimpor.');

            return response()->json([
                'success' => true,
                'message' => 'Data item berhasil diimpor',
            ], 200);
        } catch (ValidationException $e) {
            DB::rollBack();
            // Menangani error validasi Excel
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = [
                    'row'       => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors'    => $failure->errors(),
                    'values'    => $failure->values(),
                ];
            }
            Log::error('Gagal impor item karena validasi: ', $errors);
            return response()->json([
                'success' => false,
                'message' => 'Terdapat kesalahan validasi pada file.',
                'errors'  => $errors,
            ], 422);
        } catch (Throwable $e) {
            DB::rollBack();
            // Menangani error server (SQL, Logic, dll)
            Log::error('Gagal impor item: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat impor.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        // Header sesuai dengan perubahan
        $headers = [
            'ahs',
            'deskripsi',
            'merek',
            'satuan',
            'hpp',
            // --- PERUBAHAN: Ubah header template ---
            'vendor_no',
            // --- AKHIR PERUBAHAN ---
            'provinsi',
            'kab',
            'tahun',
            'produk_deskripsi',
            'spesifikasi',
        ];

        // Nama file
        $fileName = 'template_import_items.xlsx';

        // ... (Logika pembuatan file Excel tetap sama)
        $tempFile = tempnam(sys_get_temp_dir(), 'template_items_');
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $colIndex = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($colIndex, 1, $header);
            $colIndex++;
        }

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }


    // ===================================================================
    // PRIVATE HELPER METHODS (Tidak ada perubahan di sini)
    // ===================================================================

    /**
     * Mencari item berdasarkan ID primary key atau item_no custom.
     */
    private function findItem($id)
    {
        $item = Item::with('vendor')->find($id);
        if (!$item) {
            $item = Item::with('vendor')->where('item_no', $id)->first();
        }
        return $item;
    }

    /**
     * Menghasilkan item_no baru secara berurutan.
     */
    private function generateNewItemNo()
    {
        $lastItem = Item::latest('item_id')->first();
        $newIdNumber = 1;

        if ($lastItem && isset($lastItem->item_no)) {
            $lastIdNumber = intval(substr($lastItem->item_no, 2));
            $newIdNumber = $lastIdNumber + 1;
        }

        return 'M_' . str_pad($newIdNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Mengelola upload file dan mengembalikan path-nya.
     */
    private function uploadFile(Request $request, string $fieldName, string $directory)
    {
        if ($request->hasFile($fieldName) && $request->file($fieldName)->isValid()) {
            $file = $request->file($fieldName);
            $originalFileName = $file->getClientOriginalName();
            Log::info("File '$originalFileName' diupload ke direktori '$directory'.");
            return $file->storeAs($directory, $originalFileName, 'public');
        }
        return null;
    }
}
