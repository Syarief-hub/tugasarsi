@extends('layouts.app')

@section('title', 'Tugas - ' . $project->name)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">
                <a href="{{ route('projects.index') }}" class="hover:text-indigo-600">Proyek</a>
                <span class="mx-1">/</span>
                <a href="{{ route('projects.show', $project) }}" class="hover:text-indigo-600">{{ $project->name }}</a>
                <span class="mx-1">/</span> Tugas
            </p>
            <h1 class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Daftar Tugas</h1>
            <p class="mt-1 text-sm text-slate-500">Total {{ $tasks->count() }} tugas &middot; Urut berdasarkan tanggal mulai</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('projects.show', $project) }}"
               class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                Kembali ke Proyek
            </a>
            <a href="{{ route('projects.tasks.create', $project) }}"
               class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                + Tugas Baru
            </a>
        </div>
    </div>

    @if ($tasks->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="text-sm text-slate-600">Belum ada tugas pada proyek ini.</p>
            <a href="{{ route('projects.tasks.create', $project) }}"
               class="mt-4 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                Tambah Tugas Pertama
            </a>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Tugas</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mulai</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Durasi</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Selesai</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Prioritas</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Resource</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($tasks as $task)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="flex items-center gap-2 font-medium text-slate-900 hover:text-indigo-600">
                                    <span class="h-3 w-3 shrink-0 rounded-full" style="background-color: {{ $task->color }}"></span>
                                    <span class="{{ $task->parent_id ? 'pl-5' : '' }}">{{ $task->name }}</span>
                                </a>
                                @if ($task->parent_id)
                                    <p class="mt-0.5 pl-5 text-xs text-slate-500">
                                        Sub-tugas dari {{ $task->parent->name ?? '-' }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $task->start_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $task->duration }} hari</td>
                            <td class="px-4 py-3 text-slate-600">{{ $task->end_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">@include('partials.priority', ['priority' => $task->priority])</td>
                            <td class="px-4 py-3 text-slate-600">{{ $task->resources->count() }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-3 text-sm">
                                    <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="font-medium text-indigo-600 hover:text-indigo-800">Detail</a>
                                    <a href="{{ route('projects.tasks.edit', [$project, $task]) }}" class="font-medium text-slate-600 hover:text-slate-900">Edit</a>
                                    <form action="{{ route('projects.tasks.destroy', [$project, $task]) }}" method="POST"
                                          onsubmit="return confirm('Hapus tugas ini?');">
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
