<?php

namespace App\Http\Controllers;

use App\Models\KabupatenKota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class KabupatenKotaController extends Controller
{
    public function index()
    {
        try {
            // Ambil parameter offset, limit, dan order dari permintaan
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 10);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');

            // Tentukan aturan urutan default dan pemetaan urutan
            $defaultOrder = $getOrder ? $getOrder : "kabupaten_kota.id ASC";
            $orderMappings = [
                'idASC' => 'kabupaten_kota.id ASC',
                'idDESC' => 'kabupaten_kota.id DESC',
                'namaprovinsiASC' => 'provinsi.nama ASC',
                'namaprovinsiDESC' => 'provinsi.nama DESC',
                'namakabupatenASC' => 'kabupaten_kota.nama ASC',
                'namakabupatenDESC' => 'kabupaten_kota.nama DESC',
            ];

            // Setel urutan berdasarkan pemetaan atau gunakan urutan default jika tidak ditemukan
            $order = $orderMappings[$getOrder] ?? $defaultOrder;

            // Validasi aturan untuk parameter masukan
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

            // Query kabupaten/kota with search condition if search keyword is provided
            $kabupatenKotasQuery = KabupatenKota::leftJoin('provinsi', 'kabupaten_kota.id_provinsi', '=', 'provinsi.id')
                ->select('kabupaten_kota.*', 'provinsi.nama as nama_provinsi')
                ->orderByRaw($order)
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $kabupatenKotasQuery->where(function ($query) use ($search) {
                    $query->where('kabupaten_kota.nama', 'like', "%$search%")
                        ->orWhere('provinsi.nama', 'like', "%$search%");
                });
            }

            $kabupatenKotas = $kabupatenKotasQuery->get();

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'data' => $kabupatenKotas,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            $kabupatenKota = KabupatenKota::leftJoin('provinsi', 'kabupaten_kota.id_provinsi', '=', 'provinsi.id')
                ->select('kabupaten_kota.*', 'provinsi.nama as nama_provinsi')
                ->where('kabupaten_kota.id', $id)->first();

            return response()->json(['status' => 'SUCCESS', 'data' => $kabupatenKota]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_provinsi' => 'required|exists:provinsi,id',
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kabupatenKota = KabupatenKota::create($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $kabupatenKota], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_provinsi' => 'required|exists:provinsi,id',
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kabupatenKota = KabupatenKota::where('id', $id)->first();
            $kabupatenKota->update($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $kabupatenKota]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        try {
            $kabupatenKota = KabupatenKota::where('id', $id)->first();
            $kabupatenKota->delete();
            return response()->json(['status' => 'SUCCESS', 'message' => 'Kabupaten/Kota deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function syncKabupaten()
    {
        try {
            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            // Mendefinisikan endpoint untuk sinkronisasi kabupaten/kota
            $endpoint = 'kota_kab';

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint kabupaten/kota
            $response = $apiController->makeRequest($request);

            // Mengambil data kabupaten/kota dari respons
            $dataKabupatenKota = $response['data'];

            // Memproses setiap data kabupaten/kota dari respons
            foreach ($dataKabupatenKota as $data) {
                // Mencari kabupaten/kota berdasarkan ID
                $kabupatenKota = KabupatenKota::find($data['kode_kota_kab']);

                // Jika kabupaten/kota ditemukan, perbarui data
                if ($kabupatenKota) {
                    $kabupatenKota->update([
                        'nama' => $data['nama_kota_kab'],
                        'id_provinsi' => $data['kode_provinsi'],
                        // Perbarui atribut lain yang diperlukan
                    ]);
                } else {
                    // Jika kabupaten/kota tidak ditemukan, tambahkan data baru
                    KabupatenKota::create([
                        'id' => $data['kode_kota_kab'],
                        'nama' => $data['nama_kota_kab'],
                        'id_provinsi' => $data['kode_provinsi'],
                        // Tambahkan atribut lain yang diperlukan
                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi kabupaten/kota berhasil',
            ], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

}
