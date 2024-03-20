<?php

namespace App\Http\Controllers;

use App\Models\Kpc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class KpcController extends Controller
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
                'idASC' => 'kpc.id ASC',
                'idDESC' => 'kpc.id DESC',
                'namaASC' => 'kpc.nama ASC',
                'namaDESC' => 'kpc.nama DESC',
                'namakprkASC' => 'kprk.nama ASC',
                'namakprkDESC' => 'kprk.nama DESC',
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

            // Query data Kpc dengan offset, limit, dan pencarian
            $query = Kpc::query();

            if ($search !== '') {
                $query->where('nama', 'like', "%$search%");
            }

            // Query kabupaten/kota with search condition if search keyword is provided
            $kpcssQuery = Kpc::leftJoin('regional', 'kpc.id_regional', '=', 'regional.id')
                ->leftJoin('kprk', 'kpc.id_kprk', '=', 'kprk.id')
                ->select('kpc.*', 'regional.nama as nama_regional', 'kprk.nama as nama_kprk')
                ->orderByRaw($order)
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $kpcssQuery->where(function ($query) use ($search) {
                    $query->where('kpc.nama', 'like', "%$search%")->where('kprk.nama', 'like', "%$search%")
                        ->orWhere('regional.nama', 'like', "%$search%");
                });
            }

            $kpcs = $kpcssQuery->get();
            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'data' => $kpcs]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'id_regional' => 'required|exists:regional,id',
                'id_kprk' => 'required|exists:kprk,id',
                'nomor_dirian' => 'required',
                'nama' => 'required',
                'jenis_kantor' => 'required',
                'alamat' => 'required',
                'koordinat_longitude' => 'required|numeric',
                'koordinat_latitude' => 'required|numeric',
                'nomor_telpon' => 'nullable|numeric',
                'nomor_fax' => 'nullable|numeric',
                'id_provinsi' => 'required|exists:provinsi,id',
                'id_kabupaten_kota' => 'required|exists:kabupaten_kota,id',
                'id_kecamatan' => 'required|exists:kecamatan,id',
                'id_kelurahan' => 'required|exists:kelurahan,id',
                'tipe_kantor' => 'required',
                'jam_kerja_senin_kamis' => 'nullable',
                'jam_kerja_jumat' => 'nullable',
                'jam_kerja_sabtu' => 'nullable',
                'frekuensi_antar_ke_alamat' => 'nullable',
                'frekuensi_antar_ke_dari_kprk' => 'nullable',
                'jumlah_tenaga_kontrak' => 'nullable|numeric',
                'kondisi_gedung' => 'nullable',
                'fasilitas_publik_dalam' => 'nullable',
                'fasilitas_publik_halaman' => 'nullable',
                'lingkungan_kantor' => 'nullable',
                'lingkungan_sekitar_kantor' => 'nullable',
                'tgl_sinkronisasi' => 'nullable|date',
                'id_user' => 'required|exists:users,id',
                'tgl_update' => 'nullable|date',
                'id_file' => 'nullable|exists:files,id',

            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Buat data Kpc baru
            $kpc = Kpc::create($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $kpc], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            // Cari data Kpc berdasarkan ID
            $kpc = Kpc::where('id', $id)->first();
            return response()->json(['status' => 'SUCCESS', 'data' => $kpc]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    public function getByregional(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_regional' => 'required|numeric|exists:regional,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            // Cari data Kpc berdasarkan ID
            $kpc = Kpc::where('id_regional', $request->id_regional)->get();
            return response()->json(['status' => 'SUCCESS', 'data' => $kpc]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
    public function getBykprk(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_kprk' => 'required|numeric|exists:kprk,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            // Cari data Kpc berdasarkan ID
            $kpc = Kpc::where('id_kprk', $request->id_kprk)->get();
            return response()->json(['status' => 'SUCCESS', 'data' => $kpc]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'id_regional' => 'required|exists:regional,id',
                'id_kprk' => 'required|exists:kprk,id',
                'nomor_dirian' => 'required',
                'nama' => 'required',
                'jenis_kantor' => 'required',
                'alamat' => 'required',
                'koordinat_longitude' => 'required|numeric',
                'koordinat_latitude' => 'required|numeric',
                'nomor_telpon' => 'nullable|numeric',
                'nomor_fax' => 'nullable|numeric',
                'id_provinsi' => 'required|exists:provinsi,id',
                'id_kabupaten_kota' => 'required|exists:kabupaten_kota,id',
                'id_kecamatan' => 'required|exists:kecamatan,id',
                'id_kelurahan' => 'required|exists:kelurahan,id',
                'tipe_kantor' => 'required',
                'jam_kerja_senin_kamis' => 'nullable',
                'jam_kerja_jumat' => 'nullable',
                'jam_kerja_sabtu' => 'nullable',
                'frekuensi_antar_ke_alamat' => 'nullable',
                'frekuensi_antar_ke_dari_kprk' => 'nullable',
                'jumlah_tenaga_kontrak' => 'nullable|numeric',
                'kondisi_gedung' => 'nullable',
                'fasilitas_publik_dalam' => 'nullable',
                'fasilitas_publik_halaman' => 'nullable',
                'lingkungan_kantor' => 'nullable',
                'lingkungan_sekitar_kantor' => 'nullable',
                'tgl_sinkronisasi' => 'nullable|date',
                'id_user' => 'required|exists:users,id',
                'tgl_update' => 'nullable|date',
                'id_file' => 'nullable|exists:files,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Temukan dan perbarui data Kpc yang ada
            $kpc = Kpc::where('id', $id)->first();
            $kpc->update($request->all());
            return response()->json(['status' => 'SUCCESS', 'data' => $kpc]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        try {
            // Temukan dan hapus data Kpc berdasarkan ID
            $kpc = Kpc::where('id', $id)->first();
            $kpc->delete();
            return response()->json(['status' => 'SUCCESS', 'message' => 'Kpc deleted successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
