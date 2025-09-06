<?php

namespace App\Http\Controllers;

use App\Models\Ahs;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AhsWithItemsController extends Controller
{
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
}
