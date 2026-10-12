@extends('layouts.app')

@section('title', 'Gantt Chart - ' . $project->name)

@section('content')
    @php
        $hasHoliday = collect($days)->contains('holiday', true);
        $hasWeekend = collect($days)->contains('weekend', true);
    @endphp

    {{-- Gaya khusus SVG Gantt; mode jalur kritis di-toggle lewat JavaScript. --}}
    <style>
        #gantt-svg .gantt-bar {
            transition: fill 150ms ease, opacity 150ms ease;
        }

        #gantt-svg.critical-mode .gantt-bar.is-critical {
            fill: #dc2626;
            stroke: #991b1b;
        }

        #gantt-svg.critical-mode .gantt-bar:not(.is-critical) {
            opacity: 0.35;
        }

        #gantt-svg.critical-mode .dep-arrow:not(.is-critical) {
            opacity: 0.25;
        }
    </style>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">
                <a href="{{ route('projects.index') }}" class="hover:text-indigo-600">Proyek</a>
                <span class="mx-1">/</span>
                <a href="{{ route('projects.show', $project) }}" class="hover:text-indigo-600">{{ $project->name }}</a>
                <span class="mx-1">/</span> Gantt Chart
            </p>
            <h1 class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Gantt Chart: {{ $project->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                Bar dirender server-side sebagai SVG &middot; durasi dalam hari kerja
                (Sabtu-Minggu{{ $hasHoliday ? ' dan hari libur' : '' }} dilewati).
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($hasCritical)
                <button type="button" id="toggle-critical"
                        class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                    Tampilkan Jalur Kritis
                </button>
            @endif
            <a href="{{ route('projects.show', $project) }}"
               class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                Kembali ke Proyek
            </a>
        </div>
    </div>

    @if ($critical !== [])
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <span class="font-medium">Jalur kritis (CPM):</span> {{ implode(' -> ', $critical) }}
        </div>
    @endif

    @if (count($rows) === 0)
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-600">
            Belum ada tugas pada proyek ini.
            <a href="{{ route('projects.tasks.create', $project) }}" class="font-medium text-indigo-600 hover:underline">Tambahkan tugas pertama</a>
            untuk melihat timeline Gantt.
        </div>
    @else
        {{-- Legenda --}}
        <div class="mb-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-slate-600">
            @if ($hasWeekend)
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded-sm border border-slate-300 bg-slate-200"></span>
                    Sabtu-Minggu
                </span>
            @endif
            @if ($hasHoliday)
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded-sm border border-red-200 bg-red-100"></span>
                    Hari libur proyek
                </span>
            @endif
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-3 w-3 rounded-sm bg-red-600"></span>
                Bar kritis (toggle)
            </span>
            <span>Panah dependensi: FS, SS, FF, SF (+ delay dalam hari kerja)</span>
        </div>

        <div class="flex overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            {{-- Daftar tugas di kiri --}}
            <div class="w-64 shrink-0 border-r border-slate-200 sm:w-72">
                <div class="flex h-10 items-center border-b border-slate-200 bg-slate-50 px-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Tugas &amp; Resource
                </div>

                @foreach ($rows as $row)
                    @php($task = $row['task'])
                    <div class="flex h-8 items-center gap-2 border-b border-slate-100 pr-3"
                         style="padding-left: {{ 12 + $row['depth'] * 14 }}px"
                         title="{{ $task->name }}{{ $task->resources->isNotEmpty() ? ' - ' . $task->resources->pluck('name')->implode(', ') : '' }}">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $task->color }}"></span>
                        <span class="truncate text-xs font-medium text-slate-800">{{ $task->name }}</span>
                        @if ($task->resources->isNotEmpty())
                            <span class="hidden truncate text-[10px] text-slate-400 lg:inline">{{ $task->resources->pluck('name')->implode(', ') }}</span>
                        @endif
                        <span class="ml-auto shrink-0 text-[10px] text-slate-500">{{ $task->duration }} hr</span>
                    </div>
                @endforeach
            </div>

            {{-- Timeline SVG di kanan --}}
            <div class="min-w-0 flex-1 overflow-x-auto">
                <svg id="gantt-svg" xmlns="http://www.w3.org/2000/svg"
                     width="{{ $svgWidth }}" height="{{ $svgHeight }}"
                     viewBox="0 0 {{ $svgWidth }} {{ $svgHeight }}"
                     role="img" aria-label="Gantt chart {{ $project->name }}">
                    <defs>
                        <marker id="gantt-arrowhead" viewBox="0 0 10 10" refX="9" refY="5"
                                markerWidth="6" markerHeight="6" orient="auto">
                            <path d="M0,0 L10,5 L0,10 z" fill="#64748b"></path>
                        </marker>
                    </defs>

                    {{-- Latar kolom akhir pekan & hari libur --}}
                    @foreach ($days as $index => $day)
                        @if ($day['weekend'])
                            <rect x="{{ $index * $dayWidth }}" y="{{ $headerHeight }}"
                                  width="{{ $dayWidth }}" height="{{ $svgHeight - $headerHeight }}"
                                  fill="#e2e8f0"></rect>
                        @endif
                        @if ($day['holiday'])
                            <rect x="{{ $index * $dayWidth }}" y="{{ $headerHeight }}"
                                  width="{{ $dayWidth }}" height="{{ $svgHeight - $headerHeight }}"
                                  fill="#fee2e2"></rect>
                        @endif
                    @endforeach

                    {{-- Kepala tanggal --}}
                    <rect x="0" y="0" width="{{ $svgWidth }}" height="{{ $headerHeight }}" fill="#f8fafc"></rect>
                    <line x1="0" y1="{{ $headerHeight }}" x2="{{ $svgWidth }}" y2="{{ $headerHeight }}"
                          stroke="#cbd5e1" stroke-width="1"></line>

                    @php($previousMonth = null)
                    @foreach ($days as $index => $day)
                        @php($x = $index * $dayWidth)
                        @php($monthKey = $day['date']->format('Y-m'))

                        @if ($monthKey !== $previousMonth)
                            @if ($previousMonth !== null)
                                <line x1="{{ $x }}" y1="0" x2="{{ $x }}" y2="{{ $headerHeight }}"
                                      stroke="#cbd5e1" stroke-width="1"></line>
                            @endif
                            <text x="{{ $x + 3 }}" y="13" font-size="10" font-weight="600" fill="#334155"
                                  font-family="sans-serif">{{ $day['date']->format('M Y') }}</text>
                            @php($previousMonth = $monthKey)
                        @endif

                        <text x="{{ $x + $dayWidth / 2 }}" y="33" text-anchor="middle" font-size="9"
                              fill="{{ $day['weekend'] ? '#94a3b8' : '#64748b' }}"
                              font-family="sans-serif">{{ $day['date']->format('j') }}</text>

                        @if ($day['holiday'])
                            <polygon points="{{ $x + $dayWidth / 2 }},17 {{ $x + $dayWidth / 2 - 4 }},24 {{ $x + $dayWidth / 2 + 4 }},24"
                                     fill="#dc2626">
                                <title>Hari libur: {{ $day['date']->format('d/m/Y') }}</title>
                            </polygon>
                        @endif
                    @endforeach

                    {{-- Garis pemisah baris --}}
                    @foreach ($rows as $index => $row)
                        <line x1="0" y1="{{ $headerHeight + ($index + 1) * $rowHeight }}"
                              x2="{{ $svgWidth }}" y2="{{ $headerHeight + ($index + 1) * $rowHeight }}"
                              stroke="#f1f5f9" stroke-width="1"></line>
                    @endforeach

                    {{-- Bar tugas --}}
                    @foreach ($rows as $row)
                        @php($task = $row['task'])
                        @php($endDate = $task->end_date)
                        <rect class="gantt-bar {{ $row['is_critical'] ? 'is-critical' : '' }}"
                              x="{{ $row['bar_x'] + 1 }}" y="{{ $row['y'] + 6 }}"
                              width="{{ max($row['bar_width'] - 2, 4) }}" height="{{ $rowHeight - 12 }}"
                              rx="4" fill="{{ $task->color }}" stroke="rgba(0,0,0,0.15)" stroke-width="1">
                            <title>{{ $task->name }} ({{ $task->start_date->format('d/m/Y') }} - {{ $endDate?->format('d/m/Y') }}, {{ $task->duration }} hari kerja){{ $row['is_critical'] ? ' - KRITIS' : '' }}</title>
                        </rect>
                        @if ($row['bar_width'] >= 44)
                            <text x="{{ $row['bar_x'] + $row['bar_width'] / 2 }}" y="{{ $row['y'] + $rowHeight / 2 + 3 }}"
                                  text-anchor="middle" font-size="9" font-weight="600" fill="#ffffff"
                                  font-family="sans-serif" pointer-events="none">{{ $task->duration }} hr</text>
                        @endif
                    @endforeach

                    {{-- Panah dependensi --}}
                    @foreach ($arrows as $arrow)
                        <path class="dep-arrow {{ $arrow['is_critical'] ? 'is-critical' : '' }}"
                              d="{{ $arrow['path'] }}" fill="none" stroke="#64748b"
                              stroke-width="1.2" marker-end="url(#gantt-arrowhead)"></path>
                        <text x="{{ $arrow['label_x'] }}" y="{{ $arrow['label_y'] }}" text-anchor="middle"
                              font-size="9" fill="#475569" font-family="sans-serif"
                              style="paint-order: stroke; stroke: #ffffff; stroke-width: 3px;">{{ $arrow['label'] }}</text>
                    @endforeach
                </svg>
            </div>
        </div>
    @endif

    @if ($hasCritical)
        <script>
            (function () {
                var svg = document.getElementById('gantt-svg');
                var button = document.getElementById('toggle-critical');

                if (!svg || !button) {
                    return;
                }

                button.addEventListener('click', function () {
                    var active = svg.classList.toggle('critical-mode');

                    button.textContent = active ? 'Sembunyikan Jalur Kritis' : 'Tampilkan Jalur Kritis';
                    button.classList.toggle('border-red-600', active);
                    button.classList.toggle('bg-red-600', active);
                    button.classList.toggle('text-white', active);
                    button.classList.toggle('border-slate-300', !active);
                    button.classList.toggle('bg-white', !active);
                    button.classList.toggle('text-slate-700', !active);
                });
            })();
        </script>
    @endif
@endsection
