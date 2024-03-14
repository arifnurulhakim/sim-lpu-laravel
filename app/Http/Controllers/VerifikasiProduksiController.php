<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Models\VerifikasiProduksi;
use App\Models\VerifikasiProduksiDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class VerifikasiProduksiController extends Controller
{
    public function getPerTahun(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'tahun_anggaran' => 'nullable|numeric', // Menyatakan bahwa tahun_anggaran bersifat opsional dan harus berupa angka
                'triwulan' => 'nullable|numeric|in:1,2,3,4', // Menyatakan bahwa triwulan bersifat opsional, harus berupa angka, dan nilainya hanya boleh 1, 2, 3, atau 4
                'status' => 'nullable|string|in:7,9', // Menyatakan bahwa status bersifat opsional, harus berupa string, dan nilainya hanya boleh "aktif" atau "nonaktif"
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $tahun_anggaran = request()->get('tahun_anggaran', '');
            $triwulan = request()->get('triwulan', '');
            $status = request()->get('status', '');

            $defaultOrder = $getOrder ? $getOrder : "regional.nama ASC";
            $orderMappings = [
                'namaASC' => 'regional.nama ASC',
                'namaDESC' => 'regional.nama DESC',
                'triwulanASC' => 'produksi.triwulan ASC',
                'triwulanDESC' => 'produksi.triwulan DESC',
                'tahunASC' => 'produksi.tahun_anggaran ASC',
                'tahunDESC' => 'produksi.tahun_anggaran DESC',
            ];

            // Set the order based on the mapping or use the default order if not found
            $order = $orderMappings[$getOrder] ?? $defaultOrder;
            // Validation rules for input parameters
            $validOrderValues = implode(',', array_keys($orderMappings));
            $rules = [
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',
                'order' => "in:$validOrderValues",
            ];

            $validator = Validator::make([
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
            ], $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $produksiQuery = VerifikasiProduksi::orderByRaw($order)
                ->select(
                    'produksi.id_regional',
                    'produksi.triwulan',
                    'produksi.tahun_anggaran',
                    'regional.nama as nama_regional',
                    DB::raw('SUM(produksi.total_lpu) as total_lpu'),
                    DB::raw('SUM(produksi.total_lpu_prognosa) as total_lpu_prognosa'),
                    DB::raw('SUM(produksi.total_lpk) as total_lpk'),
                    DB::raw('SUM(produksi.total_lpk_prognosa) as total_lpk_prognosa'),
                    DB::raw('SUM(produksi.total_lbf) as total_lbf'),
                    DB::raw('SUM(produksi.total_lbf_prognosa) as total_lbf_prognosa')
                )
                ->join('regional', 'produksi.id_regional', '=', 'regional.id')
            // ->join('produksi_detail', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->groupBy('produksi.id_regional', 'produksi.triwulan', 'produksi.tahun_anggaran')
                ->offset($offset)
                ->limit($limit);
            // $produksiQuery = VerifikasiProduksiDetail::orderByRaw($order)
            //     ->select('produksi.id_regional',
            //         'produksi.triwulan',
            //         'produksi.tahun_anggaran',
            //         'regional.nama as nama_regional', DB::raw('SUM(produksi_detail.verifikasi) as total_produksi'))
            //     ->join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
            //     ->join('kprk', 'produksi.id_kprk', '=', 'kprk.id')
            //     ->join('regional', 'produksi.id_regional', '=', 'regional.id')
            //     ->groupBy('kprk.id', 'produksi.id_regional', 'produksi.triwulan', 'produksi.tahun_anggaran', 'regional.nama')
            //     ->offset($offset)
            //     ->limit($limit);

            if ($search !== '') {
                $produksiQuery->where('nama_regional', 'like', "%$search%");
            }
            // Menambahkan kondisi WHERE berdasarkan variabel $tahun_anggaran, $triwulan, dan $status
            if ($tahun_anggaran !== '') {
                $produksiQuery->where('produksi.tahun_anggaran', $tahun_anggaran);
            }
            if ($triwulan !== '') {
                $produksiQuery->where('produksi.triwulan', $triwulan);
            }
            if ($status !== '') {
                $produksiQuery->where('produksi.id_status', $status);
            }

            $produksi = $produksiQuery->get();
            // dd($produksi);
            $grand_total = $produksi->sum('total_lpu') + $produksi->sum('total_lpk') + $produksi->sum('total_lbf');
            $grand_total = "Rp " . number_format($grand_total, 2, ',', '.');

            // Mengubah format total_produksi menjadi format Rupiah
            foreach ($produksi as $item) {
                $item->total_produksi = "Rp " . number_format($item->total_lpu + $item->total_lpk + $item->total_lbf, 2, ',', '.');

                // AmbilVerifikasiProduksi dengan kriteria tertentu
                $getProduksi = VerifikasiProduksi::where('tahun_anggaran', $item->tahun_anggaran)
                    ->where('id_regional', $item->id_regional)
                    ->where('triwulan', $item->triwulan)
                    ->get();

                // Periksa apakah semua status dalam $getProduksi adalah 9
                $semuaStatusSembilan = $getProduksi->every(function ($produksi) {
                    return $produksi->id_status == 9;
                });

                // Jika semua status adalah 9, ambil status dari tabel Status
                if ($semuaStatusSembilan) {
                    $status = Status::where('id', 9)->first();
                    $item->status = $status->nama;
                } else {
                    $status = Status::where('id', 7)->first();
                    $item->status = $status->nama;
                }
            }

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'grand_total' => $grand_total,
                'data' => $produksi,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function getPerRegional(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun_anggaran' => 'nullable|numeric',
                'triwulan' => 'nullable|numeric|in:1,2,3,4',
                'id_regional' => 'nullable|numeric|exists:regional,id',
                'status' => 'nullable|string|in:7,9',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $id_regional = request()->get('id_regional', '');
            $tahun_anggaran = request()->get('tahun_anggaran', '');
            $triwulan = request()->get('triwulan', '');
            $status = request()->get('status', '');
            $defaultOrder = $getOrder ? $getOrder : "kprk.id ASC";
            $orderMappings = [
                'namaASC' => 'kprk.nama ASC',
                'namaDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'produksi.triwulan ASC',
                'triwulanDESC' => 'produksi.triwulan DESC',
                'tahunASC' => 'produksi.tahun_anggaran ASC',
                'tahunDESC' => 'produksi.tahun_anggaran DESC',
            ];

            // Set the order based on the mapping or use the default order if not found
            $order = $orderMappings[$getOrder] ?? $defaultOrder;
            // Validation rules for input parameters
            $validOrderValues = implode(',', array_keys($orderMappings));
            $rules = [
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',
                'order' => "in:$validOrderValues",
            ];

            $validator = Validator::make([
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
            ], $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }
            $produksiQuery = VerifikasiProduksiDetail::orderByRaw($order)
                ->select('produksi.id', 'produksi.triwulan', 'produksi.tahun_anggaran', 'regional.nama as nama_regional', 'kprk.id as id_kcu', 'kprk.nama as nama_kcu', DB::raw('SUM(produksi_detail.pelaporan) as total_produksi'))
                ->join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->join('kprk', 'produksi.id_kprk', '=', 'kprk.id')
                ->join('regional', 'produksi.id_regional', '=', 'regional.id')
                ->groupBy('kprk.id', 'produksi.id_regional', 'produksi.triwulan', 'produksi.tahun_anggaran', 'regional.nama')
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $produksiQuery->where('kprk.nama', 'like', "%$search%");
            }
            if ($id_regional !== '') {
                $produksiQuery->where('produksi.id_regional', $id_regional);
            }
            if ($tahun_anggaran !== '') {
                $produksiQuery->where('produksi.tahun_anggaran', $tahun_anggaran);
            }

            if ($triwulan !== '') {
                $produksiQuery->where('produksi.triwulan', $triwulan);
            }

            if ($status !== '') {
                $produksiQuery->where('produksi.id_status', $status);
            }

            $produksi = $produksiQuery->get();

            // Mengubah format total_produksi menjadi format Rupiah
            foreach ($produksi as $item) {
                $item->total_produksi = "Rp " . number_format($item->total_produksi, 2, ',', '.');

                // AmbilVerifikasiProduksi dengan kriteria tertentu
                $getProduksi = VerifikasiProduksi::where('tahun_anggaran', $item->tahun_anggaran)
                    ->where('id_regional', $item->id_regional)
                    ->where('triwulan', $item->triwulan)
                    ->get();

                // Periksa apakah semua status dalam $getProduksi adalah 9
                $semuaStatusSembilan = $getProduksi->every(function ($produksi) {
                    return $produksi->id_status == 9;
                });

                // Jika semua status adalah 9, ambil status dari tabel Status
                if ($semuaStatusSembilan) {
                    $status = Status::where('id', 9)->first();
                    $item->status = $status->nama;
                } else {
                    $status = Status::where('id', 7)->first();
                    $item->status = $status->nama;
                }
            }

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'data' => $produksi,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function getPerKCU(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun_anggaran' => 'nullable|numeric',
                'triwulan' => 'nullable|numeric|in:1,2,3,4',
                'id_kcu' => 'nullable|numeric|exists:kprk,id',
                'status' => 'nullable|string|in:7,9',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $id_kcu = request()->get('id_kcu', '');
            dd($id_kcu);
            $tahun_anggaran = request()->get('tahun_anggaran', '');
            $triwulan = request()->get('triwulan', '');
            $status = request()->get('status', '');
            $defaultOrder = $getOrder ? $getOrder : "kpc.id ASC";
            $orderMappings = [
                'namakpcASC' => 'kpc.nama ASC',
                'namakpcDESC' => 'kpc.nama DESC',
                'namakcuASC' => 'kprk.nama ASC',
                'namakcuDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'produksi.triwulan ASC',
                'triwulanDESC' => 'produksi.triwulan DESC',
                'tahunASC' => 'produksi.tahun_anggaran ASC',
                'tahunDESC' => 'produksi.tahun_anggaran DESC',
            ];

            // Set the order based on the mapping or use the default order if not found
            $order = $orderMappings[$getOrder] ?? $defaultOrder;
            // Validation rules for input parameters
            $validOrderValues = implode(',', array_keys($orderMappings));
            $rules = [
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',
                'order' => "in:$validOrderValues",
            ];

            $validator = Validator::make([
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
            ], $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }
            $produksiQuery = VerifikasiProduksiDetail::orderByRaw($order)
                ->select('produksi.id as id_produksi', 'produksi.triwulan', 'produksi.tahun_anggaran', 'regional.nama as nama_regional', 'kprk.id as id_kcu', 'kprk.nama as nama_kcu', 'kpc.id as id_kpc', 'kpc.nama as nama_kpc', DB::raw('SUM(produksi_detail.pelaporan) as total_produksi'))
                ->join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->join('regional', 'produksi.id_regional', '=', 'regional.id')
                ->join('kprk', 'produksi.id_kprk', '=', 'kprk.id')
                ->join('kpc', 'produksi.id_kpc', '=', 'kpc.id')
                ->groupBy('kpc.id', 'kprk.id', 'produksi.id_regional', 'produksi.triwulan', 'produksi.tahun_anggaran', 'regional.nama')
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $produksiQuery->where('kpc.nama', 'like', "%$search%");
            }
            if ($id_kcu !== '') {
                $produksiQuery->where('produksi.id_kprk', $id_kcu);
            }
            if ($tahun_anggaran !== '') {
                $produksiQuery->where('produksi.tahun_anggaran', $tahun_anggaran);
            }

            if ($triwulan !== '') {
                $produksiQuery->where('produksi.triwulan', $triwulan);
            }

            if ($status !== '') {
                // Anda perlu menyesuaikan kondisi WHERE ini sesuai dengan struktur tabel dan kondisi yang diinginkan.
                // Misalnya: $produksiQuery->where('status', $status);
            }
            $produksi = $produksiQuery->get();
            dd($produksi);

            // Mengubah format total_produksi menjadi format Rupiah
            foreach ($produksi as $item) {
                $item->total_produksi = "Rp " . number_format($item->total_produksi, 2, ',', '.');

                // AmbilVerifikasiProduksi dengan kriteria tertentu
                $getProduksi = VerifikasiProduksi::where('tahun_anggaran', $item->tahun_anggaran)
                    ->where('id_kprk', $item->id_kprk)
                    ->where('triwulan', $item->triwulan)
                    ->get();

                // Periksa apakah semua status dalam $getProduksi adalah 9
                $semuaStatusSembilan = $getProduksi->every(function ($produksi) {
                    return $produksi->id_status == 9;
                });

                // Jika semua status adalah 9, ambil status dari tabel Status
                if ($semuaStatusSembilan) {
                    $status = Status::where('id', 9)->first();
                    $item->status = $status->nama;
                } else {
                    $status = Status::where('id', 7)->first();
                    $item->status = $status->nama;
                }
            }

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'data' => $produksi,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getPerKPC(Request $request)
    {
        try {
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $getOrder = request()->get('order', '');
            $id_produksi = request()->get('id_produksi', '');
            $id_kcu = request()->get('id_kcu', '');
            $id_kpc = request()->get('id_kpc', '');
            $validator = Validator::make($request->all(), [
                'id_produksi' => 'required|string|exists:produksi,id',
                'id_kpc' => 'required|string|exists:kpc,id',
                'id_kcu' => 'required|numeric|exists:kprk,id',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }
            $defaultOrder = $getOrder ? $getOrder : "rekening_biaya.kode_rekening ASC";
            $orderMappings = [
                'koderekeningASC' => 'rekening_biaya.koderekening ASC',
                'koderekeningDESC' => 'rekening_biaya.koderekening DESC',
                'namaASC' => 'rekening_biaya.nama ASC',
                'namaDESC' => 'rekening_biaya.nama DESC',
            ];
            // dd($request->id_produksi);

            // Set the order based on the mapping or use the default order if not found
            $order = $orderMappings[$getOrder] ?? $defaultOrder;
            // Validation rules for input parameters
            $validOrderValues = implode(',', array_keys($orderMappings));
            $rules = [
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',
                'order' => "in:$validOrderValues",
            ];

            $validator = Validator::make([
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
            ], $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }
            $produksiQuery = VerifikasiProduksiDetail::orderByRaw($order)
                ->select(
                    // 'produksi.id as id_produksi',
                    'rekening_biaya.kode_rekening',
                    'rekening_biaya.nama as nama_rekening',
                    'produksi.triwulan',
                    'produksi.tahun_anggaran',
                    'produksi_detail.bulan',
                )
                ->join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->join('rekening_biaya', 'produksi_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
                ->where('produksi_detail.id_produksi', $request->id_produksi)
                ->where('produksi.id_kprk', $request->id_kcu)
                ->where('produksi.id_kpc', $request->id_kpc)
                ->groupBy('rekening_biaya.kode_rekening', 'produksi_detail.bulan')
                ->get();

            $groupedRutin = [];
            $laporanArray = [];
            foreach ($produksiQuery as $item) {
                $kodeRekening = $item->kode_rekening;
                $triwulan = $item->triwulan;

                // Jika kode_rekening belum ada dalam array groupedRutin, inisialisasikan dengan array kosong
                if (!isset($groupedRutin[$kodeRekening])) {
                    $groupedRutin[$kodeRekening] = [
                        // 'id_produksi' => $item->id_produksi,
                        'kode_rekening' => $kodeRekening,
                        'nama_rekening' => $item->nama_rekening,
                        'laporan' => $laporanArray, // Inisialisasi array laporan per kode rekening
                    ];
                }

                // Tentukan bulan-bulan berdasarkan triwulan
                $bulanAwalTriwulan = ($triwulan - 1) * 3 + 1;
                $bulanAkhirTriwulan = $bulanAwalTriwulan + 2;

                // Ubah format bulan dari angka menjadi nama bulan dalam bahasa Indonesia
                $bulanIndonesia = [
                    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
                ];

                // Bersihkan $laporanArray sebelum iterasi
                $laporanArray = [];

                for ($i = $bulanAwalTriwulan; $i <= $bulanAkhirTriwulan; $i++) {
                    // Ubah format bulan dari angka menjadi nama bulan dalam bahasa Indonesia
                    $bulanString = $bulanIndonesia[$i - 1];
                    $bulan = $i;
                    $getPelaporan = VerifikasiProduksiDetail::select(DB::raw('SUM(pelaporan) as total_pelaporan'),
                        DB::raw('SUM(verifikasi) as total_verifikasi'))
                        ->where('bulan', $bulan)
                        ->where('id_rekening_biaya', $kodeRekening)
                        ->where('id_produksi', $request->id_produksi)
                        ->get();

                    // Pastikan query menghasilkan data sebelum memprosesnya
                    if ($getPelaporan->isNotEmpty()) {
                        $pelaporan = 'Rp. ' . number_format($getPelaporan[0]->total_pelaporan, 2, ',', '.');
                        $verifikasi = 'Rp. ' . number_format($getPelaporan[0]->total_verifikasi, 2, ',', '.');
                    } else {
                        $pelaporan = 'Rp. 0,00';
                        $verifikasi = 'Rp. 0,00';
                    }

                    // Tambahkan data ke dalam array laporan
                    $laporanArray[] = [
                        'bulan_string' => $bulanString,
                        'bulan' => $bulan,
                        'pelaporan' => $pelaporan,
                        'verifikasi' => $verifikasi,
                    ];
                }

                // Tambahkan laporanArray ke dalam groupedRutin
                $groupedRutin[$kodeRekening]['laporan'] = $laporanArray;
            }
            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $order,
                'id_kcu' => $request->id_kcu,
                'id_kpc' => $request->id_kpc,
                'data' => array_values($groupedRutin),
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function notSimpling(Request $request)
    {
        try {
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $getOrder = request()->get('order', '');
            $id_produksi = request()->get('id_produksi', '');
            $id_kcu = request()->get('id_kcu', '');
            $id_kpc = request()->get('id_kpc', '');
            $status = 10;
            $validator = Validator::make($request->all(), [
                'id_produksi' => 'required|string|exists:produksi,id',
                'id_kpc' => 'required|string|exists:kpc,id',
                'id_kcu' => 'required|numeric|exists:kprk,id',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }
            $produksi = VerifikasiProduksi::where('id', $request->id_produksi)
                ->where('id_kprk', $request->id_kcu)
                ->where('id_kpc', $request->id_kpc)->first();
            $produksi->update([
                'id_status' => 10,
            ]);

            return response()->json(['status' => 'SUCCESS', 'data' => $produksi]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getDetail(Request $request)
    {

        try {

            $id_produksi = request()->get('id_produksi', '');
            $kode_rekening = request()->get('kode_rekening', '');
            $bulan = request()->get('bulan', '');
            $id_kcu = request()->get('id_kcu', '');
            $id_kpc = request()->get('id_kpc', '');
            $validator = Validator::make($request->all(), [
                'bulan' => 'required|numeric|max:12',
                'kode_rekening' => 'required|numeric|exists:rekening_biaya,id',
                'id_produksi' => 'required|string|exists:produksi,id',
                'id_kpc' => 'required|string|exists:kpc,id',
                'id_kcu' => 'required|string|exists:kprk,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }
            $bulanIndonesia = [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
            ];

            $produksi = VerifikasiProduksiDetail::select(
                'produksi_detail.id as id_produksi_detail',
                'rekening_biaya.kode_rekening',
                'rekening_biaya.nama as nama_rekening',
                'produksi.tahun_anggaran',
                DB::raw("CONCAT('" . $bulanIndonesia[$request->bulan - 1] . "') AS periode"),
                'produksi_detail.keterangan',
                'produksi_detail.lampiran',
                'produksi_detail.pelaporan',
                'produksi_detail.verifikasi',
                'produksi_detail.catatan_pemeriksa',
            )

                ->where('produksi_detail.id_produksi', $request->id_produksi)
                ->where('produksi_detail.id_rekening_biaya', $request->kode_rekening)
                ->where('produksi_detail.bulan', $request->bulan)
                ->where('produksi.id_kprk', $request->id_kcu)
                ->where('produksi.id_kpc', $request->id_kpc)
                ->join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->join('rekening_biaya', 'produksi_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
                ->join('kprk', 'produksi.id_kprk', '=', 'kprk.id')
                ->get();

            // dd($produksi);

            // Mengubah format total_produksi menjadi format Rupiah
            foreach ($produksi as $item) {
                $item->pelaporan = "Rp " . number_format($item->pelaporan, 2, ',', '.');
                $item->verifikasi = "Rp " . number_format($item->verifikasi, 2, ',', '.');
            }

            return response()->json([
                'status' => 'SUCCESS',
                'data' => $produksi,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verifikasi(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_produksi_detail' => 'required|numeric|exists:produksi_detail,id',
                'verifikasi' => 'required|string',
                'catatan_pemeriksa' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $id_produksi_detail = $request->id_produksi_detail;
            $verifikasi = $request->verifikasi;

            // Menghapus karakter "Rp." dan tanda "." dari string
            $verifikasi = str_replace(['Rp.', '.'], '', $verifikasi);
            // Mengganti tanda koma (",") dengan titik (".") untuk menyatakan bagian desimal
            $verifikasi = str_replace(',', '.', $verifikasi);
            // dd($verifikasi);

            // Mengubah string menjadi float
            $verifikasiFloat = (float) $verifikasi;

            // Format ulang float menjadi string dengan 2 angka desimal
            $verifikasiFormatted = number_format($verifikasiFloat, 2, '.', '');

            // Output: "462972.00"

            $catatan_pemeriksa = $request->input('catatan_pemeriksa');
            $id_validator = Auth::user()->id;
            $tanggal_verifikasi = now();

            $produksi_detail = VerifikasiProduksiDetail::find($id_produksi_detail);

            $produksi_detail->update([
                'verifikasi' => $verifikasiFormatted,
                'catatan_pemeriksa' => $catatan_pemeriksa,
                'id_validator' => $id_validator,
                'tgl_verifikasi' => $tanggal_verifikasi,
            ]);

            if ($produksi_detail) {
                $produksi = VerifikasiProduksiDetail::where('id_produksi', $produksi_detail->id_produksi)->get();
                $countValid = $produksi->filter(function ($detail) {
                    return $detail->verifikasi != 0.00 && $detail->tgl_verifikasi !== null;
                })->count();

                if ($countValid === $produksi->count()) {
                    VerifikasiProduksi::where('id', $id_produksi)->update(['id_status' => 9]);
                }
                return response()->json(['status' => 'SUCCESS', 'data' => $produksi_detail]);
            }

        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
