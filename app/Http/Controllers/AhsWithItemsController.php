<?php

namespace App\Http\Controllers;

use App\Models\Ahs;
use App\Models\AhsItem;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AhsWithItemsController extends Controller
{
    public function generateNoAhs()
    {
        $prefix = 'AHS';

        $last = DB::table('items')
            ->where('item_no', 'like', "$prefix%")
            ->orderBy('item_id', 'desc')
            ->value('item_no');

        if ($last) {
            // Ambil semua digit di akhir string
            preg_match('/(\d+)$/', $last, $matches);
            $lastNumber = isset($matches[1]) ? (int) $matches[1] : 0;
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = '1';
        }

        $no_ahs = $prefix . $nextNumber;

        return $no_ahs;
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            // Validasi AHS (header)
            $validated = $request->validate([
                'ahs'                => 'required|string',
                'deskripsi'          => 'required|string',
                'satuan'             => 'required|string',
                'wilayah'            => 'required|string',
                'tahun'              => 'required|integer',

                'items'              => 'required|array',
                'items.*.item_id'    => 'required|exists:items,item_id',
                'items.*.volume'     => 'required|numeric|min:0.01',
            ]);

            // Simpan header AHS
            $ahs = Ahs::create([
                'ahs'       => $validated['ahs'],
                'deskripsi' => $validated['deskripsi'],
                'satuan'    => $validated['satuan'],
                'wilayah'   => $validated['wilayah'],
                'tahun'     => $validated['tahun'],
                'harga_pokok_total' => 0, // nanti dihitung dari total jumlah item
            ]);

            $totalHarga = 0;

            // Proses detail
            foreach ($validated['items'] as $inputItem) {
                $item = Item::findOrFail($inputItem['item_id']);

                $volume = $inputItem['volume'];
                $hpp = $item->hpp;
                $jumlah = $volume * $hpp;

                $ahs->items()->create([
                    'item_id' => $item->item_id,
                    'uraian'  => $item->deskripsi,
                    'satuan'  => $item->satuan,
                    'volume'  => $volume,
                    'hpp'     => $hpp,
                    'jumlah'  => $jumlah,
                ]);

                $totalHarga += $jumlah;
            }

            // Update total harga
            $ahs->update(['harga_pokok_total' => $totalHarga]);

            DB::commit();

            return response()->json([
                'message' => 'AHS dan detail berhasil disimpan',
                'data'    => $ahs->load('items.item')
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getOptionItem(Request $request)
    {
        DB::beginTransaction();

        try {
            $request->validate(
                [
                    'wilayah' => 'required|string'
                ],
                [
                    'wilayah.required' => 'masukkan wilayah dahulu'
                ]
            );

            $data_option = Item::where('wilayah', $request->wilayah)
                ->select(
                    'item_id',
                    'item_no',
                    'deskripsi',
                    'satuan',
                    'hpp'
                )
                ->get();

            if (!isset($data_option)) {
                throw new \Exception('Data item untuk wilayah ' . $request->wilayah . ' tidak tersedia');
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data option item',
                'data'    => $data_option
            ], 200);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->errors(),
                'data'    => []
            ], 422);
        }
    }

    public function addDataAhs(Request $request)
    {
        DB::beginTransaction();

        try {
            $request->validate(
                [
                    'deskripsi' => 'required|string',
                    'satuan' => 'required|string',
                    'wilayah' => 'required|string',
                    'tahun' => 'required|string',
                    'items'              => 'required|array',
                    'items.*.item_id'    => 'required|exists:items,item_id',
                    'items.*.volume'     => 'required|numeric|min:0.01',
                ],
                [
                    'deskripsi.required' => 'masukkan deskripsi ahs',
                    'satuan.required' => 'masukkan satuan ahs',
                    'wilayah.required' => 'masukkan wilayah ahs',
                    'tahun.required' => 'masukkan tahun ahs',
                    'items.required' => 'tambahkan item ahs'
                ]
            );

            $add_ahs = Ahs::create([
                'ahs'       => 'no ahs sementara',
                'deskripsi' => $request->deskripsi,
                'satuan'    => $request->satuan,
                'wilayah'   => $request->wilayah,
                'tahun'     => $request->tahun,
                'harga_pokok_total' => 0
            ]);


            $totalHppAhs = 0;

            if ($add_ahs) {
                foreach ($request->items as $item) {
                    AhsItem::create([
                        'ahs_id' => $add_ahs->ahs_id,
                        'item_id' => $item['item_id'],
                        'uraian' => $item['uraian'] ?? null,
                        'satuan' => $item['satuan'] ?? null,
                        'volume' => $item['volume'],
                        'hpp' => $item['hpp'] ?? 0,
                        'jumlah' => $item['jumlah'] ?? 0,
                    ]);

                    $totalHppAhs += $item['jumlah'] ?? 0;
                }
            }

            $add_ahs->update(['harga_pokok_total' => $totalHppAhs]);

            $add_ahs_to_item = Item::create([
                'item_no' => $this->generateNoAhs(),
                'ahs' => 'AHS',
                'deskripsi' => $add_ahs->deskripsi,
                'satuan' => $add_ahs->satuan,
                'hpp' => $add_ahs->harga_pokok_total,
                'wilayah' => $add_ahs->wilayah,
                'merek' => '',
                'tahun' => $add_ahs->tahun
            ]);

            $add_ahs->update(['ahs' => $add_ahs_to_item->item_no]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data ahs berhasil ditambahkan'
            ], 200);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $ahs_id)
    {
        DB::beginTransaction();

        try {
            $request->validate(
                [
                    'deskripsi' => 'required|string',
                    'satuan' => 'required|string',
                    'wilayah' => 'required|string',
                    'tahun' => 'required|string',
                    'items'              => 'required|array',
                    'items.*.item_id'    => 'required|exists:items,item_id',
                    'items.*.volume'     => 'required|numeric|min:0.01',
                ],
                [
                    'deskripsi.required' => 'masukkan deskripsi ahs',
                    'satuan.required' => 'masukkan satuan ahs',
                    'wilayah.required' => 'masukkan wilayah ahs',
                    'tahun.required' => 'masukkan tahun ahs',
                    'items.required' => 'tambahkan item ahs'
                ]
            );

            $ahs = Ahs::where('ahs_id', $ahs_id)->first();

            if (!$ahs) {
                throw new \Exception('Data Ahs tidak ditemukan');
            }

            // Update header AHS
            $ahs->deskripsi = $request->deskripsi;
            $ahs->satuan    = $request->satuan;
            $ahs->wilayah   = $request->wilayah;
            $ahs->tahun     = $request->tahun;

            // Hapus item lama untuk diganti baru
            AhsItem::where('ahs_id', $ahs->ahs_id)->delete();

            // Tambahkan item baru
            $totalHppAhs = 0;
            foreach ($request->items as $item) {
                AhsItem::create([
                    'ahs_id' => $ahs->ahs_id,
                    'item_id' => $item['item_id'],
                    'uraian' => $item['uraian'] ?? null,
                    'satuan' => $item['satuan'] ?? null,
                    'volume' => $item['volume'],
                    'hpp' => $item['hpp'] ?? 0,
                    'jumlah' => $item['jumlah'] ?? 0,
                ]);

                $totalHppAhs += $item['jumlah'] ?? 0;
            }

            // Update total harga di tabel AHS & ITEM
            $ahs->harga_pokok_total = $totalHppAhs;
            $ahs->save();

            $item_ahs = Item::where('item_no', $ahs->ahs)->first();

            if (!$item_ahs) {
                throw new \Exception('Data Ahs pada item tidak ditemukann '. $ahs->ahs);
            }

            // Update juga di tabel Item (yang merepresentasikan AHS ini)
            $item_ahs->update([
                'deskripsi' => $ahs->deskripsi,
                'satuan' => $ahs->satuan,
                'hpp' => $totalHppAhs,
                'wilayah' => $ahs->wilayah,
                'tahun' => $ahs->tahun
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data AHS berhasil diperbarui',
                'data'    => $ahs->load('items')
            ], 200);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat update',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($ahs_id)
    {
        DB::beginTransaction();

        try {
            $ahs = Ahs::where('ahs_id', $ahs_id)->first();

            if (!$ahs) {
                throw new \Exception('Data Ahs tidak ditemukan');
            }

            // Hapus semua item terkait
            AhsItem::where('ahs_id', $ahs->ahs_id)->delete();

            // Hapus item di tabel Item (yang punya kode AHS ini)
            Item::where('item_no', $ahs->ahs)->delete();

            // Hapus header AHS
            $ahs->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data AHS berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data AHS',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
