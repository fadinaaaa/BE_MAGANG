<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    /**
     * Ambil semua data Item
     */
    public function index()
    {
        $items = Item::with('vendor')->get();

        return response()->json([
            'success' => true,
            'data' => $items
        ]);
    }

    /**
     * Simpan Item baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ahs' => 'required|string',
            'deskripsi' => 'required|string',
            'merek' => 'nullable|string',
            'satuan' => 'required|string',
            'hpp' => 'required|numeric',
            'vendor_id' => 'nullable|integer|exists:vendors,vendor_id',
            'wilayah' => 'nullable|string',
            'tahun' => 'required|integer',
            'produk_foto' => 'nullable|array',
            'produk_deskripsi' => 'nullable|string',
            'produk_dokumen' => 'nullable|array',
            'produk_hitungan' => 'nullable|array',
            'spesifikasi' => 'nullable|string',
        ]);

        $item = Item::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Item berhasil ditambahkan',
            'data' => $item
        ], 201);
    }

    /**
     * Detail Item by ID
     */
    public function show($id)
    {
        $item = Item::with('vendor')->find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $item
        ]);
    }

    /**
     * Update Item
     */
    public function update(Request $request, $id)
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'ahs' => 'sometimes|string',
            'deskripsi' => 'sometimes|string',
            'merek' => 'nullable|string',
            'satuan' => 'sometimes|string',
            'hpp' => 'sometimes|numeric',
            'vendor_id' => 'nullable|integer|exists:vendors,vendor_id',
            'wilayah' => 'nullable|string',
            'tahun' => 'sometimes|integer',
            'produk_foto' => 'nullable|array',
            'produk_deskripsi' => 'nullable|string',
            'produk_dokumen' => 'nullable|array',
            'produk_hitungan' => 'nullable|array',
            'spesifikasi' => 'nullable|string',
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Item berhasil diupdate',
            'data' => $item
        ]);
    }

    /**
     * Hapus Item
     */
    public function destroy($id)
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item tidak ditemukan'
            ], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item berhasil dihapus'
        ]);
    }
}
