<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Models\VerifikasiBiayaRutin;
use App\Models\VerifikasiBiayaRutinDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class VerifikasiBiayaRutinController extends Controller
{
    public function getPerTahun(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric', // Menyatakan bahwa tahun bersifat opsional dan harus berupa angka
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
            $tahun = request()->get('tahun', '');
            $triwulan = request()->get('triwulan', '');
            $status = request()->get('status', '');

            $defaultOrder = $getOrder ? $getOrder : "nama ASC";
            $orderMappings = [
                'namaASC' => 'regional.nama ASC',
                'namaDESC' => 'regional.nama DESC',
                'triwulanASC' => 'verifikasi_biaya_rutin.triwulan ASC',
                'triwulanDESC' => 'verifikasi_biaya_rutin.triwulan DESC',
                'tahunASC' => 'verifikasi_biaya_rutin.tahun ASC',
                'tahunDESC' => 'verifikasi_biaya_rutin.tahun DESC',
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

            $rutinQuery = VerifikasiBiayaRutin::orderByRaw($order)
                ->select('verifikasi_biaya_rutin.id_regional', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun', 'regional.nama as nama_regional', DB::raw('SUM(verifikasi_biaya_rutin.total_biaya) as total_biaya'))
                ->join('regional', 'verifikasi_biaya_rutin.id_regional', '=', 'regional.id')
                ->groupBy('verifikasi_biaya_rutin.id_regional', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun')
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $rutinQuery->where('nama_regional', 'like', "%$search%");
            }

            // Menambahkan kondisi WHERE berdasarkan variabel $tahun, $triwulan, dan $status
            if ($tahun !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.tahun', $tahun);
            }

            if ($triwulan !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.triwulan', $triwulan);
            }
            if ($status !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.id_status', $status);
            }

            $rutin = $rutinQuery->get();

            $grand_total = $rutin->sum('total_biaya');
            $grand_total = "Rp " . number_format($grand_total, 2, ',', '.');
            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($rutin as $item) {
                $item->total_biaya = "Rp " . number_format($item->total_biaya, 2, ',', '.');

                // Ambil VerifikasiBiayaRutin dengan kriteria tertentu
                $getBiayaRutin = VerifikasiBiayaRutin::where('tahun', $item->tahun)
                    ->where('id_regional', $item->id_regional)
                    ->where('triwulan', $item->triwulan)
                    ->get();

                // Periksa apakah semua status dalam $getBiayaRutin adalah 9
                $semuaStatusSembilan = $getBiayaRutin->every(function ($biayaRutin) {
                    return $biayaRutin->id_status == 9;
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
                'data' => $rutin,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function getPerRegional(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
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
            $tahun = request()->get('tahun', '');
            $triwulan = request()->get('triwulan', '');
            $status = request()->get('status', '');
            $defaultOrder = $getOrder ? $getOrder : "kprk.id ASC";
            $orderMappings = [
                'namaASC' => 'kprk.nama ASC',
                'namaDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'verifikasi_biaya_rutin.triwulan ASC',
                'triwulanDESC' => 'verifikasi_biaya_rutin.triwulan DESC',
                'tahunASC' => 'verifikasi_biaya_rutin.tahun ASC',
                'tahunDESC' => 'verifikasi_biaya_rutin.tahun DESC',
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
            $rutinQuery = VerifikasiBiayaRutinDetail::orderByRaw($order)
                ->select('verifikasi_biaya_rutin.id', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun', 'regional.nama as nama_regional', 'kprk.id as id_kcu', 'kprk.nama as nama_kcu', DB::raw('SUM(verifikasi_biaya_rutin_detail.pelaporan) as total_biaya'))
                ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
                ->join('kprk', 'verifikasi_biaya_rutin.id_kprk', '=', 'kprk.id')
                ->join('regional', 'verifikasi_biaya_rutin.id_regional', '=', 'regional.id')
                ->groupBy('kprk.id', 'verifikasi_biaya_rutin.id_regional', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun', 'regional.nama')
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $rutinQuery->where('kprk.nama', 'like', "%$search%");
            }
            if ($id_regional !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.id_regional', $id_regional);
            }
            if ($tahun !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.tahun', $tahun);
            }

            if ($triwulan !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.triwulan', $triwulan);
            }

            if ($status !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.id_status', $status);
            }

            $rutin = $rutinQuery->get();

            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($rutin as $item) {
                $item->total_biaya = "Rp " . number_format($item->total_biaya, 2, ',', '.');

                // Ambil VerifikasiBiayaRutin dengan kriteria tertentu
                $getBiayaRutin = VerifikasiBiayaRutin::where('tahun', $item->tahun)
                    ->where('id_regional', $item->id_regional)
                    ->where('triwulan', $item->triwulan)
                    ->get();

                // Periksa apakah semua status dalam $getBiayaRutin adalah 9
                $semuaStatusSembilan = $getBiayaRutin->every(function ($biayaRutin) {
                    return $biayaRutin->id_status == 9;
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
                'data' => $rutin,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function getPerKCU(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
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
            $tahun = request()->get('tahun', '');
            $triwulan = request()->get('triwulan', '');
            $status = request()->get('status', '');
            $defaultOrder = $getOrder ? $getOrder : "kpc.id ASC";
            $orderMappings = [
                'namakpcASC' => 'kpc.nama ASC',
                'namakpcDESC' => 'kpc.nama DESC',
                'namakcuASC' => 'kprk.nama ASC',
                'namakcuDESC' => 'kprk.nama DESC',
                'triwulanASC' => 'verifikasi_biaya_rutin.triwulan ASC',
                'triwulanDESC' => 'verifikasi_biaya_rutin.triwulan DESC',
                'tahunASC' => 'verifikasi_biaya_rutin.tahun ASC',
                'tahunDESC' => 'verifikasi_biaya_rutin.tahun DESC',
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
            $rutinQuery = VerifikasiBiayaRutinDetail::orderByRaw($order)
                ->select('verifikasi_biaya_rutin.id as id_verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun', 'regional.nama as nama_regional', 'kprk.id as id_kcu', 'kprk.nama as nama_kcu', 'kpc.id as id_kpc', 'kpc.nama as nama_kpc', DB::raw('SUM(verifikasi_biaya_rutin_detail.pelaporan) as total_biaya'))
                ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
                ->join('regional', 'verifikasi_biaya_rutin.id_regional', '=', 'regional.id')
                ->join('kprk', 'verifikasi_biaya_rutin.id_kprk', '=', 'kprk.id')
                ->join('kpc', 'verifikasi_biaya_rutin.id_kpc', '=', 'kpc.id')
                ->groupBy('kpc.id', 'kprk.id', 'verifikasi_biaya_rutin.id_regional', 'verifikasi_biaya_rutin.triwulan', 'verifikasi_biaya_rutin.tahun', 'regional.nama')
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $rutinQuery->where('kpc.nama', 'like', "%$search%");
            }
            if ($id_kcu !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.id_kprk', $id_kcu);
            }
            if ($tahun !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.tahun', $tahun);
            }

            if ($triwulan !== '') {
                $rutinQuery->where('verifikasi_biaya_rutin.triwulan', $triwulan);
            }

            if ($status !== '') {
                // Anda perlu menyesuaikan kondisi WHERE ini sesuai dengan struktur tabel dan kondisi yang diinginkan.
                // Misalnya: $rutinQuery->where('status', $status);
            }
            $rutin = $rutinQuery->get();

            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($rutin as $item) {
                $item->total_biaya = "Rp " . number_format($item->total_biaya, 2, ',', '.');

                // Ambil VerifikasiBiayaRutin dengan kriteria tertentu
                $getBiayaRutin = VerifikasiBiayaRutin::where('tahun', $item->tahun)
                    ->where('id_kprk', $item->id_kprk)
                    ->where('triwulan', $item->triwulan)
                    ->get();

                // Periksa apakah semua status dalam $getBiayaRutin adalah 9
                $semuaStatusSembilan = $getBiayaRutin->every(function ($biayaRutin) {
                    return $biayaRutin->id_status == 9;
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
                'data' => $rutin,
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
            $id_verifikasi_biaya_rutin = request()->get('id_verifikasi_biaya_rutin', '');
            $id_kcu = request()->get('id_kcu', '');
            $id_kpc = request()->get('id_kpc', '');
            $validator = Validator::make($request->all(), [
                'id_verifikasi_biaya_rutin' => 'required|string|exists:verifikasi_biaya_rutin,id',
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
            // dd($request->id_verifikasi_biaya_rutin);

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
            $rutinQuery = VerifikasiBiayaRutinDetail::orderByRaw($order)
                ->select(
                    // 'verifikasi_biaya_rutin.id as id_verifikasi_biaya_rutin',
                    'rekening_biaya.kode_rekening',
                    'rekening_biaya.nama as nama_rekening',
                    'verifikasi_biaya_rutin.triwulan',
                    'verifikasi_biaya_rutin.tahun',
                    'verifikasi_biaya_rutin_detail.bulan',
                )
                ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
                ->join('rekening_biaya', 'verifikasi_biaya_rutin_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
                ->where('verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', $request->id_verifikasi_biaya_rutin)
                ->where('verifikasi_biaya_rutin.id_kprk', $request->id_kcu)
                ->where('verifikasi_biaya_rutin.id_kpc', $request->id_kpc)
                ->groupBy('rekening_biaya.kode_rekening', 'verifikasi_biaya_rutin_detail.bulan')
                ->get();

            $groupedRutin = [];
            $laporanArray = [];
            foreach ($rutinQuery as $item) {
                $kodeRekening = $item->kode_rekening;
                $triwulan = $item->triwulan;

                // Jika kode_rekening belum ada dalam array groupedRutin, inisialisasikan dengan array kosong
                if (!isset($groupedRutin[$kodeRekening])) {
                    $groupedRutin[$kodeRekening] = [
                        // 'id_verifikasi_biaya_rutin' => $item->id_verifikasi_biaya_rutin,
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
                    $getPelaporan = VerifikasiBiayaRutinDetail::select(DB::raw('SUM(pelaporan) as total_pelaporan'),
                        DB::raw('SUM(verifikasi) as total_verifikasi'))
                        ->where('bulan', $bulan)
                        ->where('id_rekening_biaya', $kodeRekening)
                        ->where('id_verifikasi_biaya_rutin', $request->id_verifikasi_biaya_rutin)
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
            $id_verifikasi_biaya_rutin = request()->get('id_verifikasi_biaya_rutin', '');
            $id_kcu = request()->get('id_kcu', '');
            $id_kpc = request()->get('id_kpc', '');
            $status = 10;
            $validator = Validator::make($request->all(), [
                'id_verifikasi_biaya_rutin' => 'required|string|exists:verifikasi_biaya_rutin,id',
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
            $rutin = VerifikasiBiayaRutin::where('id', $request->id_verifikasi_biaya_rutin)
                ->where('id_kprk', $request->id_kcu)
                ->where('id_kpc', $request->id_kpc)->first();
            $rutin->update([
                'id_status' => 10,
            ]);

            return response()->json(['status' => 'SUCCESS', 'data' => $rutin]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getDetail(Request $request)
    {

        try {

            $id_verifikasi_biaya_rutin = request()->get('id_verifikasi_biaya_rutin', '');
            $kode_rekening = request()->get('kode_rekening', '');
            $bulan = str_pad(request()->get('bulan', ''), 2, '0', STR_PAD_LEFT);
            $id_kcu = request()->get('id_kcu', '');
            $id_kpc = request()->get('id_kpc', '');
            $validator = Validator::make($request->all(), [
                'bulan' => 'required|numeric|max:12',
                'kode_rekening' => 'required|numeric|exists:rekening_biaya,id',
                'id_verifikasi_biaya_rutin' => 'required|string|exists:verifikasi_biaya_rutin,id',
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

            $rutin = VerifikasiBiayaRutinDetail::select(
                'verifikasi_biaya_rutin_detail.id as id_verifikasi_biaya_rutin_detail',
                'rekening_biaya.kode_rekening',
                'rekening_biaya.nama as nama_rekening',
                'verifikasi_biaya_rutin.tahun',
                DB::raw("CONCAT('" . $bulanIndonesia[$bulan - 1] . "') AS periode"),
                'verifikasi_biaya_rutin_detail.keterangan',
                'verifikasi_biaya_rutin_detail.lampiran',
                'verifikasi_biaya_rutin_detail.pelaporan',
                'verifikasi_biaya_rutin_detail.verifikasi',
                'verifikasi_biaya_rutin_detail.catatan_pemeriksa',
            )

                ->where('verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', $request->id_verifikasi_biaya_rutin)
                ->where('verifikasi_biaya_rutin_detail.id_rekening_biaya', $request->kode_rekening)
                ->where('verifikasi_biaya_rutin_detail.bulan', $bulan)
                ->where('verifikasi_biaya_rutin.id_kprk', $request->id_kcu)
                ->where('verifikasi_biaya_rutin.id_kpc', $request->id_kpc)
                ->join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
                ->join('rekening_biaya', 'verifikasi_biaya_rutin_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
                ->join('kprk', 'verifikasi_biaya_rutin.id_kprk', '=', 'kprk.id')
                ->get();

            // dd($rutin);

            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($rutin as $item) {
                $item->pelaporan = "Rp " . number_format($item->pelaporan, 2, ',', '.');
                $item->verifikasi = "Rp " . number_format($item->verifikasi, 2, ',', '.');
            }

            return response()->json([
                'status' => 'SUCCESS',
                'data' => $rutin,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verifikasi(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'data.*.id_verifikasi_biaya_rutin_detail' => 'required|string|exists:verifikasi_biaya_rutin_detail,id',
                'data.*.verifikasi' => 'required|string',
                'data.*.catatan_pemeriksa' => 'string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $verifikasiData = $request->input('data');
            $updatedData = [];

            foreach ($verifikasiData as $data) {
                if (!isset($data['id_verifikasi_biaya_rutin_detail']) || !isset($data['verifikasi'])) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Invalid data structure'], Response::HTTP_BAD_REQUEST);
                }

                $id_verifikasi_biaya_rutin_detail = $data['id_verifikasi_biaya_rutin_detail'];
                $verifikasi = str_replace(['Rp.', ',', '.'], '', $data['verifikasi']);
                $verifikasiFloat = (float) $verifikasi;
                $verifikasiFormatted = number_format($verifikasiFloat, 2, '.', '');
                $catatan_pemeriksa = isset($data['catatan_pemeriksa']) ? $data['catatan_pemeriksa'] : '';
                $id_validator = Auth::user()->id;
                $tanggal_verifikasi = now();

                $biaya_rutin_detail = VerifikasiBiayaRutinDetail::find($id_verifikasi_biaya_rutin_detail);

                if (!$biaya_rutin_detail) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Detail biaya rutin tidak ditemukan'], Response::HTTP_NOT_FOUND);
                }

                $biaya_rutin_detail->update([
                    'verifikasi' => $verifikasiFormatted,
                    'catatan_pemeriksa' => $catatan_pemeriksa,
                    'id_validator' => $id_validator,
                    'tgl_verifikasi' => $tanggal_verifikasi,
                ]);

                $updatedData[] = $biaya_rutin_detail; // Menambahkan data yang diperbarui ke dalam array
            }

            return response()->json(['status' => 'SUCCESS', 'data' => $updatedData]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
