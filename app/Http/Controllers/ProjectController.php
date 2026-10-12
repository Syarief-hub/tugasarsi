<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Services\PertService;
use App\Services\SchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /**
     * Menampilkan daftar semua proyek.
     */
    public function index(): View
    {
        $projects = Project::withCount(['tasks', 'resources'])->latest()->get();

        return view('projects.index', ['projects' => $projects]);
    }

    /**
     * Menampilkan form pembuatan proyek baru.
     */
    public function create(): View
    {
        return view('projects.create', ['project' => new Project()]);
    }

    /**
     * Menyimpan proyek baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $labels = [
            'name' => 'Nama proyek',
            'organization' => 'Organisasi',
            'website' => 'Website',
            'description' => 'Deskripsi',
        ];

        $data = $request->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'organization' => ['nullable', 'string', 'max:255'],
                'website' => ['nullable', 'string', 'max:255', 'url'],
                'description' => ['nullable', 'string'],
            ],
            $this->indonesianMessages($labels)
        );

        $project = Project::create($data);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Proyek berhasil dibuat.');
    }

    /**
     * Menampilkan detail satu proyek beserta tugas dan resource-nya.
     */
    public function show(Project $project): View
    {
        $project->load([
            'tasks' => fn ($query) => $query->orderBy('start_date')->orderBy('id'),
            'resources',
        ]);

        return view('projects.show', [
            'project' => $project,
            'tasks' => $project->tasks,
            'resources' => $project->resources,
        ]);
    }

    /**
     * Halaman Gantt chart per proyek.
     *
     * Daftar tugas (berhirarki) di kiri dan timeline SVG yang dirender
     * server-side di kanan: bar berwarna sesuai warna tugas, kolom
     * Sabtu-Minggu diarsir abu-abu, hari libur diberi penanda merah,
     * panah dependensi (FS/SS/FF/SF), serta penanda jalur kritis (CPM)
     * yang bisa di-show/hide lewat JavaScript tanpa reload.
     */
    public function gantt(Project $project, SchedulingService $scheduling): View
    {
        $tasks = $project->tasks()
            ->with(['resources', 'dependencies'])
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        // Baris datar berhirarki: tugas tingkat atas lalu sub-tugasnya.
        $children = $tasks->groupBy(fn (Task $task) => (string) $task->parent_id);
        $rows = [];
        $walk = function ($parentId, int $depth) use (&$walk, $children, &$rows): void {
            foreach ($children->get((string) $parentId, collect()) as $task) {
                $rows[] = ['task' => $task, 'depth' => $depth];
                $walk($task->getKey(), $depth + 1);
            }
        };
        $walk('', 0);

        // Pastikan tugas yatim (induk di luar proyek) tetap tampil.
        $visited = array_map(fn (array $row) => (int) $row['task']->getKey(), $rows);
        foreach ($tasks as $task) {
            if (! in_array((int) $task->getKey(), $visited, true)) {
                $rows[] = ['task' => $task, 'depth' => 0];
            }
        }

        $keyed = $tasks->keyBy(fn (Task $task) => (int) $task->getKey());

        // Jalur kritis dari CPM; gagal (siklus) berarti tanpa penanda kritis.
        try {
            $cpm = $scheduling->criticalPath($project);
        } catch (ValidationException) {
            $cpm = ['tasks' => []];
        }

        // Tugas kritis = tugas yang akar tingkat atasnya berada di jalur kritis.
        foreach ($rows as &$row) {
            $node = $row['task'];
            $guard = 0;

            while ($node->parent_id !== null
                && $keyed->has((int) $node->parent_id)
                && $guard++ < 50
            ) {
                $node = $keyed->get((int) $node->parent_id);
            }

            $row['is_critical'] = (bool) ($cpm['tasks'][(int) $node->getKey()]['is_critical'] ?? false);
        }
        unset($row);

        // Rentang timeline dari tanggal mulai paling awal sampai selesai terakhir.
        $minDate = null;
        $maxDate = null;

        foreach ($rows as $row) {
            $task = $row['task'];

            if ($minDate === null || $task->start_date->lt($minDate)) {
                $minDate = $task->start_date->copy();
            }

            $end = $task->end_date;

            if ($end !== null && ($maxDate === null || $end->gt($maxDate))) {
                $maxDate = $end->copy();
            }
        }

        // Geometri dasar SVG ( piksel ).
        $dayWidth = 18;
        $rowHeight = 32;
        $headerHeight = 40;

        $days = [];
        $dayIndex = [];

        if ($minDate !== null && $maxDate !== null) {
            $index = 0;

            for ($date = $minDate->copy(); $date->lte($maxDate); $date->addDay()) {
                $dayIndex[$date->format('Y-m-d')] = $index;
                $days[] = [
                    'date' => $date->copy(),
                    'weekend' => $project->weekend_mode !== 'work'
                        && in_array((int) $date->format('w'), [0, 6], true),
                    'holiday' => $project->holiday_mode !== 'work'
                        && $scheduling->isHoliday($date),
                ];
                $index++;
            }
        }

        $svgWidth = max(count($days) * $dayWidth, 1);
        $svgHeight = $headerHeight + count($rows) * $rowHeight;

        // Posisi bar tiap tugas.
        foreach ($rows as $index => &$row) {
            $task = $row['task'];
            $startIdx = $dayIndex[$task->start_date->format('Y-m-d')] ?? 0;
            $end = $task->end_date;
            $endIdx = $end !== null ? ($dayIndex[$end->format('Y-m-d')] ?? $startIdx) : $startIdx;

            $row['bar_x'] = $startIdx * $dayWidth;
            $row['bar_width'] = max(($endIdx - $startIdx + 1) * $dayWidth, $dayWidth);
            $row['y'] = $headerHeight + $index * $rowHeight;
        }
        unset($row);

        // Panah dependensi antar bar sesuai tipe (FS, SS, FF, SF).
        $rowOf = [];

        foreach ($rows as $index => $row) {
            $rowOf[(int) $row['task']->getKey()] = $index;
        }

        $arrows = [];

        foreach ($rows as $index => $row) {
            $task = $row['task'];

            foreach ($task->dependencies as $dependency) {
                $from = $rowOf[(int) $dependency->predecessor_id] ?? null;

                if ($from === null || $from === $index) {
                    continue;
                }

                $predecessor = $rows[$from];
                $predRight = $predecessor['bar_x'] + $predecessor['bar_width'];
                $taskRight = $row['bar_x'] + $row['bar_width'];

                [$fromX, $toX] = match ($dependency->type) {
                    'SS' => [$predecessor['bar_x'], $row['bar_x']],
                    'FF' => [$predRight, $taskRight],
                    'SF' => [$predecessor['bar_x'], $taskRight],
                    default => [$predRight, $row['bar_x']], // FS
                };

                $fromY = $predecessor['y'] + $rowHeight / 2;
                $toY = $row['y'] + $rowHeight / 2;
                $midX = (int) (($fromX + $toX) / 2);

                $arrows[] = [
                    'path' => sprintf('M %d %d H %d V %d H %d', $fromX, $fromY, $midX, $toY, $toX),
                    'label_x' => $midX,
                    'label_y' => (int) (($fromY + $toY) / 2) - 4,
                    'label' => $dependency->type
                        . ((int) $dependency->delay > 0 ? '+' . (int) $dependency->delay : ''),
                    'is_critical' => $predecessor['is_critical'] && $row['is_critical'],
                ];
            }
        }

        return view('projects.gantt', [
            'project' => $project,
            'rows' => $rows,
            'days' => $days,
            'arrows' => $arrows,
            'svgWidth' => $svgWidth,
            'svgHeight' => $svgHeight,
            'dayWidth' => $dayWidth,
            'rowHeight' => $rowHeight,
            'headerHeight' => $headerHeight,
            'hasCritical' => array_column($rows, 'is_critical') !== []
                && in_array(true, array_column($rows, 'is_critical'), true),
            'critical' => $cpm['critical'] ?? [],
        ]);
    }

    /**
     * Halaman PERT chart per proyek.
     *
     * Setiap tugas menjadi node (kode, nama, durasi, mulai/selesai) yang
     * dirender sebagai SVG server-side tanpa library; panah mengikuti
     * dependensi, dan node pada jalur kritis (slack 0 dari CPM manual)
     * diberi warna merah.
     */
    public function pert(Project $project, PertService $pert): View
    {
        $error = null;
        $analysis = null;

        try {
            $analysis = $pert->analyze($project);
        } catch (ValidationException $exception) {
            $error = implode(' ', Arr::flatten($exception->errors()));
        }

        $tasks = $project->tasks()
            ->with('dependencies')
            ->orderBy('id')
            ->get()
            ->keyBy(fn (Task $task) => (int) $task->getKey());

        // Layer node = jarak dependensi terpanjang dari node awal.
        $preds = [];

        foreach ($tasks as $task) {
            $preds[(int) $task->getKey()] = $task->dependencies
                ->pluck('predecessor_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $tasks->has($id))
                ->values()
                ->all();
        }

        $layer = [];

        foreach ($analysis['order'] ?? [] as $id) {
            $layer[$id] = 0;

            foreach ($preds[$id] ?? [] as $from) {
                $layer[$id] = max($layer[$id], ($layer[$from] ?? 0) + 1);
            }
        }

        // Geometri dasar node & kanvas.
        $boxWidth = 172;
        $boxHeight = 68;
        $gapX = 84;
        $gapY = 30;
        $padding = 26;

        $columns = [];
        foreach ($layer as $id => $level) {
            $columns[$level][] = $id;
        }

        $maxLayer = $columns === [] ? 0 : max(array_keys($columns));
        $maxRows = $columns === [] ? 0 : max(array_map('count', $columns));

        $svgWidth = $maxLayer * ($boxWidth + $gapX) + $boxWidth + $padding * 2;
        $svgHeight = max(1, $maxRows) * ($boxHeight + $gapY) - $gapY + $padding * 2;

        $nodes = [];
        $position = [];

        foreach ($columns as $level => $ids) {
            foreach (array_values($ids) as $index => $id) {
                $task = $tasks->get($id);
                $activity = $analysis['activities'][$id] ?? null;

                $x = $padding + $level * ($boxWidth + $gapX);
                $y = $padding + $index * ($boxHeight + $gapY);

                $position[$id] = [
                    'x' => $x,
                    'y' => $y,
                    'center_y' => $y + $boxHeight / 2,
                ];

                $nodes[] = [
                    'task' => $task,
                    'x' => $x,
                    'y' => $y,
                    'code' => $activity['code'] ?? '?',
                    'is_critical' => (bool) ($activity['is_critical'] ?? false),
                    'label' => mb_substr($task->name, 0, 22),
                    'start' => $task->start_date?->format('d/m'),
                    'end' => $task->end_date?->format('d/m'),
                ];
            }
        }

        // Panah: dari tepi kanan predecessor ke tepi kiri successor.
        $arrows = [];

        foreach ($tasks as $id => $task) {
            foreach ($preds[$id] ?? [] as $from) {
                if (! isset($position[$from], $position[$id])) {
                    continue;
                }

                $x1 = $position[$from]['x'] + $boxWidth;
                $y1 = $position[$from]['center_y'];
                $x2 = $position[$id]['x'];
                $y2 = $position[$id]['center_y'];
                $midX = (int) (($x1 + $x2) / 2);

                $dependency = $task->dependencies
                    ->first(fn ($dep) => (int) $dep->predecessor_id === $from);

                $label = $dependency !== null && ($dependency->type !== 'FS' || (int) $dependency->delay > 0)
                    ? $dependency->type . ((int) $dependency->delay > 0 ? '+' . (int) $dependency->delay : '')
                    : null;

                $arrows[] = [
                    'path' => sprintf('M %d %d H %d V %d H %d', $x1, $y1, $midX, $y2, $x2),
                    'label' => $label,
                    'label_x' => $midX,
                    'label_y' => (int) (($y1 + $y2) / 2) - 4,
                    'is_critical' => ($analysis['activities'][$id]['is_critical'] ?? false)
                        && ($analysis['activities'][$from]['is_critical'] ?? false),
                ];
            }
        }

        return view('projects.pert', [
            'project' => $project,
            'error' => $error,
            'nodes' => $nodes,
            'arrows' => $arrows,
            'svgWidth' => $svgWidth,
            'svgHeight' => $svgHeight,
            'boxWidth' => $boxWidth,
            'boxHeight' => $boxHeight,
            'hasCritical' => in_array(true, array_column($nodes, 'is_critical'), true),
        ]);
    }

    /**
     * Halaman analisis jalur kritis manual (PERT/CPM).
     *
     * Menampilkan tabel aktivitas (kode, durasi, predecessor), semua jalur
     * dari awal ke akhir beserta totalnya, jalur terpanjang sebagai critical
     * path beserta durasi proyek, serta form simulasi keterlambatan
     * (aktivitas + tambahan hari) lewat parameter query GET.
     */
    public function criticalPath(Request $request, Project $project, PertService $pert): View|RedirectResponse
    {
        try {
            $analysis = $pert->analyze($project);
        } catch (ValidationException $exception) {
            return view('projects.critical-path', [
                'project' => $project,
                'analysis' => null,
                'simulation' => null,
                'error' => implode(' ', Arr::flatten($exception->errors())),
            ]);
        }

        $simulation = null;

        if ($request->filled('activity') || $request->filled('days')) {
            $data = $request->validate(
                [
                    'activity' => ['required', 'integer', Rule::exists('tasks', 'id')->where('project_id', $project->getKey())],
                    'days' => ['required', 'integer', 'min:0', 'max:365'],
                ],
                $this->indonesianMessages([
                    'activity' => 'Aktivitas',
                    'days' => 'Tambahan hari',
                ])
            );

            $simulation = $pert->simulate($project, (int) $data['activity'], (int) $data['days']);
        }

        return view('projects.critical-path', [
            'project' => $project,
            'analysis' => $analysis,
            'simulation' => $simulation,
            'error' => null,
        ]);
    }

    /**
     * Menampilkan form edit proyek.
     */
    public function edit(Project $project): View
    {
        return view('projects.edit', ['project' => $project]);
    }

    /**
     * Memperbarui proyek.
     */
    public function update(Request $request, Project $project): RedirectResponse
    {
        $labels = [
            'name' => 'Nama proyek',
            'organization' => 'Organisasi',
            'website' => 'Website',
            'description' => 'Deskripsi',
        ];

        $data = $request->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'organization' => ['nullable', 'string', 'max:255'],
                'website' => ['nullable', 'string', 'max:255', 'url'],
                'description' => ['nullable', 'string'],
            ],
            $this->indonesianMessages($labels)
        );

        $project->update($data);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Proyek berhasil diperbarui.');
    }

    /**
     * Menjadwalkan ulang seluruh tugas proyek berdasarkan dependensi,
     * lalu menampilkan jalur kritis (CPM).
     */
    public function schedule(Project $project, SchedulingService $scheduling): RedirectResponse
    {
        try {
            $result = $scheduling->reschedule($project);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('projects.show', $project)
                ->withErrors($exception->errors());
        }

        $message = sprintf('%d tugas berhasil dijadwalkan ulang.', $result['rescheduled']);

        if ($result['critical'] !== []) {
            $message .= sprintf(
                ' Jalur kritis: %s (%d hari kerja).',
                implode(' -> ', $result['critical']),
                $result['duration']
            );
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('success', $message);
    }

    /**
     * Menghapus proyek beserta tugas, resource, dan dependensinya.
     */
    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Proyek berhasil dihapus.');
    }
}
