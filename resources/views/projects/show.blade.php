@extends('layouts.app')

@section('title', $project->name . ' - Manajemen Proyek')

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">
                <a href="{{ route('projects.index') }}" class="hover:text-indigo-600">Proyek</a>
                <span class="mx-1">/</span> {{ $project->name }}
            </p>
            <h1 class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">{{ $project->name }}</h1>
            @if ($project->organization)
                <p class="mt-1 text-sm text-slate-600">{{ $project->organization }}</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <form action="{{ route('projects.schedule', $project) }}" method="POST">
                @csrf
                <button type="submit"
                        class="inline-flex items-center rounded-lg border border-indigo-600 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 shadow-sm transition hover:bg-indigo-100">
                    Jadwalkan ulang
                </button>
            </form>
            <a href="{{ route('projects.edit', $project) }}"
               class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                Edit Proyek
            </a>
            <a href="{{ route('projects.gantt', $project) }}"
               class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                Gantt Chart
            </a>
            <a href="{{ route('projects.pert', $project) }}"
               class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                PERT Chart
            </a>
            <a href="{{ route('projects.criticalPath', $project) }}"
               class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                Analisis Jalur Kritis
            </a>
            <a href="{{ route('projects.tasks.create', $project) }}"
               class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                + Tugas
            </a>
            <a href="{{ route('projects.resources.create', $project) }}"
               class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                + Resource
            </a>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah Tugas</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $tasks->count() }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah Resource</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $resources->count() }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Website</p>
            <p class="mt-2 break-all text-sm text-slate-700">
                @if ($project->website)
                    <a href="{{ $project->website }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">
                        {{ $project->website }}
                    </a>
                @else
                    <span class="text-slate-400">Belum diisi</span>
                @endif
            </p>
        </div>
    </div>

    @if ($project->description)
        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">Deskripsi</h2>
            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ $project->description }}</p>
        </div>
    @endif

    <div class="mt-8">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Tugas</h2>
                <p class="mt-0.5 text-xs text-slate-500">Tanggal mulai dan selesai dihitung dalam hari kerja (Sabtu-Minggu dan hari libur proyek dilewati).</p>
            </div>
            <div class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('projects.tasks.index', $project) }}" class="font-medium text-indigo-600 hover:text-indigo-800">Lihat semua tugas</a>
                <a href="{{ route('projects.tasks.create', $project) }}" class="font-medium text-slate-600 hover:text-slate-900">Tambah tugas</a>
            </div>
        </div>

        @if ($tasks->isEmpty())
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-8 text-center text-sm text-slate-600">
                Belum ada tugas. <a href="{{ route('projects.tasks.create', $project) }}" class="font-medium text-indigo-600 hover:underline">Tambahkan tugas pertama</a>.
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Tugas</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mulai</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Durasi (hari kerja)</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Selesai</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Prioritas</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($tasks as $task)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="flex items-center gap-2 font-medium text-slate-900 hover:text-indigo-600">
                                        <span class="h-3 w-3 shrink-0 rounded-full" style="background-color: {{ $task->color }}"></span>
                                        @if ($task->parent_id)
                                            <span class="text-slate-400">&#8627;</span>
                                        @endif
                                        <span>{{ $task->name }}</span>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $task->start_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $task->duration }} hari kerja</td>
                                <td class="px-4 py-3 text-slate-600">{{ $task->end_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">@include('partials.priority', ['priority' => $task->priority])</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center justify-end gap-3 text-sm">
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
    </div>

    <div class="mt-8">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-slate-900">Resource</h2>
            <div class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('projects.resources.index', $project) }}" class="font-medium text-indigo-600 hover:text-indigo-800">Lihat semua resource</a>
                <a href="{{ route('projects.resources.create', $project) }}" class="font-medium text-slate-600 hover:text-slate-900">Tambah resource</a>
            </div>
        </div>

        @if ($resources->isEmpty())
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-8 text-center text-sm text-slate-600">
                Belum ada resource. <a href="{{ route('projects.resources.create', $project) }}" class="font-medium text-indigo-600 hover:underline">Tambahkan resource pertama</a>.
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nama</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Peran</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Telepon</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Email</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($resources as $resource)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $resource->name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $resource->role }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $resource->phone ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $resource->email ?? '-' }}</td>
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
    </div>
@endsection
