<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VendorController extends Controller
{
    /**
     * Menampilkan semua vendor
     */
    public function index()
    {
        $vendors = Vendor::all();
        return response()->json($vendors);
    }

    /**
     * Menyimpan vendor baru
     */
    public function store(Request $request)
    {
        Log::info('Store vendor route hit!');
        Log::info($request->all());
        $request->validate([
            'vendor_name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'contact_no'   => 'required|string|max:20',
            'email'        => 'required|email|unique:vendors',
            'wilayah'      => 'required|string',
            'tahun'        => 'required|integer',
        ]);

        $vendor = Vendor::create($request->all());

        return response()->json($vendor, 201);
        Log::info('Store vendor route hit!');
        Log::info($request->all());

        dd('Store method triggered', $request->all());
    }

    /**
     * Menampilkan 1 vendor berdasarkan ID
     */
    public function show($id)
    {
        $vendor = Vendor::findOrFail($id);
        return response()->json($vendor);
    }

    /**
     * Update vendor
     */
    public function update(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->update($request->all());

        return response()->json($vendor);
    }

    /**
     * Hapus vendor
     */
    public function destroy($id)
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->delete();

        return response()->json(null, 204);
    }
}
