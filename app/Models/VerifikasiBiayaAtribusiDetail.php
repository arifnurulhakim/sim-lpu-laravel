<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifikasiBiayaAtribusiDetail extends Model
{
    protected $table = 'verifikasi_biaya_atribusi_detail';
    protected $primaryKey = 'id'; // Sesuaikan dengan nama primary key pada tabel
    public $timestamps = false;
    // Attribut yang dapat diisi
    protected $fillable = [
        'id',
        'id_verifikasi_biaya_atribusi',
        'id_rekening_biaya',
        'bulan',
        'pelaporan',
        'verifikasi',
        'catatan_pemeriksa',
        'id_verifikator',
        'tgl_verifikasi',
    ];

    // Relasi jika diperlukan
    // public function namaRelasi()
    // {
    //     return $this->belongsTo(NamaModel::class, 'foreign_key', 'local_key');
    // }
}
