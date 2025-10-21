<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ItemsExport;
use App\Imports\ItemsImport;
use Maatwebsite\Excel\Validators\ValidationException;
use Throwable; // Import Throwable untuk menangkap semua jenis error

class ItemController extends Controller
{
    /**
     * Ambil semua data Item dengan filter.
     */
    public function index(Request $request)
    {
        // Validasi input filter (opsional tapi praktik yang baik)
        $request->validate([
            'wilayah' => 'nullable|string|max:255',
            'tahun'   => 'nullable|integer',
            'item_no' => 'nullable|string', // Filter berdasarkan item_no
        ]);

        $query = Item::with('vendor');

        // Menggunakan when() untuk query yang lebih bersih
        $query->when($request->filled('wilayah'), function ($q) use ($request) {
            return $q->where('wilayah', $request->wilayah);
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
                'vendor_id'        => 'nullable|integer|exists:vendors,vendor_id',
                'wilayah'          => 'nullable|string',
                'tahun'            => 'required|integer',
                'produk_foto'      => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'produk_deskripsi' => 'nullable|string',
                'produk_dokumen'   => 'nullable|file|mimes:pdf,doc,docx|max:5120',
                'spesifikasi'      => 'nullable|string',
            ]);

            // Pindahkan logika upload file ke method terpisah
            $validated['produk_foto']    = $this->uploadFile($request, 'produk_foto', 'uploads/foto');
            $validated['produk_dokumen'] = $this->uploadFile($request, 'produk_dokumen', 'uploads/dokumen');

            // Generate Item No baru secara otomatis
            $validated['item_no'] = $this->generateNewItemNo();

            // Baris kritis yang akan kita pantau
            $item = Item::create($validated);

            Log::info('Item baru berhasil ditambahkan dengan ID: ' . $item->item_id);

            return response()->json([
                'success' => true,
                'message' => 'Item berhasil ditambahkan',
                'data'    => $item,
            ], 201);
        } catch (ValidationException $e) {
            // Menangkap dan mencatat eror validasi secara spesifik
            Log::error('Eror validasi saat menyimpan item: ', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Data yang diberikan tidak valid.',
                'errors'  => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            // Menangkap SEMUA jenis eror lain (database, dll)
            Log::error('GAGAL MENYIMPAN ITEM: ' . $e->getMessage());
            // Baris ini akan mencatat detail lengkap eror ke log
            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat mencoba menyimpan data.',
                'error'   => $e->getMessage(), // Mengirim pesan eror untuk debugging
            ], 500);
        }
    }
    /**
     * Tampilkan detail Item berdasarkan ID atau item_no.
     */
    public function show($id)
    {
        // Refactor: Panggil method pencarian item
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
            // Validasi unik untuk item_no, abaikan item saat ini
            'item_no'          => ['sometimes', 'string', Rule::unique('items')->ignore($item->item_id, 'item_id')],
            'ahs'              => 'sometimes|string',
            'deskripsi'        => 'sometimes|string',
            'merek'            => 'nullable|string',
            'satuan'           => 'sometimes|string',
            'hpp'              => 'sometimes|numeric',
            'vendor_id'        => 'nullable|integer|exists:vendors,vendor_id',
            'wilayah'          => 'nullable|string',
            'tahun'            => 'sometimes|integer',
            'produk_foto'      => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'produk_deskripsi' => 'nullable|string',
            'produk_dokumen'   => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'spesifikasi'      => 'nullable|string',
        ]);

        // Cek dan proses upload file baru
        if ($request->hasFile('produk_foto')) {
            // Hapus file lama jika ada
            if ($item->produk_foto) {
                Storage::disk('public')->delete($item->produk_foto);
            }
            $validated['produk_foto'] = $this->uploadFile($request, 'produk_foto', 'uploads/foto');
        }

        if ($request->hasFile('produk_dokumen')) {
            if ($item->produk_dokumen) {
                Storage::disk('public')->delete($item->produk_dokumen);
            }
            $validated['produk_dokumen'] = $this->uploadFile($request, 'produk_dokumen', 'uploads/dokumen');
        }

        $item->update($validated);
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
        $item = $this->findItem($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item tidak ditemukan',
            ], 404);
        }

        // Hapus file dari storage sebelum menghapus record
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
        Log::info('Memulai proses ekspor semua item.');
        
        // Nama file sekarang statis karena selalu mengekspor semua data
        return Excel::download(new ItemsExport, 'item.xlsx');
    }

    /**
     * Impor data Item dari Excel.
     */
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv']);

        DB::beginTransaction(); // <-- Mulai transaksi database

        try {
            // PERBAIKAN: Gunakan toCollection untuk memproses data sebelum disimpan
            $collection = Excel::toCollection(new ItemsImport, $request->file('file'));

            // Loop melalui setiap baris dari sheet pertama
            foreach ($collection[0] as $row) {
                Item::create([
                    'item_no'          => $this->generateNewItemNo(), // Generate nomor baru yang aman untuk SETIAP baris
                    'ahs'              => $row['ahs'],
                    'deskripsi'        => $row['deskripsi'],
                    'merek'            => $row['merek'] ?? null,
                    'satuan'           => $row['satuan'],
                    'hpp'              => $row['hpp'],
                    'vendor_id'        => $row['vendor_id'] ?? null,
                    'wilayah'          => $row['wilayah'] ?? null,
                    'tahun'            => $row['tahun'],
                    'spesifikasi'      => $row['spesifikasi'] ?? null,
                    'produk_deskripsi' => $row['produk_deskripsi'] ?? null,
                ]);
            }

            DB::commit(); // <-- Jika semua berhasil, simpan perubahan secara permanen
            Log::info('File item berhasil diimpor.');

            return response()->json([
                'success' => true,
                'message' => 'Data item berhasil diimpor',
            ], 200);

        } catch (ValidationException $e) {
            DB::rollBack(); // <-- Batalkan semua jika ada error validasi
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
            DB::rollBack(); // <-- Batalkan semua jika ada error tak terduga
            Log::error('Gagal impor item karena error tak terduga: ' . $e->getMessage());
            Log::error($e); // Log stack trace lengkap
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat impor.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ===================================================================
    // PRIVATE HELPER METHODS (Untuk mengurangi duplikasi kode)
    // ===================================================================

    /**
     * Mencari item berdasarkan ID primary key atau item_no custom.
     */
    private function findItem($id)
    {
        // Cari berdasarkan primary key dulu (lebih cepat)
        $item = Item::with('vendor')->find($id);

        // Jika tidak ketemu, cari berdasarkan item_no
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
        // Menggunakan latest berdasarkan primary key lebih andal
        $lastItem = Item::latest('item_id')->first();
        $newIdNumber = 1;

        if ($lastItem && isset($lastItem->item_no)) {
            // Ambil angka dari string 'M_XXX'
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
            // Menggunakan nama file asli untuk konsistensi
            $originalFileName = $file->getClientOriginalName();
            Log::info("File '$originalFileName' diupload ke direktori '$directory'.");
            return $file->storeAs($directory, $originalFileName, 'public');
        }
        return null;
    }
}
