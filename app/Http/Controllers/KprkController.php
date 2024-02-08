<?php

namespace App\Http\Controllers;

use App\Models\Kprk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class KprkController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Ambil parameter offset, limit, dan order dari permintaan
            $offset = $request->get('offset', 0);
            $limit = $request->get('limit', 10);
            $search = $request->get('search', '');
            $getOrder = $request->get('order', '');

            // Tentukan aturan urutan default dan pemetaan urutan
            $defaultOrder = $getOrder ? $getOrder : "id ASC";
            $orderMappings = [
                'idASC' => 'kprk.id ASC',
                'idDESC' => 'kprk.id DESC',
                'namaASC' => 'kprk.nama ASC',
                'namaDESC' => 'kprk.nama DESC',
                'namaregionalASC' => 'regional.nama ASC',
                'namaregionalDESC' => 'regional.nama DESC',
                // Tambahkan pemetaan urutan lain jika diperlukan
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
                ], Response::HTTP_BAD_REQUEST);
            }

            // Query data Kprk dengan offset, limit, dan pencarian
            $query = Kprk::query();

            if ($search !== '') {
                $query->where('nama', 'like', "%$search%");
            }

            // Query kabupaten/kota with search condition if search keyword is provided
            $kprkssQuery = Kprk::leftJoin('regional', 'kprk.id_regional', '=', 'regional.id')
                ->select('kprk.*', 'regional.nama as nama_regional')
                ->orderByRaw($order)
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $kprkssQuery->where(function ($query) use ($search) {
                    $query->where('kprk.nama', 'like', "%$search%")
                        ->orWhere('regional.nama', 'like', "%$search%");
                });
            }

            $kprks = $kprkssQuery->get();
            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'data' => $kprks]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'id_regional' => 'required',
                'kode' => 'required',
                'nama' => 'required',
                'id_file' => 'required',
                'id_provinsi' => 'required',
                'id_kabupaten_kota' => 'required',
                'id_kecamatan' => 'required',
                'id_kelurahan' => 'required',
                'longitude' => 'required',
                'latitude' => 'required',
                'tgl_sinkronisasi' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Buat data Kprk baru
            $kprk = Kprk::create($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $kprk], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            // Cari data Kprk berdasarkan ID
            $kprk = Kprk::where('id', $id)->first();
            return response()->json(['status' => 'SUCCESS', 'data' => $kprk]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'id_regional' => 'required',
                'kode' => 'required',
                'nama' => 'required',
                'id_file' => 'required',
                'id_provinsi' => 'required',
                'id_kabupaten_kota' => 'required',
                'id_kecamatan' => 'required',
                'id_kelurahan' => 'required',
                'longitude' => 'required',
                'latitude' => 'required',
                'tgl_sinkronisasi' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Temukan dan perbarui data Kprk yang ada
            $kprk = Kprk::where('id', $id)->first();
            $kprk->update($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $kprk]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        try {
            // Temukan dan hapus data Kprk berdasarkan ID
            $kprk = Kprk::where('id', $id)->first();
            $kprk->delete();
            return response()->json(['status' => 'SUCCESS', 'message' => 'Kprk deleted successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
