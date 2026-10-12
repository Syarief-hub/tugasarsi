@extends('layouts.app')

@section('title', 'PERT Chart - ' . $project->name)

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">
                <a href="{{ route('projects.index') }}" class="hover:text-indigo-600">Proyek</a>
                <span class="mx-1">/</span>
                <a href="{{ route('projects.show', $project) }}" class="hover:text-indigo-600">{{ $project->name }}</a>
                <span class="mx-1">/</span> PERT Chart
            </p>
            <h1 class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">PERT Chart: {{ $project->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                Node tugas dirender server-side sebagai SVG (tanpa library).
                Durasi dalam hari; node jalur kritis ditandai merah.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('projects.criticalPath', $project) }}"
               class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                Analisis Jalur Kritis
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
    @endif

    @if ($nodes === [])
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-600">
            Belum ada tugas pada proyek ini.
            <a href="{{ route('projects.tasks.create', $project) }}" class="font-medium text-indigo-600 hover:underline">Tambahkan tugas pertama</a>
            untuk melihat PERT chart.
        </div>
    @else
        @if ($hasCritical)
            <div class="mb-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-slate-600">
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded-sm bg-red-600"></span>
                    Node jalur kritis (slack 0)
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded-sm bg-indigo-500"></span>
                    Node biasa
                </span>
                <span>Panah = dependensi antar tugas (label tipe + delay bila bukan FS+0)</span>
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto p-4">
                <svg xmlns="http://www.w3.org/2000/svg"
                     width="{{ $svgWidth }}" height="{{ $svgHeight }}"
                     viewBox="0 0 {{ $svgWidth }} {{ $svgHeight }}"
                     role="img" aria-label="PERT chart {{ $project->name }}">
                    <defs>
                        <marker id="pert-arrowhead" viewBox="0 0 10 10" refX="9" refY="5"
                                markerWidth="6" markerHeight="6" orient="auto">
                            <path d="M0,0 L10,5 L0,10 z" fill="#64748b"></path>
                        </marker>
                        <marker id="pert-arrowhead-critical" viewBox="0 0 10 10" refX="9" refY="5"
                                markerWidth="6" markerHeight="6" orient="auto">
                            <path d="M0,0 L10,5 L0,10 z" fill="#dc2626"></path>
                        </marker>
                    </defs>

                    {{-- Panah dependensi --}}
                    @foreach ($arrows as $arrow)
                        <path class="pert-arrow {{ $arrow['is_critical'] ? 'is-critical' : '' }}"
                              d="{{ $arrow['path'] }}" fill="none"
                              stroke="{{ $arrow['is_critical'] ? '#dc2626' : '#64748b' }}" stroke-width="1.4"
                              marker-end="url({{ $arrow['is_critical'] ? '#pert-arrowhead-critical' : '#pert-arrowhead' }})"></path>
                        @if ($arrow['label'] !== null)
                            <text x="{{ $arrow['label_x'] }}" y="{{ $arrow['label_y'] }}" text-anchor="middle"
                                  font-size="9" fill="{{ $arrow['is_critical'] ? '#b91c1c' : '#475569' }}" font-family="sans-serif"
                                  style="paint-order: stroke; stroke: #ffffff; stroke-width: 3px;">{{ $arrow['label'] }}</text>
                        @endif
                    @endforeach

                    {{-- Node tugas --}}
                    @foreach ($nodes as $node)
                        @php($task = $node['task'])
                        <g>
                            <rect x="{{ $node['x'] }}" y="{{ $node['y'] }}"
                                  width="{{ $boxWidth }}" height="{{ $boxHeight }}" rx="8"
                                  fill="{{ $node['is_critical'] ? '#dc2626' : '#eef2ff' }}"
                                  stroke="{{ $node['is_critical'] ? '#991b1b' : '#6366f1' }}" stroke-width="1.5">
                                <title>{{ $task->name }} ({{ $task->duration }} hari){{ $node['is_critical'] ? ' - KRITIS' : '' }}</title>
                            </rect>

                            {{-- Kode aktivitas --}}
                            <text x="{{ $node['x'] + 10 }}" y="{{ $node['y'] + 17 }}"
                                  font-size="11" font-weight="700" font-family="sans-serif"
                                  fill="{{ $node['is_critical'] ? '#ffffff' : '#4338ca' }}">{{ $node['code'] }}</text>

                            {{-- Nama tugas --}}
                            <text x="{{ $node['x'] + $boxWidth - 10 }}" y="{{ $node['y'] + 17 }}"
                                  text-anchor="end" font-size="10" font-weight="600" font-family="sans-serif"
                                  fill="{{ $node['is_critical'] ? '#ffffff' : '#1e293b' }}">{{ $node['label'] }}</text>

                            {{-- Durasi --}}
                            <text x="{{ $node['x'] + 10 }}" y="{{ $node['y'] + 40 }}"
                                  font-size="10" font-family="sans-serif"
                                  fill="{{ $node['is_critical'] ? '#fee2e2' : '#475569' }}">Durasi: {{ $task->duration }} hari</text>

                            {{-- Mulai - selesai --}}
                            @if ($node['start'] !== null)
                                <text x="{{ $node['x'] + 10 }}" y="{{ $node['y'] + 57 }}"
                                      font-size="10" font-family="sans-serif"
                                      fill="{{ $node['is_critical'] ? '#fecaca' : '#64748b' }}">
                                    {{ $node['start'] }} - {{ $node['end'] ?? '?' }}
                                </text>
                            @endif
                        </g>
                    @endforeach
                </svg>
            </div>
        </div>
    @endif
@endsection
