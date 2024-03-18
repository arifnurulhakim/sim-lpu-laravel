<?php

namespace App\Http\Controllers;

use App\Models\Npp;
use App\Models\ProduksiNasional;
use App\Models\Status;
use App\Models\VerifikasiProduksiDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class NppController extends Controller
{

    public function getPerTahun(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'tahun' => 'nullable|numeric',
                'bulan' => 'nullable|numeric',
                'status' => 'nullable|string|in:7,9',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 100);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $tahun = request()->get('tahun', '');
            $bulan = request()->get('bulan', '');
            $status = request()->get('status', '');

            $defaultOrder = $getOrder ? $getOrder : "bulan ASC";
            $orderMappings = [
                'bulanASC' => 'npp.bulan ASC',
                'bulanDESC' => 'npp.bulan DESC',
                'tahunASC' => 'npp.tahun ASC',
                'tahunDESC' => 'npp.tahun DESC',
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
                ], 400);
            }

            $nppQuery = Npp::orderByRaw($order)
                ->select('id', 'bulan', 'tahun', 'bsu as nominal')
                ->offset($offset)
                ->limit($limit);

            // Menambahkan kondisi WHERE berdasarkan variabel $tahun, $bulan, dan $status
            if ($tahun !== '') {
                $nppQuery->where('npp.tahun', $tahun);
            }

            if ($bulan !== '') {
                $nppQuery->where('npp.bulan', $bulan);
            }
            if ($status !== '') {
                $nppQuery->where('npp.id_status', $status);
            }

            $npp = $nppQuery->get();

            $grand_total = $npp->sum('nominal');
            $grand_total = "Rp " . number_format($grand_total, 2, ',', '.');
            // Mengubah format total_biaya menjadi format Rupiah
            foreach ($npp as $item) {
                $item->nominal = "Rp " . number_format($item->nominal, 2, ',', '.');

                // Ambil Npp dengan kriteria tertentu
                $getNpp = Npp::where('tahun', $item->tahun)
                    ->where('bulan', $item->bulan)
                    ->get();

                // Periksa apakah semua status dalam $getNpp adalah 9
                $semuaStatusSembilan = $getNpp->every(function ($biayaAtribusi) {
                    return $biayaAtribusi->id_status == 9;
                });

                // Jika semua status adalah 9, ambil status dari tabel Status
                if ($semuaStatusSembilan) {
                    $status = Status::where('id', 9)->first();
                    $item->status = $status->nama;
                } else {
                    $status = Status::where('id', 7)->first();
                    $item->status = $status->nama;
                }
            }

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'grand_total' => $grand_total,
                'data' => $npp,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getDetail(Request $request)
    {
        try {

            $id_npp = request()->get('id_npp', '');
            $validator = Validator::make($request->all(), [
                'id_npp' => 'required|numeric|exists:npp,id',

            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }
            $bulanIndonesia = [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
            ];

            $npp = Npp::select(
                'npp.id',
                'rekening_biaya.kode_rekening',
                'rekening_biaya.nama as nama_rekening',
                'npp.bulan',
                'npp.tahun',
                'npp.nama_file',
                'npp.bsu as pelaporan',
                'npp.verifikasi',
                'npp.catatan_pemeriksa',

            )
                ->where('npp.id', $request->id_npp)
                ->join('rekening_biaya', 'npp.id_rekening_biaya', '=', 'rekening_biaya.id')
                ->first();
            if ($npp) {
                $npp->periode = $bulanIndonesia[$npp->bulan - 1];
                $npp->pelaporan = "Rp " . number_format($npp->pelaporan, 2, ',', '.');
                $npp->verifikasi = "Rp " . number_format($npp->verifikasi, 2, ',', '.');

                $pendapatan_nasional = ProduksiNasional::select(DB::raw('SUM(jml_pendapatan) as jml_pendapatan'))
                    ->where('bulan', $npp->bulan)
                    ->where('status', 'OUTGOING')
                    ->where('tahun', $npp->tahun)->first();
                $npp->pendapatan_nasional = "Rp " . number_format($pendapatan_nasional->jml_pendapatan, 2, ',', '.');
                $pendapatan_kcp_nasional = VerifikasiProduksiDetail::select(DB::raw('SUM(produksi_detail.pelaporan) as pelaporan'))
                    ->join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
                    ->where('produksi_detail.nama_bulan', $npp->bulan)
                    ->where('produksi_detail.jenis_produksi', 'PENERIMAAN/OUTGOING')
                    ->where('produksi.tahun_anggaran', $npp->tahun)
                    ->first();
                $npp->pendapatan_kcp_nasional = "Rp " . number_format($pendapatan_kcp_nasional->pelaporan, 2, ',', '.');
                if ($pendapatan_nasional->jml_pendapatan != 0) {
                    $proporsi = ($pendapatan_kcp_nasional->pelaporan / $pendapatan_nasional->jml_pendapatan) * 100;
                    $npp->{"proporsi_" . $npp->periode} = number_format($proporsi, 2) . '%';
                } else {
                    $npp->{"proporsi_" . $npp->periode} = '0%'; // Atau bisa disesuaikan sesuai kebutuhan jika pendapatan nasional 0
                }

            }

            return response()->json([
                'status' => 'SUCCESS',
                'data' => $npp,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verifikasi(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                '*.id_npp' => 'required|string|exists:produksi_detail,id',
                '*.verifikasi' => 'required|string',
                '*.catatan_pemeriksa' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            foreach ($request->all() as $data) {
                $id_npp = $data['id_npp'];
                $verifikasi = $data['verifikasi'];
                $verifikasi = str_replace(['Rp.', '.'], '', $verifikasi);
                $verifikasi = str_replace(',', '.', $verifikasi);
                $verifikasiFloat = (float) $verifikasi;
                $verifikasiFormatted = number_format($verifikasiFloat, 2, '.', '');
                $catatan_pemeriksa = $data['catatan_pemeriksa'];
                $id_validator = Auth::user()->id;
                $tanggal_verifikasi = now();

                $produksi_detail = VerifikasiProduksiDetail::find($id_npp);

                $produksi_detail->update([
                    'verifikasi' => $verifikasiFormatted,
                    'catatan_pemeriksa' => $catatan_pemeriksa,
                    'id_validator' => $id_validator,
                    'tgl_verifikasi' => $tanggal_verifikasi,
                ]);

                if ($produksi_detail) {
                    $produksi = VerifikasiProduksiDetail::where('id_produksi', $produksi_detail->id_produksi)->get();
                    $countValid = $produksi->filter(function ($detail) {
                        return $detail->verifikasi != 0.00 && $detail->tgl_verifikasi !== null;
                    })->count();

                    if ($countValid === $produksi->count()) {
                        VerifikasiProduksi::where('id', $id_produksi)->update(['id_status' => 9]);
                    }
                }
            }

            return response()->json(['status' => 'SUCCESS']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verifikasi(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'data.*.id_npp' => 'required|string|exists:npp,id',
                'data.*.verifikasi' => 'required|string',
                'data.*.catatan_pemeriksa' => 'string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'ERROR', 'message' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $verifikasiData = $request->input('data');
            $updatedData = [];

            foreach ($verifikasiData as $data) {
                if (!isset($data['id_npp']) || !isset($data['verifikasi'])) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Invalid data structure'], Response::HTTP_BAD_REQUEST);
                }

                $id_npp = $data['id_npp'];
                $verifikasi = str_replace(['Rp.', ',', '.'], '', $data['verifikasi']);
                $verifikasiFloat = (float) $verifikasi;
                $verifikasiFormatted = number_format($verifikasiFloat, 2, '.', '');
                $catatan_pemeriksa = isset($data['catatan_pemeriksa']) ? $data['catatan_pemeriksa'] : '';
                $id_validator = Auth::user()->id;
                $tanggal_verifikasi = now();

                $npp = Npp::find($id_npp);

                if (!$npp) {
                    return response()->json(['status' => 'ERROR', 'message' => 'Detail biaya rutin tidak ditemukan'], Response::HTTP_NOT_FOUND);
                }

                $npp->update([
                    'verifikasi' => $verifikasiFormatted,
                    'catatan_pemeriksa' => $catatan_pemeriksa,
                    'id_validator' => $id_validator,
                    'tgl_verifikasi' => $tanggal_verifikasi,
                    'id_status' => 9,
                ]);

                $updatedData[] = $npp; // Menambahkan data yang diperbarui ke dalam array
            }

            return response()->json(['status' => 'SUCCESS', 'data' => $updatedData]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


}
