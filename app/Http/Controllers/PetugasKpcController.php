<?php

namespace App\Http\Controllers;

use App\Models\PetugasKPC;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class PetugasKpcController extends Controller
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
            $petugaskpcsQuery = PetugasKPC::orderByRaw($order)
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $petugaskpcsQuery->where('nama', 'like', "%$search%");
            }

            $petugaskpcs = $petugaskpcsQuery->get();
            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'data' => $petugaskpcs,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            $petugaskpc = PetugasKPC::where('id', $id)->first();
            return response()->json(['status' => 'SUCCESS', 'data' => $petugaskpc]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function getBykpc(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_kpc' => 'required|numeric|exists:kpc,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            // Cari data Kprk berdasarkan ID
            $petugaskpc = PetugasKPC::where('id_kpc', $request->id_kpc)->get();
            return response()->json(['status' => 'SUCCESS', 'data' => $petugaskpc]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_kpc' => 'required|numeric|exists:kpc,id',
                'nippos' => 'required|numeric',
                'nama_petugas' => 'required',
                'pangkat' => 'required',
                'masa_kerja' => 'required|numeric',
                'jabatan' => 'required',
                'id_user' => 'required|numeric|exists:users,id',
                'tgl_update' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $petugaskpc = PetugasKPC::create($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $petugaskpc], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_kpc' => 'nullable|numeric|exists:kpc,id',
                'nippos' => 'nullable|numeric',
                'nama_petugas' => 'nullable',
                'pangkat' => 'nullable',
                'masa_kerja' => 'nullable|numeric',
                'jabatan' => 'nullable',
                'id_user' => 'nullable|numeric|exists:users,id',
                'tgl_update' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $petugaskpc = PetugasKPC::where('id', $id)->first();
            $petugaskpc->update($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $petugaskpc]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        try {
            $petugaskpc = PetugasKPC::where('id', $id)->first();
            $petugaskpc->delete();
            return response()->json(['status' => 'SUCCESS', 'message' => 'PetugasKPC deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
