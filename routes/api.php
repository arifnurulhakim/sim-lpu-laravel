<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BiayaAtribusiController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\JenisBisnisController;
use App\Http\Controllers\JenisKantorController;
use App\Http\Controllers\KabupatenKotaController;
use App\Http\Controllers\KategoriBiayaController;
use App\Http\Controllers\KecamatanController;
use App\Http\Controllers\KelurahanController;
use App\Http\Controllers\KprkController;
use App\Http\Controllers\PenyelenggaraController;
use App\Http\Controllers\ProvinsiController;
use App\Http\Controllers\PusherController;
use App\Http\Controllers\RegionalController;
use App\Http\Controllers\RekeningBiayaController;
use App\Http\Controllers\RekeningProduksiController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\SyncApiController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
 */
Route::get('/welcome', function () {
    return view('welcome');
})->name('welcome');
Route::get('/clear-cache', function () {
    $exitCode = Artisan::call('cache:clear');
    return '<h1>Cache cleared</h1>';
})->name('clear-cache');

Route::get('/route-clear', function () {
    $exitCode = Artisan::call('route:clear');
    return '<h1>Route cache cleared</h1>';
})->name('route-clear');

Route::get('/config-cache', function () {
    $exitCode = Artisan::call('config:cache');
    return '<h1>Configuration cached</h1>';
})->name('config-cache');

Route::get('/optimize', function () {
    $exitCode = Artisan::call('optimize');
    return '<h1>Configuration cached</h1>';
})->name('optimize');

Route::get('/storage-link', function () {
    $exitCode = Artisan::call('storage:link');
    return '<h1>storage linked</h1>';
})->name('optimize');

Route::get('/get-token', [ApiController::class, 'getToken']);
Route::get('/get-signature', [ApiController::class, 'generateSignature']);
Route::get('/makeRequest', [ApiController::class, 'makeRequest']);
Route::get('/syncProvinsi', [ProvinsiController::class, 'syncProvinsi']);
Route::get('/syncKabupaten-kota', [KabupatenKotaController::class, 'syncKabupaten']);
Route::get('/syncKecamatan', [KecamatanController::class, 'syncKecamatan']);
Route::get('/syncKelurahan', [KelurahanController::class, 'syncKelurahan']);

Route::get('/syncRegional', [SyncApiController::class, 'syncRegional']);
Route::get('/syncKategoriBiaya', [SyncApiController::class, 'syncKategoriBiaya']);
Route::get('/syncRekeningBiaya', [SyncApiController::class, 'syncRekeningBiaya']);
Route::get('/syncRekeningProduksi', [SyncApiController::class, 'syncRekeningProduksi']);
Route::get('/syncTipeBisnis', [SyncApiController::class, 'syncTipeBisnis']);
Route::get('/syncPetugasKCP', [SyncApiController::class, 'syncPetugasKCP']);
Route::get('/syncKCU', [SyncApiController::class, 'syncKCU']);
Route::get('/syncKPC', [SyncApiController::class, 'syncKPC']);
Route::get('/syncBiayaAtribusi', [SyncApiController::class, 'syncBiayaAtribusi']);
Route::get('/syncProduksi', [SyncApiController::class, 'syncProduksi']);

Route::controller(AuthController::class)->group(function () {
    // Route login tidak perlu middleware auth:api
    Route::post('/login', 'login')->name('login');
    Route::post('/register', 'register')->name('register');
    Route::get('/getProfile', 'getProfile')->name('getProfile');
    Route::get('/getUser/{id}', 'getUser')->name('getUser');
    Route::get('/getAlluser', 'getAlluser')->name('getAlluser');
    Route::delete('/deleteUser/{id}', 'deleteUser')->name('deleteUser');
    Route::post('/updateUser/{id}', 'updateUser')->name('updateUser');
    Route::post('/logout', 'logout')->name('logout');
    Route::get('/exportCSV', 'exportCSV')->name('exportCSV');

});

Route::controller(ForgotPasswordController::class)->group(function () {
    Route::get('/password/email', '__invoke')->name('postemail');
    Route::post('/password/email', '__invoke')->name('email');
});

Route::controller(CodeCheckController::class)->group(function () {
    Route::get('/password/code/check', '__invoke')->name('get_check');
    Route::post('/password/code/check', '__invoke')->name('post_check');
});

Route::controller(ResetPasswordController::class)->group(function () {
    Route::get('/password/reset', '__invoke')->name('postreset');
    Route::post('/password/reset', '__invoke')->name('reset');
});

Route::post('/reset-first-password', [ResetPasswordController::class, 'resetFirstPassword'])->name('reset-first-password');
Route::post('/sendMessage/{eventName}', [PusherController::class, 'sendMessage'])->name('sendMessage');
Route::get('/chatList', [PusherController::class, 'chatList']);
Route::get('/chatList/{penerima_id}', [PusherController::class, 'chatDetail']);
Route::get('/test', [PusherController::class, 'test'])->name('test');

Route::middleware('auth:api')->group(function () {
    Route::post('api-keys', [ApiKeyController::class, 'generateKey'])->name('api-keys.generate');
    Route::get('api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
    Route::delete('api-keys/{id}', [ApiKeyController::class, 'delete'])->name('api-keys.delete');
    Route::put('api-keys/deactivate/{id}', [ApiKeyController::class, 'deactivate'])->name('api-keys.deactivate');

    Route::middleware('api_key')->group(function () {
        Route::apiResource('user', UserController::class);
        Route::apiResource('provinsi', ProvinsiController::class);
        Route::apiResource('kabupaten-kota', KabupatenKotaController::class);
        Route::apiResource('kecamatan', KecamatanController::class);
        Route::apiResource('kelurahan', KelurahanController::class);
        Route::apiResource('jenis-bisnis', JenisBisnisController::class);
        Route::apiResource('jenis-kantor', JenisKantorController::class);
        Route::apiResource('kprk', KprkController::class);
        Route::apiResource('penyelenggara', PenyelenggaraController::class);
        Route::apiResource('regional', RegionalController::class);
        Route::apiResource('rekening-biaya', RekeningBiayaController::class);
        Route::apiResource('rekening-produksi', RekeningProduksiController::class);
        Route::apiResource('kategori-biaya', KategoriBiayaController::class);

        Route::get('status', [UserController::class, 'status'])->name('status');
        Route::get('grup', [UserController::class, 'grup'])->name('grup');

        Route::get('atribusi-tahun', [BiayaAtribusiController::class, 'getPerTahun'])->name('atribusi-tahun');
        Route::get('atribusi-regional', [BiayaAtribusiController::class, 'getPerRegional'])->name('atribusi-regional');
        Route::get('atribusi-kcu', [BiayaAtribusiController::class, 'getPerKCU'])->name('atribusi-kcu');
        Route::get('atribusi-detail', [BiayaAtribusiController::class, 'getDetail'])->name('atribusi-detail');

    });
});
