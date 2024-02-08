<?php

namespace App\Http\Controllers;

use App\Models\RekeningBiaya;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class RekeningBiayaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $offset = $request->get('offset', 0);
            $limit = $request->get('limit', 10);
            $search = $request->get('search', '');
            $getOrder = $request->get('order', '');

            $defaultOrder = $getOrder ? $getOrder : "id ASC";
            $orderMappings = [
                'idASC' => 'id ASC',
                'idDESC' => 'id DESC',
                'namaASC' => 'nama ASC',
                'namaDESC' => 'nama DESC',
                // Add more order mappings if needed
            ];

            $order = $orderMappings[$getOrder] ?? $defaultOrder;

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

            $query = RekeningBiaya::query();

            if ($search !== '') {
                $query->where('nama', 'like', "%$search%");
            }

            $rekeningBiaya = $query->orderByRaw($order)
                ->offset($offset)
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'data' => $rekeningBiaya]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_rekening' => 'required',
                'nama' => 'required',
                'tgl_sinkronisasi' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $rekeningBiaya = RekeningBiaya::create($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $rekeningBiaya], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            $rekeningBiaya = RekeningBiaya::where('id', $id)->first();
            return response()->json(['status' => 'SUCCESS', 'data' => $rekeningBiaya]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode_rekening' => 'required',
                'nama' => 'required',
                'tgl_sinkronisasi' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $rekeningBiaya = RekeningBiaya::where('id', $id)->first();
            $rekeningBiaya->update($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $rekeningBiaya]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        try {
            $rekeningBiaya = RekeningBiaya::where('id', $id)->first();
            $rekeningBiaya->delete();
            return response()->json(['status' => 'SUCCESS', 'message' => 'rekening biaya deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
