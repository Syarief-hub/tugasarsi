<?php

use App\Http\Controllers\AllocationController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman utama
|--------------------------------------------------------------------------
|
| Halaman beranda bersifat statis agar tetap bisa dibuka tanpa data,
| lalu mengarahkan pengguna ke daftar proyek.
|
*/

Route::get('/', function () {
    return view('home');
})->name('home');

/*
|--------------------------------------------------------------------------
| Proyek
|--------------------------------------------------------------------------
*/

Route::resource('projects', ProjectController::class);

/*
|--------------------------------------------------------------------------
| Tugas & Resource (bersarang di dalam proyek)
|--------------------------------------------------------------------------
|
| scopeBindings memastikan {task} dan {resource} selalu diambil dari
| proyek yang bersangkutan.
|
*/

Route::scopeBindings()->group(function () {
    Route::resource('projects.tasks', TaskController::class);
    Route::resource('projects.resources', ResourceController::class)->except(['show']);
});

/*
|--------------------------------------------------------------------------
| Alokasi resource ke tugas
|--------------------------------------------------------------------------
*/

Route::post('tasks/{task}/allocations', [AllocationController::class, 'store'])
    ->name('allocations.store');

Route::delete('tasks/{task}/allocations/{resource}', [AllocationController::class, 'detach'])
    ->name('allocations.destroy');

/*
|--------------------------------------------------------------------------
| Hari libur
|--------------------------------------------------------------------------
*/

Route::resource('holidays', HolidayController::class)->only(['index', 'store', 'destroy']);
