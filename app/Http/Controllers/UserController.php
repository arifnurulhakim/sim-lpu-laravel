<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Models\User;
use App\Models\UserGrup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
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
                'nameASC' => 'name ASC',
                'nameDESC' => 'name DESC',
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
            $query = User::query();

            if ($search !== '') {
                $query->where('nama', 'like', "%$search%");
            }
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }

            // Query data User dengan offset, limit, dan pencarian

            $users = $query->orderByRaw($order)
                ->offset($offset)
                ->limit($limit)
                ->get();

            // Query kabupaten/kota with search condition if search keyword is provided
            $usersQuery = User::leftJoin('status', 'user.id_status', '=', 'status.id')
                ->leftJoin('user_grup', 'user.id_grup', '=', 'user_grup.id')
                ->select('user.*', 'user_grup.nama as nama_grup', 'status.nama as status')
                ->orderByRaw($order)
                ->offset($offset)
                ->limit($limit);

            if ($search !== '') {
                $usersQuery->where(function ($query) use ($search) {
                    $query->where('user.nama', 'like', "%$search%")
                        ->orWhere('user_grup.nama', 'like', "%$search%")
                        ->orWhere('status.nama', 'like', "%$search%");
                });
            }

            $users = $usersQuery->get();

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'data' => $users,
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:255',
                'username' => 'required|string|max:255|unique:user',
                'password' => 'required|string|min:6',
                'id_grup' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $user = User::create([
                'nama' => $request->get('nama'),
                'username' => $request->get('username'),
                'password_hash' => bcrypt($request->get('password')),
                'id_grup' => $request->get('id_grup'),
                'id_status' => 2,
            ]);

            return response()->json(['status' => 'SUCCESS', 'data' => $user], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            // dd($e);
            return response()->json(['error' => $e], 500);
        }
    }

    public function show($id)
    {
        try {
            // Cari User berdasarkan ID
            $user = User::find($id);

            if (!$user) {
                return response()->json(['status' => 'ERROR', 'message' => 'User not found'], Response::HTTP_NOT_FOUND);
            }

            return response()->json(['status' => 'SUCCESS', 'data' => $user]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function update(Request $request, $id)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'nama' => 'string|max:255',
                'username' => 'string|max:255|unique:user',
                'password' => 'string|min:6',
                'id_grup' => 'integer',
                'id_status' => 'integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input data',
                    'errors' => $validator->errors(),
                ], Response::HTTP_BAD_REQUEST);
            }

            // Cari dan perbarui data User berdasarkan ID
            $user = User::find($id);

            if (!$user) {
                return response()->json(['status' => 'ERROR', 'message' => 'User not found'], Response::HTTP_NOT_FOUND);
            }

            // Memperbarui bidang-bidang yang ada dalam permintaan, jika ada
            $user->nama = $request->input('nama') ?? $user->nama;
            $user->username = $request->input('username') ?? $user->username;

            // Periksa apakah password dikirim dalam permintaan dan setel ulang password_hash jika iya
            if ($request->has('password')) {
                $user->password_hash = bcrypt($request->input('password'));
            }

            // Memperbarui bidang id_grup, jika ada dalam permintaan
            if ($request->has('id_grup')) {
                $user->id_grup = $request->input('id_grup');
            }

            // Memperbarui bidang id_status, jika ada dalam permintaan
            if ($request->has('id_status')) {
                $user->id_status = $request->input('id_status');
            }

            // Simpan perubahan
            $user->save();

            return response()->json(['status' => 'SUCCESS', 'data' => $user]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        try {
            // Cari dan hapus data User berdasarkan ID
            $user = User::find($id);

            if (!$user) {
                return response()->json(['status' => 'ERROR', 'message' => 'User not found'], Response::HTTP_NOT_FOUND);
            }

            $user->delete();

            return response()->json(['status' => 'SUCCESS', 'message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function status()
    {
        try {
            // Cari User berdasarkan ID
            $status = Status::all();

            if (!$status) {
                return response()->json(['status' => 'ERROR', 'message' => 'status not found'], Response::HTTP_NOT_FOUND);
            }

            return response()->json(['status' => 'SUCCESS', 'data' => $status]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function grup()
    {
        try {
            // Cari User berdasarkan ID
            $user_grup = UserGrup::all();

            if (!$user_grup) {
                return response()->json(['status' => 'ERROR', 'message' => 'user grup not found'], Response::HTTP_NOT_FOUND);
            }

            return response()->json(['status' => 'SUCCESS', 'data' => $user_grup]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
