<?php

namespace App\Http\Controllers;

use App\Models\BiayaArtibusi;
use App\Models\BiayaArtibusiDetail;
use App\Models\JenisBisnis;
use App\Models\KategoriBiaya;
use App\Models\Kprk;
use App\Models\PetugasKPC;
use App\Models\Regional;
use App\Models\RekeningBiaya;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SyncApiController extends Controller
{
    public function syncRegional()
    {
        try {
            // Mendefinisikan endpoint untuk sinkronisasi provinsi
            $endpoint = 'profil_regional';

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            // Mengambil data provinsi dari respons
            $dataRegional = $response['data'];
            if (!$dataRegional) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataRegional as $data) {
                // Mencari provinsi berdasarkan ID
                $regional = Regional::find($data['id_regional']);

                if ($regional) {
                    $regional->update([
                        'nama' => $data['nama_regional'],
                        // Perbarui atribut lain yang diperlukan
                    ]);
                } else {
                    // Jika provinsi tidak ditemukan, tambahkan data baru
                    Regional::create([
                        'id' => $data['id_regional'],
                        'kode' => $data['kode_regional'],
                        'nama' => $data['nama_regional'],
                        // Tambahkan atribut lain yang diperlukan
                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi regional berhasil'], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncKategoriBiaya()
    {
        try {
            // Mendefinisikan endpoint untuk sinkronisasi provinsi
            $endpoint = 'kategori_biaya';

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            // Mengambil data provinsi dari respons
            $dataKategoriBiaya = $response['data'];
            if (!$dataKategoriBiaya) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataKategoriBiaya as $data) {
                // Mencari provinsi berdasarkan ID
                $kategoriBiaya = KategoriBiaya::find($data['id']);

                if ($kategoriBiaya) {
                    $kategoriBiaya->update([
                        'nama' => $data['deskripsi'],
                        // Perbarui atribut lain yang diperlukan
                    ]);
                } else {
                    // Jika provinsi tidak ditemukan, tambahkan data baru
                    KategoriBiaya::create([
                        'id' => $data['id'],
                        'nama' => $data['deskripsi'],
                        // Tambahkan atribut lain yang diperlukan
                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi regional berhasil'], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncRekeningBiaya()
    {
        try {
            // Mendefinisikan endpoint untuk sinkronisasi provinsi
            $endpoint = 'rekening_biaya';

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            // Mengambil data provinsi dari respons
            $dataRekeningBiaya = $response['data'];
            if (!$dataRekeningBiaya) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataRekeningBiaya as $data) {
                // Mencari provinsi berdasarkan ID
                $rekeningBiaya = RekeningBiaya::find($data['id_rekening']);

                if ($rekeningBiaya) {
                    $rekeningBiaya->update([
                        'nama' => $data['nama_rekening'],
                        // Perbarui atribut lain yang diperlukan
                    ]);
                } else {
                    // Jika provinsi tidak ditemukan, tambahkan data baru
                    RekeningBiaya::create([
                        'id' => $data['id_rekening'],
                        'kode' => $data['kode_rekening'],
                        'nama' => $data['nama_rekening'],
                        // Tambahkan atribut lain yang diperlukan
                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi rekening biaya berhasil'], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncRekeningProduksi()
    {
        try {
            // Mendefinisikan endpoint untuk sinkronisasi provinsi
            $endpoint = 'rekening_produksi';

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            // Mengambil data provinsi dari respons
            $dataRekeningProduksi = $response['data'];
            if (!$dataRekeningProduksi) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataRekeningProduksi as $data) {
                // Mencari provinsi berdasarkan ID
                $rekeningProduksi = RekeningBiaya::find($data['id_rekening']);

                if ($rekeningProduksi) {
                    $rekeningProduksi->update([

                        'nama' => $data['nama_rekening'],
                        'id_produk' => $data['id_produk'],
                        'nama_produk' => $data['nama_produk'],
                        'id_tipe_bisnis' => $data['id_tipe_bisnis'],
                        // Perbarui atribut lain yang diperlukan
                    ]);
                } else {
                    // Jika provinsi tidak ditemukan, tambahkan data baru
                    RekeningBiaya::create([
                        'id' => $data['id_rekening'],
                        'kode_rekening' => $data['kode_rekening'],
                        'nama' => $data['nama_rekening'],
                        'id_produk' => $data['id_produk'],
                        'nama_produk' => $data['nama_produk'],
                        'id_tipe_bisnis' => $data['id_tipe_bisnis'],
                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi rekening produksi berhasil'], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncTipeBisnis()
    {
        try {
            // Mendefinisikan endpoint untuk sinkronisasi provinsi
            $endpoint = 'tipe_bisnis';

            // Membuat instance dari ApiController
            $apiController = new ApiController();

            // Membuat instance dari Request dan mengisi access token jika diperlukan
            $request = new Request();
            $request->merge(['end_point' => $endpoint]);

            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            // Mengambil data provinsi dari respons
            $dataTipeBisnis = $response['data'];
            if (!$dataTipeBisnis) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataTipeBisnis as $data) {
                // Mencari provinsi berdasarkan ID
                $rekeningBiaya = JenisBisnis::find($data['id']);

                if ($rekeningBiaya) {
                    $rekeningBiaya->update([
                        'nama' => $data['deskripsi'],
                        // Perbarui atribut lain yang diperlukan
                    ]);
                } else {
                    // Jika provinsi tidak ditemukan, tambahkan data baru
                    JenisBisnis::create([
                        'id' => $data['id'],
                        'nama' => $data['deskripsi'],
                        // Tambahkan atribut lain yang diperlukan
                    ]);
                }
            }
            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi tipe bisnis berhasil'], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncPetugasKCP(Request $request)
    {
        try {
            // Mendefinisikan endpoint untuk sinkronisasi provinsi
            $endpoint = 'petugas_kpc';
            $id_kpc = $request->id_kpc;
            // Membuat instance dari ApiController
            $apiController = new ApiController();
            $url_request = $endpoint . '?id_kpc=' . $id_kpc;
            $request->merge(['end_point' => $url_request]);

            $response = $apiController->makeRequest($request);
            // dd($response);

            // Mengambil data provinsi dari respons
            $dataPetugasKPC = $response['data'] ?? [];
            if (!$dataPetugasKPC) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataPetugasKPC as $data) {
                // Mencari provinsi berdasarkan ID
                $petugasKPC = PetugasKPC::where('id_kpc', $data['id_kpc']);

                if ($petugasKPC) {
                    $petugasKPC->update([
                        'nama_petugas' => $data['nama_petugas'],
                        'nippos' => $data['nippos'],
                        'pangkat' => $data['pangkat'],
                        'masa_kerja' => $data['masa_kerja'],
                        'jabatan' => $data['jabatan'],
                        // Perbarui atribut lain yang diperlukan
                    ]);
                } else {
                    // Jika provinsi tidak ditemukan, tambahkan data baru
                    PetugasKPC::create([
                        'nama_petugas' => $data['nama_petugas'],
                        'nippos' => $data['nippos'],
                        'pangkat' => $data['pangkat'],
                        'masa_kerja' => $data['masa_kerja'],
                        'jabatan' => $data['jabatan'],
                        // Tambahkan atribut lain yang diperlukan
                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi petugas KPC berhasil'], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncKCU(Request $request)
    {
        try {
            // Mendefinisikan endpoint untuk sinkronisasi provinsi
            $endpoint = 'profil_kprk';
            $id_kprk = $request->id_kprk;
            // Membuat instance dari ApiController
            $apiController = new ApiController();

            $url_request = $endpoint . '?id_kprk=' . $id_kprk;
            $request->merge(['end_point' => $url_request]);

            $response = $apiController->makeRequest($request);
            // Mengambil data provinsi dari respons
            $dataKCU = $response['data'] ?? [];
            if (!$dataKCU) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataKCU as $data) {
                // Mencari provinsi berdasarkan ID
                $petugasKCU = Kprk::find($data['id_kprk']);

                if ($petugasKCU) {
                    $petugasKCU->update([
                        'id_regional' => $data['regional'],
                        'nama' => $data['nama_kprk'],
                        'id_provinsi' => $data['provinsi'],
                        'id_kabupaten_kota' => $data['kab_kota'],
                        'id_kecamatan' => $data['kecamatan'],
                        'id_kelurahan' => $data['kelurahan'],
                        'jumlah_kpc_lpu' => $data['jumlah_kpc_lpu'],
                        'jumlah_kpc_lpk' => $data['jumlah_kpc_lpk'],
                        'tgl_sinkronisasi' => now(),
                        // Perbarui atribut lain yang diperlukan
                    ]);
                } else {
                    // Jika provinsi tidak ditemukan, tambahkan data baru
                    Kprk::create([
                        'id' => $data['id_kprk'],
                        'id_regional' => $data['regional'],
                        'nama' => $data['nama_kprk'],
                        'id_provinsi' => $data['provinsi'],
                        'id_kabupaten_kota' => $data['kab_kota'],
                        'id_kecamatan' => $data['kecamatan'],
                        'id_kelurahan' => $data['kelurahan'],
                        'jumlah_kpc_lpu' => $data['jumlah_kpc_lpu'],
                        'jumlah_kpc_lpk' => $data['jumlah_kpc_lpk'],
                        'tgl_sinkronisasi' => now(),
                        // Tambahkan atribut lain yang diperlukan
                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi petugas KPC berhasil'], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncKPC(Request $request)
    {
        try {
            // Mendefinisikan endpoint untuk sinkronisasi provinsi
            $endpoint = 'daftar_kpc';
            // $id_kpc = $request->id_kprk;
            // Membuat instance dari ApiController
            $apiController = new ApiController();
            $request->merge(['end_point' => $endpoint]);
            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            // Mengambil data provinsi dari respons
            $dataKCP = $response['data'];
            if (!$dataKCP) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();
            foreach ($dataKCP as $data) {
                // Mencari provinsi berdasarkan ID
                $kcp = Kpc::find($data['nopend']);
                if (!$kcp) {
                    Kpc::create([
                        'id' => $data['nopend'],
                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi KCP berhasil'], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function syncBiayaAtribusi(Request $request)
    {
        try {
            // Mendefinisikan endpoint untuk sinkronisasi provinsi
            $endpoint = '';
            $id_regional = $request->id_regional;
            $id_kprk = $request->id_kprk;
            $kategori_biaya = $request->kategori_biaya;
            $bulan = $request->bulan;
            $tahun = $request->tahun;

            if ($kategori_biaya == 1) {
                $endpoint = 'biaya_upl';
            } elseif ($kategori_biaya == 2) {
                $endpoint = 'biaya_angkutan_pos_setempat';
            } else {
                $endpoint = 'biaya_sopir_tersier';
            }

            // Membuat instance dari ApiController
            $apiController = new ApiController();
            $url_request = $endpoint . '?bulan=' . $bulan . '&id_kprk=' . $id_kprk . '&tahun=' . $tahun;
            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            // Mengambil data provinsi dari respons
            $dataBiayaAtribusi = $response['data'];
            if (!$dataBiayaAtribusi) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataBiayaAtribusi as $data) {
                // Mencari provinsi berdasarkan ID
                $biayaAtribusi = BiayaArtibusi::find($data['id']);
                $biayaAtribusiDetail = BiayaArtibusiDetail::where('id_biaya_atribusi', $biayaAtribusi->id)->where('id_rekening_biaya', $data['koderekening'])->get();

                if ($petugasKPC) {
                    $petugasKPC->update([
                        'nama_petugas' => $data['nama_petugas'],
                        'nippos' => $data['nippos'],
                        'pangkat' => $data['pangkat'],
                        'masa_kerja' => $data['masa_kerja'],
                        'jabatan' => $data['jabatan'],
                        // Perbarui atribut lain yang diperlukan
                    ]);
                } else {
                    // Jika provinsi tidak ditemukan, tambahkan data baru
                    PetugasKPC::create([
                        'nama_petugas' => $data['nama_petugas'],
                        'nippos' => $data['nippos'],
                        'pangkat' => $data['pangkat'],
                        'masa_kerja' => $data['masa_kerja'],
                        'jabatan' => $data['jabatan'],
                        // Tambahkan atribut lain yang diperlukan
                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi petugas KPC berhasil'], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    public function syncProduksi(Request $request)
    {
        try {
            // Mendefinisikan endpoint untuk sinkronisasi provinsi
            $endpoint = 'produksi';
            $tipe_bisnis = $request->kd_bisnis;
            $no_kcp = $request->nopend;
            $triwulan = $request->triwulan;
            $tahun = $request->tahun;

            // Membuat instance dari ApiController
            $apiController = new ApiController();
            $url_request = $endpoint . '?nopend=' . $no_kcp . '&kd_bisnis=' . $tipe_bisnis . '&tahun=' . $tahun . '&triwulan=' . $triwulan;
            // Memanggil makeRequest dari ApiController untuk sinkronisasi dengan endpoint provinsi
            $response = $apiController->makeRequest($request);

            // Mengambil data provinsi dari respons
            $dataBiayaAtribusi = $response['data'];
            if (!$dataBiayaAtribusi) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }

            // Memulai transaksi database untuk meningkatkan kinerja
            DB::beginTransaction();

            foreach ($dataBiayaAtribusi as $data) {
                // Mencari provinsi berdasarkan ID
                $biayaAtribusi = BiayaArtibusi::find($data['id']);
                $biayaAtribusiDetail = BiayaArtibusiDetail::where('id_biaya_atribusi', $biayaAtribusi->id)->where('id_rekening_biaya', $data['koderekening'])->get();

                if ($petugasKPC) {
                    $petugasKPC->update([
                        'nama_petugas' => $data['nama_petugas'],
                        'nippos' => $data['nippos'],
                        'pangkat' => $data['pangkat'],
                        'masa_kerja' => $data['masa_kerja'],
                        'jabatan' => $data['jabatan'],
                        // Perbarui atribut lain yang diperlukan
                    ]);
                } else {
                    // Jika provinsi tidak ditemukan, tambahkan data baru
                    PetugasKPC::create([
                        'nama_petugas' => $data['nama_petugas'],
                        'nippos' => $data['nippos'],
                        'pangkat' => $data['pangkat'],
                        'masa_kerja' => $data['masa_kerja'],
                        'jabatan' => $data['jabatan'],
                        // Tambahkan atribut lain yang diperlukan
                    ]);
                }
            }

            // Commit transaksi setelah selesai
            DB::commit();

            // Setelah sinkronisasi selesai, kembalikan respons JSON sukses
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Sinkronisasi petugas KPC berhasil'], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            DB::rollBack();

            // Tangani kesalahan yang terjadi selama sinkronisasi
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
}
