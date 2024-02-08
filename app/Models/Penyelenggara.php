<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penyelenggara extends Model
{
    use HasFactory;
    protected $table = 'penyelengara';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'nama',
    ];
}
