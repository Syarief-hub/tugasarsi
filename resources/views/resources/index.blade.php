@extends('layouts.app')

@section('title', 'Resource - ' . $project->name)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">
                <a href="{{ route('projects.index') }}" class="hover:text-indigo-600">Proyek</a>
                <span class="mx-1">/</span>
                <a href="{{ route('projects.show', $project) }}" class="hover:text-indigo-600">{{ $project->name }}</a>
                <span class="mx-1">/</span> Resource
            </p>
            <h1 class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Daftar Resource</h1>
            <p class="mt-1 text-sm text-slate-500">Total {{ $resources->count() }} resource</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('projects.show', $project) }}"
               class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                Kembali ke Proyek
            </a>
            <a href="{{ route('projects.resources.create', $project) }}"
               class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                + Resource Baru
            </a>
        </div>
    </div>

    @if ($resources->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="text-sm text-slate-600">Belum ada resource pada proyek ini.</p>
            <a href="{{ route('projects.resources.create', $project) }}"
               class="mt-4 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                Tambah Resource Pertama
            </a>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Telepon</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Peran</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Gaji</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tugas</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($resources as $resource)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $resource->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $resource->phone ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $resource->email ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                    {{ $resource->role }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">Rp {{ number_format((float) $resource->salary, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $resource->tasks_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-3 text-sm">
                                    <a href="{{ route('projects.resources.edit', [$project, $resource]) }}" class="font-medium text-slate-600 hover:text-slate-900">Edit</a>
                                    <form action="{{ route('projects.resources.destroy', [$project, $resource]) }}" method="POST"
                                          onsubmit="return confirm('Hapus resource ini beserta alokasinya?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-medium text-red-600 hover:text-red-800">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
