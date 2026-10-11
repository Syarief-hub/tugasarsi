@extends('layouts.app')

@section('title', 'Beranda - Manajemen Proyek')

@section('content')
    <div class="rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm sm:px-12">
        <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">
            Aplikasi Manajemen Proyek
        </span>
        <h1 class="mx-auto mt-4 max-w-3xl text-2xl font-semibold text-slate-900 sm:text-4xl">
            Rencanakan proyek seperti GanttProject, langsung dari peramban
        </h1>
        <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-slate-600 sm:text-base">
            Kelola proyek, tugas beserta sub-tugas, dependensi antar tugas (FS, SS, FF, SF),
            hari libur, serta alokasi sumber daya dalam satu tampilan yang bersih dan berbahasa Indonesia.
        </p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('projects.index') }}"
               class="inline-flex items-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                Lihat Daftar Proyek
            </a>
            <a href="{{ route('projects.create') }}"
               class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                Buat Proyek Baru
            </a>
        </div>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Tugas &amp; Sub-tugas</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Setiap tugas punya tanggal mulai, durasi harian, prioritas, dan warna.
                Tugas dapat dipecah menjadi sub-tugas.
            </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Dependensi</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Hubungkan tugas dengan tipe Finish-Start, Start-Start, Finish-Finish,
                atau Start-Finish beserta jeda (delay) dalam hari.
            </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Resource &amp; Alokasi</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Daftar anggota tim lengkap dengan peran dan gaji, lalu alokasikan
                mereka ke tugas dengan persentase tertentu.
            </p>
        </div>
    </div>
@endsection
