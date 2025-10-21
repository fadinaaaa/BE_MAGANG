<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\VendorsExport;
use App\Imports\VendorsImport;
use Maatwebsite\Excel\Validators\ValidationException;
use Exception;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    /**
     * Menampilkan semua vendor
     */
    public function index(Request $request)
    {
        $query = Vendor::query();

        // filter berdasarkan wilayah
        if ($request->has('wilayah') && !empty($request->wilayah)) {
            $query->where('wilayah', $request->wilayah);
        }

        // filter berdasarkan tahun
        if ($request->has('tahun') && !empty($request->tahun)) {
            $query->where('tahun', $request->tahun);
        }

        $vendors = $query->get();

        return response()->json($vendors);
    }

    /**
     * Menyimpan vendor baru
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            // Validasi 'vendor_no' dihapus karena akan digenerate otomatis
            'vendor_name'  => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'contact_no'   => 'required|string|max:20',
            'email'        => 'required|email|unique:vendors,email',
            'wilayah'      => 'required|string',
            'tahun'        => 'required|integer',
        ]);

        // Generate vendor_no baru secara otomatis
        $validatedData['vendor_no'] = $this->generateNewVendorNo();

        $vendor = Vendor::create($validatedData);

        return response()->json($vendor, 201);
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

        $request->validate([
            'vendor_no'    => ['required', 'string', Rule::unique('vendors')->ignore($vendor->vendor_id, 'vendor_id')],
            'vendor_name'  => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'contact_no'   => 'required|string|max:20',
            'email'        => ['required', 'email', Rule::unique('vendors')->ignore($vendor->vendor_id, 'vendor_id')],
            'wilayah'      => 'required|string',
            'tahun'        => 'required|integer',
        ]);

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

        return response()->json([
            'success' => true,
            'message' => 'Vendor berhasil dihapus'
        ], 200);
    }

    public function export()
    {
        try {
            // Ambil semua data vendor tanpa filter bulan & tahun
            $vendors = Vendor::all();

            // Cek apakah ada data
            if ($vendors->isEmpty()) {
                Log::error('Export Excel gagal: Tidak ada data vendor.');
                return response()->json(['message' => 'Tidak ada data vendor untuk diexport'], 404);
            }

            // Export semua data vendor
            return Excel::download(new VendorsExport($vendors), 'vendor.xlsx');
        } catch (Exception $e) {
            // Tangkap error tidak terduga dan log
            Log::error('Export Excel error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Terjadi kesalahan saat export Excel'], 500);
        }
    }

    /**
     * Import data Vendor dari Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        try {
            Excel::import(new VendorsImport, $request->file('file'));

            Log::info('File vendor berhasil diimpor.');

            return response()->json([
                'success' => true,
                'message' => 'Data vendor berhasil diimpor'
            ], 200);
        } catch (ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values()
                ];
            }

            Log::error('Gagal impor vendor karena validasi: ', $errors);

            return response()->json([
                'success' => false,
                'message' => 'Terdapat kesalahan validasi pada file yang diunggah.',
                'errors' => $errors
            ], 422);
        }
    }

    /**
     * Menghasilkan vendor_no baru secara berurutan.
     */
    private function generateNewVendorNo()
    {
        // Menggunakan latest berdasarkan primary key lebih andal
        $lastVendor = Vendor::latest('vendor_id')->first();
        $newIdNumber = 1;

        if ($lastVendor && isset($lastVendor->vendor_no)) {
            // Ambil angka dari string 'V_XXX'
            $lastIdNumber = intval(substr($lastVendor->vendor_no, 2));
            $newIdNumber = $lastIdNumber + 1;
        }

        // Menggunakan str_pad dengan panjang 3 untuk format '001', '002', dst.
        return 'V_' . str_pad($newIdNumber, 3, '0', STR_PAD_LEFT);
    }
}
