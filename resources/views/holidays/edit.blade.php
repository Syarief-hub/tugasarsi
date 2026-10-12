@extends('layouts.app')

@section('title', 'Edit Hari Libur - Manajemen Proyek')

@section('content')
    <div class="mb-6">
        <p class="text-sm text-slate-500">
            <a href="{{ route('projects.index') }}" class="hover:text-indigo-600">Proyek</a>
            <span class="mx-1">/</span>
            <a href="{{ route('holidays.index') }}" class="hover:text-indigo-600">Hari Libur</a>
            <span class="mx-1">/</span> Edit
        </p>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Edit Hari Libur</h1>
        <p class="mt-1 text-sm text-slate-500">Ubah nama atau tanggal hari libur {{ $holiday->name }}.</p>
    </div>

    <div class="max-w-xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <form action="{{ route('holidays.update', $holiday) }}" method="POST" class="grid gap-4">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="mb-1 block text-sm font-medium text-slate-700">
                    Nama Hari Libur <span class="text-red-500">*</span>
                </label>
                <input type="text" id="name" name="name" value="{{ old('name', $holiday->name) }}" required
                       maxlength="255" placeholder="Contoh: Hari Kemerdekaan"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            </div>

            <div>
                <label for="date" class="mb-1 block text-sm font-medium text-slate-700">
                    Tanggal <span class="text-red-500">*</span>
                </label>
                <input type="date" id="date" name="date" value="{{ old('date', $holiday->date->format('Y-m-d')) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            </div>

            <div class="mt-2 flex flex-wrap gap-3">
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                    Simpan Perubahan
                </button>
                <a href="{{ route('holidays.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                    Batal
                </a>
            </div>
        </form>
    </div>
@endsection
