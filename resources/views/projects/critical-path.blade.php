@extends('layouts.app')

@section('title', 'Analisis Jalur Kritis - ' . $project->name)

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">
                <a href="{{ route('projects.index') }}" class="hover:text-indigo-600">Proyek</a>
                <span class="mx-1">/</span>
                <a href="{{ route('projects.show', $project) }}" class="hover:text-indigo-600">{{ $project->name }}</a>
                <span class="mx-1">/</span> Analisis Jalur Kritis
            </p>
            <h1 class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Analisis Jalur Kritis Manual: {{ $project->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                Perhitungan PERT/CPM: durasi dihitung sebagai jumlah hari polos,
                jalur dengan total terpanjang adalah critical path.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('projects.pert', $project) }}"
               class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                PERT Chart
            </a>
            <a href="{{ route('projects.show', $project) }}"
               class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                Kembali ke Proyek
            </a>
        </div>
    </div>

    @if ($error !== null)
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ $error }}
        </div>
    @elseif ($analysis === null || $analysis['activities'] === [])
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-600">
            Belum ada tugas pada proyek ini.
            <a href="{{ route('projects.tasks.create', $project) }}" class="font-medium text-indigo-600 hover:underline">Tambahkan tugas pertama</a>
            untuk menganalisis jalur kritis.
        </div>
    @else
        {{-- Tabel aktivitas --}}
        <div class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-900">Tabel Aktivitas</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Kode</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Aktivitas</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Durasi (hari)</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Predecessor</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">ES</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">EF</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">LS</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">LF</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Slack</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($analysis['activities'] as $activity)
                            <tr class="{{ $activity['is_critical'] ? 'bg-red-50' : 'hover:bg-slate-50' }}">
                                <td class="px-4 py-3 font-semibold {{ $activity['is_critical'] ? 'text-red-700' : 'text-slate-900' }}">{{ $activity['code'] }}</td>
                                <td class="px-4 py-3 {{ $activity['is_critical'] ? 'font-medium text-red-800' : 'text-slate-900' }}">{{ $activity['name'] }}</td>
                                <td class="px-4 py-3 text-right {{ $activity['is_critical'] ? 'text-red-800' : 'text-slate-600' }}">{{ $activity['duration'] }}</td>
                                <td class="px-4 py-3 {{ $activity['is_critical'] ? 'text-red-700' : 'text-slate-600' }}">
                                    {{ $activity['predecessors'] === [] ? '-' : implode(', ', $activity['predecessors']) }}
                                </td>
                                <td class="px-4 py-3 text-right {{ $activity['is_critical'] ? 'text-red-700' : 'text-slate-600' }}">{{ $activity['es'] }}</td>
                                <td class="px-4 py-3 text-right {{ $activity['is_critical'] ? 'text-red-700' : 'text-slate-600' }}">{{ $activity['ef'] }}</td>
                                <td class="px-4 py-3 text-right {{ $activity['is_critical'] ? 'text-red-700' : 'text-slate-600' }}">{{ $activity['ls'] }}</td>
                                <td class="px-4 py-3 text-right {{ $activity['is_critical'] ? 'text-red-700' : 'text-slate-600' }}">{{ $activity['lf'] }}</td>
                                <td class="px-4 py-3 text-right {{ $activity['is_critical'] ? 'font-semibold text-red-700' : 'text-slate-600' }}">{{ $activity['slack'] }}</td>
                                <td class="px-4 py-3">
                                    @if ($activity['is_critical'])
                                        <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">Kritis</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Biasa</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Critical path & durasi proyek --}}
        <div class="mb-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Critical Path (jalur terpanjang)</p>
                @if ($analysis['critical'] !== null)
                    <p class="mt-2 text-lg font-semibold text-red-900">{{ implode(' -> ', $analysis['critical']['codes']) }}</p>
                    <p class="mt-1 text-sm text-red-700">Total {{ $analysis['critical']['total'] }} hari</p>
                @else
                    <p class="mt-2 text-sm text-red-700">Tidak ada jalur yang bisa dihitung.</p>
                @endif
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Durasi Proyek</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $analysis['duration'] }} hari</p>
                <p class="mt-1 text-xs text-slate-500">Jumlah aktivitas: {{ count($analysis['activities']) }} &middot; Jumlah jalur: {{ count($analysis['paths']) }}</p>
            </div>
        </div>

        {{-- Daftar semua jalur --}}
        <div class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-900">Semua Jalur (Awal &rarr; Akhir)</h2>
                <p class="mt-0.5 text-xs text-slate-500">Diurutkan dari total durasi terpanjang; jalur teratas adalah critical path.</p>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($analysis['paths'] as $index => $path)
                    @php($isCritical = $index === 0)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 {{ $isCritical ? 'bg-red-50' : '' }}">
                        <span class="text-sm {{ $isCritical ? 'font-semibold text-red-900' : 'text-slate-700' }}">
                            {{ implode(' -> ', $path['codes']) }}
                        </span>
                        <span class="inline-flex items-center gap-2">
                            @if ($isCritical)
                                <span class="inline-flex items-center rounded-full bg-red-600 px-2.5 py-0.5 text-xs font-semibold text-white">Critical Path</span>
                            @endif
                            <span class="text-sm font-semibold {{ $isCritical ? 'text-red-800' : 'text-slate-600' }}">{{ $path['total'] }} hari</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Simulasi keterlambatan --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-900">Simulasi Keterlambatan</h2>
                <p class="mt-0.5 text-xs text-slate-500">Pilih aktivitas dan tambah hari keterlambatan untuk melihat durasi proyek baru.</p>
            </div>

            <form method="GET" action="{{ route('projects.criticalPath', $project) }}" class="flex flex-wrap items-end gap-4 px-4 py-4">
                <div>
                    <label for="activity" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Aktivitas</label>
                    <select id="activity" name="activity"
                            class="w-64 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        @foreach ($analysis['activities'] as $activity)
                            <option value="{{ $activity['id'] }}"
                                @selected(request('activity') == $activity['id'])>
                                {{ $activity['code'] }} - {{ $activity['name'] }} ({{ $activity['duration'] }} hari)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="days" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Tambahan Hari</label>
                    <input type="number" id="days" name="days" min="0" max="365" step="1" value="{{ old('days', request('days', 0)) }}"
                           class="w-32 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                </div>

                <button type="submit"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                    Simulasikan
                </button>
            </form>

            @if ($simulation !== null)
                <div class="border-t border-slate-200 px-4 py-4">
                    <div class="rounded-lg border {{ $simulation['new_duration'] > $simulation['base_duration'] ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-slate-50' }} px-4 py-3 text-sm">
                        <p class="font-medium text-slate-900">
                            Hasil simulasi: aktivitas
                            <span class="font-semibold">{{ $simulation['activity']['code'] }} - {{ $simulation['activity']['name'] }}</span>
                            terlambat <span class="font-semibold">{{ $simulation['extra_days'] }} hari</span>.
                        </p>
                        <p class="mt-2 text-slate-700">
                            Durasi proyek:
                            <span class="font-semibold text-slate-900">{{ $simulation['base_duration'] }} hari</span>
                            &rarr;
                            <span class="font-semibold {{ $simulation['new_duration'] > $simulation['base_duration'] ? 'text-amber-700' : 'text-slate-900' }}">{{ $simulation['new_duration'] }} hari</span>
                        </p>
                        @if ($simulation['new_critical'] !== null)
                            <p class="mt-1 text-slate-700">
                                Jalur kritis baru:
                                <span class="font-semibold">{{ implode(' -> ', $simulation['new_critical']['codes']) }}</span>
                                ({{ $simulation['new_critical']['total'] }} hari)
                            </p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif
@endsection
