@extends('layouts.app')

@section('title', 'Daftar Proyek - Manajemen Proyek')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 sm:text-2xl">Daftar Proyek</h1>
            <p class="mt-1 text-sm text-slate-500">Total {{ $projects->count() }} proyek terdaftar</p>
        </div>
        <a href="{{ route('projects.create') }}"
           class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
            + Proyek Baru
        </a>
    </div>

    @if ($projects->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="text-sm text-slate-600">Belum ada proyek. Buat proyek pertama Anda.</p>
            <a href="{{ route('projects.create') }}"
               class="mt-4 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                Buat Proyek
            </a>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Proyek</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Organisasi</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Website</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tugas</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Resource</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($projects as $project)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.show', $project) }}" class="font-medium text-slate-900 hover:text-indigo-600">
                                    {{ $project->name }}
                                </a>
                                @if ($project->description)
                                    <p class="mt-0.5 max-w-md truncate text-xs text-slate-500">{{ $project->description }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $project->organization ?? '-' }}</td>
                            <td class="px-4 py-3">
                                @if ($project->website)
                                    <a href="{{ $project->website }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">
                                        {{ $project->website }}
                                    </a>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $project->tasks_count }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $project->resources_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-3 text-sm">
                                    <a href="{{ route('projects.show', $project) }}" class="font-medium text-indigo-600 hover:text-indigo-800">Detail</a>
                                    <a href="{{ route('projects.edit', $project) }}" class="font-medium text-slate-600 hover:text-slate-900">Edit</a>
                                    <form action="{{ route('projects.destroy', $project) }}" method="POST"
                                          onsubmit="return confirm('Hapus proyek ini beserta seluruh tugas, resource, dan dependensinya?');">
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
