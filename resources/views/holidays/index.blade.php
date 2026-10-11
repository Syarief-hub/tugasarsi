@extends('layouts.app')

@section('title', 'Hari Libur - Manajemen Proyek')

@section('content')
    <div class="mb-6">
        <p class="text-sm text-slate-500">
            <a href="{{ route('projects.index') }}" class="hover:text-indigo-600">Proyek</a>
            <span class="mx-1">/</span> Hari Libur
        </p>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Hari Libur</h1>
        <p class="mt-1 text-sm text-slate-500">Daftar hari libur yang dapat dipertimbangkan dalam perencanaan jadwal.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Tambah Hari Libur</h2>
                <form action="{{ route('holidays.store') }}" method="POST" class="mt-4 grid gap-4">
                    @csrf
                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">
                            Nama Hari Libur <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required
                               maxlength="255" placeholder="Contoh: Hari Kemerdekaan"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="date" class="mb-1 block text-sm font-medium text-slate-700">
                            Tanggal <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="date" name="date" value="{{ old('date') }}" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                        Simpan
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2">
            @if ($holidays->isEmpty())
                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-600">
                    Belum ada hari libur terdaftar.
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nama</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($holidays as $holiday)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-600">{{ $holiday->date->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $holiday->name }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <form action="{{ route('holidays.destroy', $holiday) }}" method="POST"
                                              onsubmit="return confirm('Hapus hari libur ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-medium text-red-600 hover:text-red-800">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
