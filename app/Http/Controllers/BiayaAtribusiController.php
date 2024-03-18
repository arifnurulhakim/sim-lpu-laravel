<?php

namespace App\Http\Controllers;

use App\Models\BiayaAtribusi;
use App\Models\BiayaAtribusiDetail;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class BiayaAtribusiController extends Controller
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
                'triwulanASC' => 'biaya_atribusi.triwulan ASC',
                'triwulanDESC' => 'biaya_atribusi.triwulan DESC',
                'tahunASC' => 'biaya_atribusi.tahun_anggaran ASC',
                'tahunDESC' => 'biaya_atribusi.tahun_anggaran DESC',
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

            $atribusiQuery = BiayaAtribusi::orderByRaw($order)
                ->select('biaya_atribusi.id_regional', 'biaya_atribusi.triwulan', 'biaya_atribusi.tahun_anggaran', 'regional.nama as nama_regional', DB::raw('SUM(biaya_atribusi.total_biaya) as total_biaya'))
                ->join('regional', 'biaya_atribusi.id_regional', '=', 'regional.id')
                ->groupBy('biaya_atribusi.id_regional', 'biaya_atribusi.triwulan', 'biaya_atribusi.tahun_anggaran')
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $atribusiQuery->where('nama_regional', 'like', "%$search%");
            }

            // Menambahkan kondisi WHERE berdasarkan variabel $tahun, $triwulan, dan $status
            if ($tahun !== '') {
                $atribusiQuery->where('biaya_atribusi.tahun_anggaran', $tahun);
            }

            if ($triwulan !== '') {
                $atribusiQuery->where('biaya_atribusi.triwulan', $triwulan);
            }
            if ($status !== '') {
                $atribusiQuery->where('biaya_atribusi.id_status', $status);
            }

            $atribusi = $atribusiQuery->get();

            $grand_total = $atribusi->sum('total_biaya');
            $grand_total = "Rp " . number_format($grand_total, 2, ',', '.');
            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($atribusi as $item) {
                $item->total_biaya = "Rp " . number_format($item->total_biaya, 2, ',', '.');

                // Ambil BiayaAtribusi dengan kriteria tertentu
                $getBiayaAtribusi = BiayaAtribusi::where('tahun_anggaran', $item->tahun_anggaran)
                    ->where('id_regional', $item->id_regional)
                    ->where('triwulan', $item->triwulan)
                    ->get();

                // Periksa apakah semua status dalam $getBiayaAtribusi adalah 9
                $semuaStatusSembilan = $getBiayaAtribusi->every(function ($biayaAtribusi) {
                    return $biayaAtribusi->id_status == 9;
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
                'data' => $atribusi,
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
                'triwulanASC' => 'biaya_atribusi.triwulan ASC',
                'triwulanDESC' => 'biaya_atribusi.triwulan DESC',
                'tahunASC' => 'biaya_atribusi.tahun_anggaran ASC',
                'tahunDESC' => 'biaya_atribusi.tahun_anggaran DESC',
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
            $atribusiQuery = BiayaAtribusiDetail::orderByRaw($order)
                ->select('biaya_atribusi.id', 'biaya_atribusi.triwulan', 'biaya_atribusi.tahun_anggaran', 'regional.nama as nama_regional', 'kprk.id as id_kcu', 'kprk.nama as nama_kcu', DB::raw('SUM(biaya_atribusi_detail.pelaporan) as total_biaya'))
                ->join('biaya_atribusi', 'biaya_atribusi_detail.id_biaya_atribusi', '=', 'biaya_atribusi.id')
                ->join('kprk', 'biaya_atribusi.id_kprk', '=', 'kprk.id')
                ->join('regional', 'biaya_atribusi.id_regional', '=', 'regional.id')
                ->groupBy('kprk.id', 'biaya_atribusi.id_regional', 'biaya_atribusi.triwulan', 'biaya_atribusi.tahun_anggaran', 'regional.nama')
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $atribusiQuery->where('kprk.nama', 'like', "%$search%");
            }
            if ($id_regional !== '') {
                $atribusiQuery->where('biaya_atribusi.id_regional', $id_regional);
            }
            if ($tahun !== '') {
                $atribusiQuery->where('biaya_atribusi.tahun_anggaran', $tahun);
            }

            if ($triwulan !== '') {
                $atribusiQuery->where('biaya_atribusi.triwulan', $triwulan);
            }

            if ($status !== '') {
                $atribusiQuery->where('biaya_atribusi.id_status', $status);
            }

            $atribusi = $atribusiQuery->get();

            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($atribusi as $item) {
                $item->total_biaya = "Rp " . number_format($item->total_biaya, 2, ',', '.');

                // Ambil BiayaAtribusi dengan kriteria tertentu
                $getBiayaAtribusi = BiayaAtribusi::where('tahun_anggaran', $item->tahun_anggaran)
                    ->where('id_regional', $item->id_regional)
                    ->where('triwulan', $item->triwulan)
                    ->get();

                // Periksa apakah semua status dalam $getBiayaAtribusi adalah 9
                $semuaStatusSembilan = $getBiayaAtribusi->every(function ($biayaAtribusi) {
                    return $biayaAtribusi->id_status == 9;
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
                'data' => $atribusi,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getPerKCU(Request $request)
    {
        try {
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $getOrder = request()->get('order', '');
            $id_biaya_atribusi = request()->get('id_biaya_atribusi', '');
            $id_kcu = request()->get('id_kcu', '');
            $validator = Validator::make($request->all(), [
                'id_biaya_atribusi' => 'nullable|numeric|exists:biaya_atribusi,id',
                'id_kcu' => 'nullable|numeric|exists:kprk,id',
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
            // dd($request->id_biaya_atribusi);

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
            $atribusiQuery = BiayaAtribusiDetail::orderByRaw($order)
                ->select(
                    'rekening_biaya.kode_rekening',
                    'rekening_biaya.nama as nama_rekening',
                    'biaya_atribusi.triwulan',
                    'biaya_atribusi.tahun_anggaran',
                    'biaya_atribusi_detail.bulan',
                )
                ->join('biaya_atribusi', 'biaya_atribusi_detail.id_biaya_atribusi', '=', 'biaya_atribusi.id')
                ->join('rekening_biaya', 'biaya_atribusi_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
                ->where('biaya_atribusi_detail.id_biaya_atribusi', $request->id_biaya_atribusi)
                ->where('biaya_atribusi.id_kprk', $request->id_kcu)
                ->groupBy('rekening_biaya.kode_rekening', 'biaya_atribusi_detail.bulan')
                ->get();

            $groupedAtribusi = [];
            $laporanArray = [];
            foreach ($atribusiQuery as $item) {
                $kodeRekening = $item->kode_rekening;
                $triwulan = $item->triwulan;

                // Jika kode_rekening belum ada dalam array groupedAtribusi, inisialisasikan dengan array kosong
                if (!isset($groupedAtribusi[$kodeRekening])) {
                    $groupedAtribusi[$kodeRekening] = [
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
                    $getPelaporan = BiayaAtribusiDetail::select(DB::raw('SUM(pelaporan) as total_pelaporan'),
                        DB::raw('SUM(verifikasi) as total_verifikasi'))
                        ->where('bulan', $bulan)
                        ->where('id_rekening_biaya', $kodeRekening)
                        ->where('id_biaya_atribusi', $request->id_biaya_atribusi)
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

                // Tambahkan laporanArray ke dalam groupedAtribusi
                $groupedAtribusi[$kodeRekening]['laporan'] = $laporanArray;
            }
            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $order,
                'id_biaya_atribusi' => $request->id_biaya_atribusi,
                'id_kcu' => $request->id_kcu,
                'data' => array_values($groupedAtribusi),
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getDetail(Request $request)
    {
        // href="/backend/verifikasi_biaya_atribusi_detail/update?a=2270020231&b=5102050004&c=1&d=2023&e=01"

        // href="/backend/verifikasi_biaya_atribusi_detail/update?a=2270020231&b=5102050004&c=1&d=2023&e=02"
        try {

            $id_biaya_atribusi = request()->get('id_biaya_atribusi', '');
            $kode_rekening = request()->get('kode_rekening', '');
            $bulan = request()->get('bulan', '');
            $id_kcu = request()->get('id_kcu', '');
            $validator = Validator::make($request->all(), [
                'bulan' => 'required|numeric|max:12',
                'kode_rekening' => 'required|numeric|exists:rekening_biaya,id',
                'id_biaya_atribusi' => 'required|numeric|exists:biaya_atribusi,id',
                'id_kcu' => 'required|numeric|exists:kprk,id',
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

            $atribusi = BiayaAtribusiDetail::select(
                'biaya_atribusi_detail.id as id_biaya_atribusi_detail',
                'rekening_biaya.kode_rekening',
                'rekening_biaya.nama as nama_rekening',
                'biaya_atribusi.tahun_anggaran',
                DB::raw("CONCAT('" . $bulanIndonesia[$request->bulan - 1] . "') AS periode"),
                'biaya_atribusi_detail.keterangan',
                'biaya_atribusi_detail.lampiran',
                'biaya_atribusi_detail.pelaporan',
                'biaya_atribusi_detail.verifikasi',
                'biaya_atribusi_detail.catatan_pemeriksa',
                'kprk.nama as nama_kcu',
                'kprk.jumlah_kpc_lpu',
                'kprk.jumlah_kpc_lpk',
            )

                ->where('biaya_atribusi_detail.id_biaya_atribusi', $request->id_biaya_atribusi)
                ->where('biaya_atribusi_detail.id_rekening_biaya', $request->kode_rekening)
                ->where('biaya_atribusi_detail.bulan', $request->bulan)
                ->where('biaya_atribusi.id_kprk', $request->id_kcu)
                ->join('biaya_atribusi', 'biaya_atribusi_detail.id_biaya_atribusi', '=', 'biaya_atribusi.id')
                ->join('rekening_biaya', 'biaya_atribusi_detail.id_rekening_biaya', '=', 'rekening_biaya.id')
                ->join('kprk', 'biaya_atribusi.id_kprk', '=', 'kprk.id')
                ->get();

            // dd($atribusi);

            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($atribusi as $item) {
                $item->pelaporan = "Rp " . number_format($item->pelaporan, 2, ',', '.');
                $item->verifikasi = "Rp " . number_format($item->verifikasi, 2, ',', '.');
            }

            return response()->json([
                'status' => 'SUCCESS',
                'data' => $atribusi,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verifikasi(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'data.*.id_biaya_atribusi_detail' => 'required|string|exists:biaya_atribusi_detail,id',
                'data.*.verifikasi' => 'required|string',
                'data.*.catatan_pemeriksa' => 'string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $verifikasiData = $request->input('data');
            $updatedData = [];

            foreach ($verifikasiData as $data) {
                if (!isset($data['id_biaya_atribusi_detail']) || !isset($data['verifikasi'])) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Invalid data structure'], Response::HTTP_BAD_REQUEST);
                }

                $id_biaya_atribusi_detail = $data['id_biaya_atribusi_detail'];
                $verifikasi = str_replace(['Rp.', ',', '.'], '', $data['verifikasi']);
                $verifikasiFloat = (float) $verifikasi;
                $verifikasiFormatted = number_format($verifikasiFloat, 2, '.', '');
                $catatan_pemeriksa = isset($data['catatan_pemeriksa']) ? $data['catatan_pemeriksa'] : '';
                $id_validator = Auth::user()->id;
                $tanggal_verifikasi = now();

                $biaya_atribusi_detail = BiayaAtribusiDetail::find($id_biaya_atribusi_detail);

                if (!$biaya_atribusi_detail) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Detail biaya atribusi tidak ditemukan'], Response::HTTP_NOT_FOUND);
                }

                $biaya_atribusi_detail->update([
                    'verifikasi' => $verifikasiFormatted,
                    'catatan_pemeriksa' => $catatan_pemeriksa,
                    'id_validator' => $id_validator,
                    'tgl_verifikasi' => $tanggal_verifikasi,
                ]);

                $updatedData[] = $biaya_atribusi_detail; // Menambahkan data yang diperbarui ke dalam array
            }

            return response()->json(['status' => 'SUCCESS', 'data' => $updatedData]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
