<?php

namespace App\Http\Controllers;

use App\Models\Ahs;
use App\Models\AhsItem;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AhsExport;
use App\Exports\AhsImportTemplateExport;
use App\Imports\AhsImport;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage; // Tambahkan ini untuk delete file

class AhsWithItemsController extends Controller
{
    public function generateNoAhs()
    {
        $prefix = 'AHS';

        // Fungsi ini sudah benar menggunakan item_no
        $last = DB::table('items')
            ->where('item_no', 'like', "$prefix%")
            ->orderBy('item_id', 'desc')
            ->value('item_no');

        if ($last) {
            preg_match('/(\d+)$/', $last, $matches);
            $lastNumber = isset($matches[1]) ? (int) $matches[1] : 0;
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . $nextNumber;
    }

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

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'ahs'       => 'required|string',
                'deskripsi' => 'required|string',
                'merek' => 'required|string',
                'satuan'    => 'required|string',
                'vendor_id' => 'required|integer',
                'provinsi'  => 'required|string',
                'kab'       => 'required|string',
                'tahun'     => 'required|integer',
                'produk_foto' => 'required|file',
                'produk_deskripsi' => 'required|string',
                'produk_dokumen' => 'required|file',
                'spesifikasi' => 'required|string',

                'items'              => 'required|array',
                'items.*.item_no'    => 'required|string|exists:items,item_no',
                'items.*.volume'     => 'required|numeric|min:0.01',
            ]);

            $ahs = Ahs::create([
                'ahs'     => $validated['ahs'],
                'deskripsi' => $validated['deskripsi'],
                'satuan'    => $validated['satuan'],
                'provinsi'  => $validated['provinsi'],
                'kab'       => $validated['kab'],
                'tahun'     => $validated['tahun'],
                'harga_pokok_total' => 0,
            ]);

            $totalHarga = 0;

            foreach ($validated['items'] as $inputItem) {
                // Cari item berdasarkan item_no, bukan item_id
                $item = Item::where('item_no', $inputItem['item_no'])->firstOrFail();

                $volume = $inputItem['volume'];
                $hpp = $item->hpp;
                $jumlah = $volume * $hpp;

                $ahs->items()->create([
                    'item_id' => $item->item_id, // Foreign key tetap menggunakan item_id
                    'uraian'  => $item->deskripsi,
                    'satuan'  => $item->satuan,
                    'volume'  => $volume,
                    'hpp'     => $hpp,
                    'jumlah'  => $jumlah,
                ]);

                $totalHarga += $jumlah;
            }

            $ahs->update(['harga_pokok_total' => $totalHarga]);

            DB::commit();

            return response()->json([
                'message' => 'AHS dan detail berhasil disimpan',
                'data'    => $ahs->load('items.item')
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Validasi gagal', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan', 'error' => $e->getMessage()], 500);
        }
    }

    public function getOptionItem(Request $request)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'provinsi' => 'required|string',
                'kab'      => 'required|string',
            ], [
                'provinsi.required' => 'Masukkan provinsi dahulu',
                'kab.required' => 'Masukkan kab dahulu',
            ]);

            $data_option = Item::where('provinsi', $request->provinsi)
                ->where('kab', $request->kab)
                ->select('item_id', 'item_no', 'deskripsi', 'satuan', 'hpp')
                ->get();

            if ($data_option->isEmpty()) {
                throw new \Exception('Data item untuk ' . $request->provinsi . ', ' . $request->kab . ' tidak tersedia');
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data option item',
                'data'    => $data_option
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->errors(), 'data' => []], 422);
        }
    }

    public function addDataAhs(Request $request)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'deskripsi' => 'required|string',
                'satuan'    => 'required|string',
                'provinsi'  => 'required|string',
                'kab'       => 'required|string',
                'tahun'     => 'required|string',
                'merek' => 'nullable|string',
                'vendor_id' => 'nullable|integer|exists:vendors,vendor_id',
                'produk_foto' => 'nullable|file',
                'produk_deskripsi' => 'nullable|string',
                'produk_dokumen' => 'nullable|file',
                'spesifikasi' => 'nullable|string',

                'items'     => 'required|array',
                'items.*.item_no' => 'required|string|exists:items,item_no',
                'items.*.volume'  => 'required|numeric|min:0.01',
            ], [
                'deskripsi.required' => 'Masukkan deskripsi!',
                'satuan.required' => 'Masukkan satuan!',
                'provinsi.required' => 'Pilih provinsi!',
                'kab.required' => 'Pilih kabupaten!',
                'tahun.required' => 'Masukkan tahun!',
                'items.required' => 'Tambahkan item minimal satu!',
                'items.*.item_no.required' => 'Pilih item!',
                'items.*.item_no.exists' => 'Item tidak ditemukan dalam database!',
                'items.*.volume.required' => 'Masukkan volume!',
                'items.*.volume.numeric' => 'Volume harus berupa angka!',
                'items.*.volume.min' => 'Volume minimal 0.01!',
            ]);

            $vendorId = $request->vendor_id;

            $add_ahs = Ahs::create([
                'ahs'       => 'no ahs sementara',
                'deskripsi' => $request->deskripsi,
                'satuan'    => $request->satuan,
                'provinsi'  => $request->provinsi,
                'kab'       => $request->kab,
                'tahun'     => $request->tahun,
                'harga_pokok_total' => 0
            ]);

            $totalHppAhs = 0;

            foreach ($request->items as $inputItem) {
                $item = Item::where('item_no', $inputItem['item_no'])->firstOrFail();
                $volume = $inputItem['volume'];
                $hpp = $item->hpp;
                $uraian = $item->deskripsi;
                $satuan = $item->satuan;
                $jumlah = $volume * $hpp;

                AhsItem::create([
                    'ahs_id'  => $add_ahs->ahs_id,
                    'item_id' => $item->item_id,
                    'uraian'  => $uraian,
                    'satuan'  => $satuan,
                    'volume'  => $volume,
                    'hpp'     => $hpp,
                    'jumlah'  => $jumlah,
                ]);

                $totalHppAhs += $jumlah;
            }

            $add_ahs->update(['harga_pokok_total' => $totalHppAhs]);

            $produk_foto    = $this->uploadFile($request, 'produk_foto', 'uploads/foto');
            $produk_dokumen = $this->uploadFile($request, 'produk_dokumen', 'uploads/dokumen');

            $add_ahs_to_item = Item::create([
                'item_no'   => $this->generateNoAhs(),
                'ahs'       => 'AHS',
                'deskripsi' => $add_ahs->deskripsi,
                'satuan'    => $add_ahs->satuan,
                'hpp'       => $add_ahs->harga_pokok_total,
                'provinsi'  => $add_ahs->provinsi,
                'kab'       => $add_ahs->kab,
                'tahun'     => $add_ahs->tahun,
                'merek' => $request->merek,
                'vendor_id' => $vendorId,
                'produk_foto' => $produk_foto,
                'produk_deskripsi' => $request->produk_deskripsi,
                'produk_dokumen' => $produk_dokumen,
                'spesifikasi' => $request->spesifikasi
            ]);

            $add_ahs->update(['ahs' => $add_ahs_to_item->item_no]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Data AHS berhasil ditambahkan']);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $ahs_id)
    {
        DB::beginTransaction();

        try {
            // --- PERUBAHAN UTAMA: Tambahkan validasi untuk field detail (merek, vendor, dll) ---
            $request->validate([
                'deskripsi' => 'required|string',
                'satuan'    => 'required|string',
                'provinsi'  => 'required|string',
                'kab'       => 'required|string',
                'tahun'     => 'required|string',
                // Field tambahan yang sebelumnya hilang
                'merek'            => 'nullable|string',
                'vendor_id'        => 'nullable|integer|exists:vendors,vendor_id',
                'spesifikasi'      => 'nullable|string',
                'produk_deskripsi' => 'nullable|string',
                'produk_foto'      => 'nullable|file', // Bisa nullable jika tidak update foto
                'produk_dokumen'   => 'nullable|file',

                'items'     => 'required|array',
                'items.*.item_no' => 'required|string|exists:items,item_no',
                'items.*.volume'  => 'required|numeric|min:0.01',
            ]);

            $ahs = Ahs::find($ahs_id);
            if (!$ahs) throw new \Exception('Data AHS tidak ditemukan');

            // 1. Update Tabel AHS (Hanya header dasar)
            $ahs->update([
                'deskripsi' => $request->deskripsi,
                'satuan'    => $request->satuan,
                'provinsi'  => $request->provinsi,
                'kab'       => $request->kab,
                'tahun'     => $request->tahun,
            ]);

            // 2. Update Komponen AHS (Hapus & Buat Ulang)
            AhsItem::where('ahs_id', $ahs->ahs_id)->delete();
            $totalHppAhs = 0;

            foreach ($request->items as $inputItem) {
                $item = Item::where('item_no', $inputItem['item_no'])->firstOrFail();
                $volume = $inputItem['volume'];
                $hpp = $item->hpp;
                $uraian = $item->deskripsi;
                $satuan = $item->satuan;
                $jumlah = $volume * $hpp;

                AhsItem::create([
                    'ahs_id'  => $ahs->ahs_id,
                    'item_id' => $item->item_id,
                    'uraian'  => $uraian,
                    'satuan'  => $satuan,
                    'volume'  => $volume,
                    'hpp'     => $hpp,
                    'jumlah'  => $jumlah,
                ]);

                $totalHppAhs += $jumlah;
            }

            $ahs->update(['harga_pokok_total' => $totalHppAhs]);

            // 3. Update Tabel ITEM (Disini field lengkap seperti merek, vendor, dll disimpan)
            $item_ahs = Item::where('item_no', $ahs->ahs)->first();
            if (!$item_ahs) throw new \Exception('Item AHS tidak ditemukan');

            $dataItemUpdate = [
                'deskripsi'        => $ahs->deskripsi,
                'satuan'           => $ahs->satuan,
                'hpp'              => $totalHppAhs,
                'provinsi'         => $ahs->provinsi,
                'kab'              => $ahs->kab,
                'tahun'            => $ahs->tahun,
                // Masukkan field tambahan disini
                'merek'            => $request->merek,
                'vendor_id'        => $request->vendor_id,
                'spesifikasi'      => $request->spesifikasi,
                'produk_deskripsi' => $request->produk_deskripsi,
            ];

            // Cek jika ada upload file baru
            if ($request->hasFile('produk_foto')) {
                // Hapus foto lama jika perlu (opsional)
                if ($item_ahs->produk_foto) {
                     Storage::disk('public')->delete($item_ahs->produk_foto);
                }
                $dataItemUpdate['produk_foto'] = $this->uploadFile($request, 'produk_foto', 'uploads/foto');
            }

            if ($request->hasFile('produk_dokumen')) {
                if ($item_ahs->produk_dokumen) {
                     Storage::disk('public')->delete($item_ahs->produk_dokumen);
                }
                $dataItemUpdate['produk_dokumen'] = $this->uploadFile($request, 'produk_dokumen', 'uploads/dokumen');
            }

            $item_ahs->update($dataItemUpdate);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Data AHS berhasil diperbarui']);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan saat update', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($ahs_id)
    {
        DB::beginTransaction();

        try {
            $ahs = Ahs::find($ahs_id);
            if (!$ahs) throw new \Exception('Data AHS tidak ditemukan');

            AhsItem::where('ahs_id', $ahs->ahs_id)->delete();
            Item::where('item_no', $ahs->ahs)->delete();
            $ahs->delete();

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Data AHS berhasil dihapus']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data AHS', 'error' => $e->getMessage()], 500);
        }
    }

    public function export()
    {
        try {
            return Excel::download(new AhsExport(), 'data_ahs.xlsx');
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal melakukan export', 'error' => $e->getMessage()], 500);
        }
    }

    public function downloadImportTemplate()
    {
        try {
            return Excel::download(new AhsImportTemplateExport(), 'template_import_ahs.xlsx');
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengunduh template', 'error' => $e->getMessage()], 500);
        }
    }

    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls'
            ]);

            DB::beginTransaction();
            Excel::import(new AhsImport(), $request->file('file'));
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Data AHS berhasil di-import.']);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Validasi file gagal', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan saat import data', 'error' => $e->getMessage()], 500);
        }
    }
}