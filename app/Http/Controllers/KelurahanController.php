<?php

namespace App\Http\Controllers;

use App\Models\Kelurahan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class KelurahanController extends Controller
{
    public function index()
    {
        try {
            // Ambil parameter offset, limit, search, dan order dari permintaan
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 10);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');

            // Tentukan aturan urutan default dan pemetaan urutan
            $defaultOrder = $getOrder ? $getOrder : "kelurahan.id ASC";
            $orderMappings = [
                'idASC' => 'kelurahan.id ASC',
                'idDESC' => 'kelurahan.id DESC',
                'namakelurahanASC' => 'kelurahan.nama ASC',
                'namakelurahanDESC' => 'kelurahan.nama DESC',
                'namakecamatanASC' => 'kecamatan.nama ASC',
                'namakecamatanDESC' => 'kecamatan.nama DESC',
                'namakabupatenASC' => 'kabupaten_kota.nama ASC',
                'namakabupatenDESC' => 'kabupaten_kota.nama DESC',
                'namaprovinsiASC' => 'provinsi.nama ASC',
                'namaprovinsiDESC' => 'provinsi.nama DESC',
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

            // Query kelurahan dengan kondisi pencarian dan urutan yang ditentukan
            $kelurahans = Kelurahan::leftJoin('kecamatan', 'kelurahan.id_kecamatan', '=', 'kecamatan.id')
                ->leftJoin('kabupaten_kota', 'kelurahan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'kelurahan.id_provinsi', '=', 'provinsi.id')
                ->select('kelurahan.*', 'kecamatan.nama as nama_kecamatan', 'kabupaten_kota.nama as nama_kabupaten', 'provinsi.nama as nama_provinsi')
                ->where(function ($query) use ($search) {
                    $query->where('kelurahan.nama', 'like', "%$search%")
                        ->orWhere('kecamatan.nama', 'like', "%$search%")
                        ->orWhere('kabupaten_kota.nama', 'like', "%$search%")
                        ->orWhere('provinsi.nama', 'like', "%$search%");
                })
                ->orderByRaw($order)
                ->offset($offset)
                ->limit($limit)
                ->get();

            return response()->json(['status' => 'SUCCESS', 'data' => $kelurahans]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            $kelurahan = Kelurahan::leftJoin('kecamatan', 'kelurahan.id_kecamatan', '=', 'kecamatan.id')
                ->leftJoin('kabupaten_kota', 'kelurahan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'kelurahan.id_provinsi', '=', 'provinsi.id')
                ->select('kelurahan.*', 'kecamatan.nama as nama_kecamatan', 'kabupaten_kota.nama as nama_kabupaten', 'provinsi.nama as nama_provinsi')
                ->where('id', $id)->first();

            return response()->json(['status' => 'SUCCESS', 'data' => $kelurahan]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_provinsi' => 'required|exists:provinsi,id',
                'id_kabupaten_kota' => 'required|exists:kabupaten_kota,id',
                'id_kecamatan' => 'required|exists:kecamatan,id',
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kelurahan = Kelurahan::create($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $kelurahan], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_provinsi' => 'required|exists:provinsi,id',
                'id_kabupaten_kota' => 'required|exists:kabupaten_kota,id',
                'id_kecamatan' => 'required|exists:kecamatan,id',
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kelurahan = Kelurahan::where('id', $id)->first();
            $kelurahan->update($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $kelurahan]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        try {
            $kelurahan = Kelurahan::where('id', $id)->first();
            $kelurahan->delete();
            return response()->json(['status' => 'SUCCESS', 'message' => 'Kelurahan deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function syncKelurahan()
    {
        try {
            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            // Mendefinisikan endpoint untuk sinkronisasi kelurahan
            $endpoint = 'kelurahan';

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint kelurahan
            $response = $apiController->makeRequest($request);

            // Mengambil data kelurahan dari respons
            $dataKelurahan = $response['data'];

            // Memproses setiap data kelurahan dari respons
            foreach ($dataKelurahan as $data) {
                // Mencari kelurahan berdasarkan ID
                $kelurahan = Kelurahan::find($data['kode_kelurahan']);

                // Jika kelurahan ditemukan, perbarui data
                if ($kelurahan) {
                    $kelurahan->update([
                        'nama' => $data['nama_kelurahan'],
                        'id_kecamatan' => $data['kode_kecamatan'],
                        'id_kabupaten_kota' => $data['kode_kota_kab'],
                        'id_provinsi' => $data['kode_provinsi'],
                        // Perbarui atribut lain yang diperlukan
                    ]);
                } else {
                    // Jika kelurahan tidak ditemukan, tambahkan data baru
                    Kelurahan::create([
                        'id' => $data['kode_kelurahan'],
                        'nama' => $data['nama_kelurahan'],
                        'id_kecamatan' => $data['kode_kecamatan'],
                        'id_kabupaten_kota' => $data['kode_kota_kab'],
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
                'message' => 'Sinkronisasi kelurahan berhasil',
            ], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

}
