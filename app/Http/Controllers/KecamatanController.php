<?php

namespace App\Http\Controllers;

use App\Models\Kecamatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class KecamatanController extends Controller
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
            $defaultOrder = $getOrder ? $getOrder : "kecamatan.id ASC";
            $orderMappings = [
                'idASC' => 'kecamatan.id ASC',
                'idDESC' => 'kecamatan.id DESC',
                'namakecamatanASC' => 'kecamatan.nama ASC',
                'namakecamatanDESC' => 'kecamatan.nama DESC',
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

            // Ambil data kecamatan sesuai offset dan limit yang ditentukan
            $kecamatans = Kecamatan::leftJoin('kabupaten_kota', 'kecamatan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'kabupaten_kota.id_provinsi', '=', 'provinsi.id')
                ->select('kecamatan.*', 'kabupaten_kota.nama as nama_kabupaten_kota', 'provinsi.nama as nama_provinsi')
                ->where(function ($query) use ($search) {
                    $query->where('kecamatan.nama', 'like', "%$search%")
                        ->orWhere('kabupaten_kota.nama', 'like', "%$search%")
                        ->orWhere('provinsi.nama', 'like', "%$search%");
                })
                ->orderByRaw($order)
                ->offset($offset)
                ->limit($limit)
                ->get();

            return response()->json(['status' => 'SUCCESS', 'data' => $kecamatans]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            $kecamatan = Kecamatan::leftJoin('kabupaten_kota', 'kecamatan.id_kabupaten_kota', '=', 'kabupaten_kota.id')
                ->leftJoin('provinsi', 'kabupaten_kota.id_provinsi', '=', 'provinsi.id')
                ->select('kecamatan.*', 'kabupaten_kota.nama as nama_kabupaten_kota', 'provinsi.nama as nama_provinsi')
                ->where('kecamatan.id', $id)->first();

            return response()->json(['status' => 'SUCCESS', 'data' => $kecamatan]);
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
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kecamatan = Kecamatan::create($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $kecamatan], Response::HTTP_CREATED);
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
                'nama' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kecamatan = Kecamatan::where('id', $id)->first();
            $kecamatan->update($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $kecamatan]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        try {
            $kecamatan = Kecamatan::where('id', $id)->first();
            $kecamatan->delete();
            return response()->json(['status' => 'SUCCESS', 'message' => 'Kecamatan deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function syncKecamatan()
    {
        try {
            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            // Mendefinisikan endpoint untuk sinkronisasi kecamatan
            $endpoint = 'kecamatan';

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint kecamatan
            $response = $apiController->makeRequest($request);

            // Mengambil data kecamatan dari respons
            $dataKecamatan = $response['data'];

            // Memproses setiap data kecamatan dari respons
            foreach ($dataKecamatan as $data) {
                // Mencari kecamatan berdasarkan ID
                $kecamatan = Kecamatan::find($data['kode_kecamatan']);

                // Jika kecamatan ditemukan, perbarui data
                if ($kecamatan) {
                    $kecamatan->update([
                        'nama' => $data['nama_kecamatan'],
                        'id_kabupaten_kota' => $data['kode_kota_kab'],
                        'id_provinsi' => $data['kode_provinsi'],
                        // Perbarui atribut lain yang diperlukan
                    ]);
                } else {
                    // Jika kecamatan tidak ditemukan, tambahkan data baru
                    Kecamatan::create([
                        'id' => $data['kode_kecamatan'],
                        'nama' => $data['nama_kecamatan'],
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
                'message' => 'Sinkronisasi kecamatan berhasil',
            ], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

}
