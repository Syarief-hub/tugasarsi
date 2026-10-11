@extends('layouts.app')

@section('title', $task->name . ' - ' . $project->name)

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">
                <a href="{{ route('projects.index') }}" class="hover:text-indigo-600">Proyek</a>
                <span class="mx-1">/</span>
                <a href="{{ route('projects.show', $project) }}" class="hover:text-indigo-600">{{ $project->name }}</a>
                <span class="mx-1">/</span>
                <a href="{{ route('projects.tasks.index', $project) }}" class="hover:text-indigo-600">Tugas</a>
                <span class="mx-1">/</span> {{ $task->name }}
            </p>
            <h1 class="mt-1 flex items-center gap-2 text-xl font-semibold text-slate-900 sm:text-2xl">
                <span class="h-4 w-4 shrink-0 rounded-full" style="background-color: {{ $task->color }}"></span>
                {{ $task->name }}
            </h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('projects.tasks.edit', [$project, $task]) }}"
               class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                Edit Tugas
            </a>
            <form action="{{ route('projects.tasks.destroy', [$project, $task]) }}" method="POST"
                  onsubmit="return confirm('Hapus tugas ini?');">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-medium text-red-600 shadow-sm transition hover:bg-red-50">
                    Hapus
                </button>
            </form>
            <a href="{{ route('projects.tasks.index', $project) }}"
               class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                Kembali
            </a>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal Mulai</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $task->start_date->format('d/m/Y') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Durasi</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $task->duration }} hari kerja</p>
            <p class="mt-1 text-xs text-slate-500">Sabtu-Minggu dan hari libur proyek dilewati.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal Selesai</p>
            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $task->end_date->format('d/m/Y') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prioritas</p>
            <div class="mt-2">@include('partials.priority', ['priority' => $task->priority])</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Warna</p>
            <div class="mt-2 flex items-center gap-2">
                <span class="h-5 w-5 rounded-full" style="background-color: {{ $task->color }}"></span>
                <span class="text-sm text-slate-600">{{ $task->color }}</span>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tugas Induk</p>
            <p class="mt-2 text-sm text-slate-700">
                @if ($task->parent)
                    <a href="{{ route('projects.tasks.show', [$project, $task->parent]) }}" class="text-indigo-600 hover:underline">
                        {{ $task->parent->name }}
                    </a>
                @else
                    <span class="text-slate-400">Tugas utama</span>
                @endif
            </p>
        </div>
    </div>

    @if ($children->isNotEmpty())
        <div class="mt-8">
            <h2 class="mb-3 text-base font-semibold text-slate-900">Sub-tugas ({{ $children->count() }})</h2>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nama</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mulai</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Durasi</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Prioritas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($children as $child)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('projects.tasks.show', [$project, $child]) }}" class="font-medium text-slate-900 hover:text-indigo-600">
                                        {{ $child->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $child->start_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $child->duration }} hari</td>
                                <td class="px-4 py-3">@include('partials.priority', ['priority' => $child->priority])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="mt-8 grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Dependensi Masuk</h2>
            <p class="mt-1 text-xs text-slate-500">Tugas yang harus mendahului tugas ini.</p>

            @if ($predecessors->isEmpty())
                <p class="mt-4 rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">
                    Belum ada dependensi masuk.
                </p>
            @else
                <ul class="mt-4 divide-y divide-slate-100 text-sm">
                    @foreach ($predecessors as $predecessor)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <a href="{{ route('projects.tasks.show', [$project, $predecessor]) }}" class="font-medium text-slate-900 hover:text-indigo-600">
                                {{ $predecessor->name }}
                            </a>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                {{ \App\Models\TaskDependency::TYPES[$predecessor->pivot->type] ?? $predecessor->pivot->type }}
                                &middot; delay {{ $predecessor->pivot->delay }} hari kerja
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Dependensi Keluar</h2>
            <p class="mt-1 text-xs text-slate-500">Tugas yang menunggu tugas ini selesai.</p>

            @if ($successors->isEmpty())
                <p class="mt-4 rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">
                    Belum ada dependensi keluar.
                </p>
            @else
                <ul class="mt-4 divide-y divide-slate-100 text-sm">
                    @foreach ($successors as $successor)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <a href="{{ route('projects.tasks.show', [$project, $successor]) }}" class="font-medium text-slate-900 hover:text-indigo-600">
                                {{ $successor->name }}
                            </a>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                {{ \App\Models\TaskDependency::TYPES[$successor->pivot->type] ?? $successor->pivot->type }}
                                &middot; delay {{ $successor->pivot->delay }} hari kerja
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900">Alokasi Resource</h2>
        <p class="mt-1 text-xs text-slate-500">Tentukan berapa persen waktu resource dialokasikan untuk tugas ini.</p>

        @if ($availableResources->isEmpty())
            <p class="mt-4 rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">
                Belum ada resource pada proyek ini.
                <a href="{{ route('projects.resources.create', $project) }}" class="font-medium text-indigo-600 hover:underline">Tambah resource</a>
            </p>
        @else
            <form action="{{ route('allocations.store', $task) }}" method="POST"
                  class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div class="min-w-56 flex-1">
                    <label for="resource_id" class="mb-1 block text-sm font-medium text-slate-700">Resource</label>
                    <select id="resource_id" name="resource_id" required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option value="">— Pilih resource —</option>
                        @foreach ($availableResources as $resource)
                            <option value="{{ $resource->id }}" @selected((string) old('resource_id') === (string) $resource->id)>
                                {{ $resource->name }} ({{ $resource->role }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="w-32">
                    <label for="allocation" class="mb-1 block text-sm font-medium text-slate-700">Alokasi (%)</label>
                    <input type="number" id="allocation" name="allocation" min="1" max="100" step="1"
                           value="{{ old('allocation', 100) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                </div>
                <button type="submit"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                    Alokasikan
                </button>
            </form>
        @endif

        @if ($task->resources->isEmpty())
            <p class="mt-4 text-sm text-slate-500">Belum ada resource yang dialokasikan ke tugas ini.</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nama</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Peran</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Alokasi</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($task->resources as $resource)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $resource->name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $resource->role }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $resource->pivot->allocation }}%</td>
                                <td class="px-4 py-3 text-right">
                                    <form action="{{ route('allocations.destroy', [$task, $resource]) }}" method="POST"
                                          onsubmit="return confirm('Lepas alokasi resource ini dari tugas?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-medium text-red-600 hover:text-red-800">Lepas</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
