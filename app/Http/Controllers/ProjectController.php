<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Services\SchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
