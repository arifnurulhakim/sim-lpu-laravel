<?php

namespace App\Http\Controllers;

use App\Models\Regional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class RegionalController extends Controller
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
                'idASC' => 'id ASC',
                'idDESC' => 'id DESC',
                'namaASC' => 'nama ASC',
                'namaDESC' => 'nama DESC',
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

            // Query data regional dengan offset, limit, dan pencarian
            $query = Regional::query();

            if ($search !== '') {
                $query->where('nama', 'like', "%$search%");
            }

            $regionals = $query->orderByRaw($order)
                ->offset($offset)
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'data' => $regionals]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'kode' => 'required',
                'nama' => 'required',
                'id_file' => 'required',
                'id_provinsi' => 'required',
                'id_kabupaten_kota' => 'required',
                'id_kecamatan' => 'required',
                'id_kelurahan' => 'required',
                'longitude' => 'required',
                'latitude' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Buat data regional baru
            $regional = Regional::create($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $regional], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            // Cari data regional berdasarkan ID
            $regional = Regional::where('id', $id)->first();
            return response()->json(['status' => 'SUCCESS', 'data' => $regional]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'kode' => 'required',
                'nama' => 'required',
                'id_file' => 'required',
                'id_provinsi' => 'required',
                'id_kabupaten_kota' => 'required',
                'id_kecamatan' => 'required',
                'id_kelurahan' => 'required',
                'longitude' => 'required',
                'latitude' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Temukan dan perbarui data regional yang ada
            $regional = Regional::where('id', $id)->first();
            $regional->update($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $regional]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        try {
            // Temukan dan hapus data regional berdasarkan ID
            $regional = Regional::where('id', $id)->first();
            $regional->delete();
            return response()->json(['status' => 'SUCCESS', 'message' => 'Regional deleted successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
