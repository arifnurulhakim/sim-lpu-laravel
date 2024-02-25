<?php

namespace App\Http\Controllers;

use App\Models\BiayaAtribusi;
use App\Models\BiayaAtribusiDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class BiayaAtribusiController extends Controller
{
    public function index()
    {
        try {
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 10);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $defaultOrder = $getOrder ? $getOrder : "id ASC";
            $orderMappings = [
                'idASC' => 'id ASC',
                'idDESC' => 'id DESC',
                'namaASC' => 'nama ASC',
                'namaDESC' => 'nama DESC',
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
            $provinsisQuery = Provinsi::orderByRaw($order)
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $provinsisQuery->where('nama', 'like', "%$search%");
            }

            $provinsis = $provinsisQuery->get();
            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'data' => $provinsis,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function getPerTahun(Request $request)
    {
        try {
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $tahun = request()->get('tahun', '');
            $triwulan = request()->get('triwulan', '');
            $status = request()->get('status', '');

            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric', // Menyatakan bahwa tahun bersifat opsional dan harus berupa angka
                'triwulan' => 'nullable|numeric|in:1,2,3,4', // Menyatakan bahwa triwulan bersifat opsional, harus berupa angka, dan nilainya hanya boleh 1, 2, 3, atau 4
                'status' => 'nullable|string|in:7,9', // Menyatakan bahwa status bersifat opsional, harus berupa string, dan nilainya hanya boleh "aktif" atau "nonaktif"
            ]);

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
                // Anda perlu menyesuaikan kondisi WHERE ini sesuai dengan struktur tabel dan kondisi yang diinginkan.
                // Misalnya: $atribusiQuery->where('status', $status);
            }

            $atribusi = $atribusiQuery->get();

            $grand_total = $atribusi->sum('total_biaya');
            $grand_total = "Rp " . number_format($grand_total, 2, ',', '.');
            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($atribusi as $item) {
                $item->total_biaya = "Rp " . number_format($item->total_biaya, 2, ',', '.');
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
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $id_regional = request()->get('id_regional', '');
            $tahun = request()->get('tahun', '');
            $triwulan = request()->get('triwulan', '');
            $status = request()->get('status', '');
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string',
                'tahun' => 'nullable|numeric',
                'triwulan' => 'nullable|numeric|in:1,2,3,4',
                'id_regional' => 'nullable|numeric|exists:regional,id',
                'status' => 'nullable|string|in:7,9',
            ]);
            $defaultOrder = $getOrder ? $getOrder : "kprk.nama ASC";
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
                ->select('biaya_atribusi.triwulan', 'biaya_atribusi.tahun_anggaran', 'regional.nama as nama_regional', 'kprk.nama as nama_kcu', DB::raw('SUM(biaya_atribusi_detail.pelaporan) as total_biaya'))
                ->join('biaya_atribusi', 'biaya_atribusi_detail.id_biaya_atribusi', '=', 'biaya_atribusi.id')
                ->join('kprk', 'biaya_atribusi.id_kprk', '=', 'kprk.id')
                ->join('regional', 'biaya_atribusi.id_regional', '=', 'regional.id')
                ->groupBy('kprk.id', 'biaya_atribusi.id_regional', 'biaya_atribusi.triwulan', 'biaya_atribusi.tahun_anggaran')
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
                // Anda perlu menyesuaikan kondisi WHERE ini sesuai dengan struktur tabel dan kondisi yang diinginkan.
                // Misalnya: $atribusiQuery->where('status', $status);
            }
            $atribusi = $atribusiQuery->get();

            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($atribusi as $item) {
                $item->total_biaya = "Rp " . number_format($item->total_biaya, 0, ',', '.');
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

    public function show($id)
    {
        try {
            $provinsi = Provinsi::where('id', $id)->first();
            return response()->json(['status' => 'SUCCESS', 'data' => $provinsi]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $provinsi = Provinsi::create($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $provinsi], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $provinsi = Provinsi::where('id', $id)->first();
            $provinsi->update($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $provinsi]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        try {
            $provinsi = Provinsi::where('id', $id)->first();
            $provinsi->delete();
            return response()->json(['status' => 'SUCCESS', 'message' => 'Provinsi deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function syncProvinsi()
    {
        try {
            // Mendefinisikan endpoint untuk sinkronisasi provinsi
            $endpoint = 'provinsi';

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            // Mengambil data provinsi dari respons
            $dataProvinsi = $response['data'];
            if (!$dataProvinsi) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            // Memproses setiap data provinsi dari respons
            foreach ($dataProvinsi as $data) {
                // Mencari provinsi berdasarkan ID
                $provinsi = Provinsi::find($data['kode_provinsi']);

                // Jika provinsi ditemukan, perbarui data
                if ($provinsi) {
                    $provinsi->update([
                        'nama' => $data['nama_provinsi'],
                        // Perbarui atribut lain yang diperlukan
                    ]);
                } else {
                    // Jika provinsi tidak ditemukan, tambahkan data baru
                    Provinsi::create([
                        'id' => $data['kode_provinsi'],
                        'nama' => $data['nama_provinsi'],
                        // Tambahkan atribut lain yang diperlukan
                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi provinsi berhasil'], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
}
