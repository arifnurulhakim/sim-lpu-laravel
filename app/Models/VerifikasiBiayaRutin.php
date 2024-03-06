<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifikasiBiayaRutin extends Model
{
    protected $table = 'verifikasi_biaya_rutin';
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
        'total_biaya_prognosa',
        'tgl_sinkronisasi',
        'id_status',
        'id_status_kprk',
        'id_status_kpc',
        'bulan',
    ];

    public function regional()
    {
        return $this->belongsTo(Regional::class, 'id_regional');
    }

    public function kprk()
    {
        return $this->belongsTo(Kprk::class, 'id_kprk');
    }
    public function kpc()
    {
        return $this->belongsTo(Kpc::class, 'id_kpc');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'id_status');
    }

    public function statusKprk()
    {
        return $this->belongsTo(StatusKprk::class, 'id_status_kprk');
    }
}
