<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifikasiBiayaAtribusi extends Model
{
    protected $table = 'verifikasi_biaya_atribusi';
    protected $primaryKey = 'id'; // Sesuaikan dengan nama primary key pada tabel
    public $timestamps = false;
    // Attribut yang dapat diisi
    protected $fillable = [
        'id',
        'id_regional',
        'id_kprk',
        'id_kpc',
        'tahun',
        'triwulan',
        'total_biaya',
        'tgl_pelaporan',
        'id_file',
        'catatan_pelapor',
        'id_status',
        'id_status_kprk',
        'id_status_kpc',
    ];

    // Relasi jika diperlukan
    // public function namaRelasi()
    // {
    //     return $this->belongsTo(NamaModel::class, 'foreign_key', 'local_key');
    // }
}
