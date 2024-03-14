<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerifikasiProduksi extends Model
{
    use HasFactory;
    protected $table = 'produksi';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'id_regional',
        'id_kprk',
        'id_kpc',
        'status_kprk',
        'status_regional',
        'tahun_anggaran',
        'triwulan',
        'total_lpu',
        'total_lpu_prognosa',
        'total_lpk',
        'total_lpk_prognosa',
        'total_lbf',
        'total_lbf_prognosa',
        'tgl_sinkronisasi',
        'status_lpu',
        'status_lbf',
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
