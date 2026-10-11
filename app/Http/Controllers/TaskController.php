<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    /**
     * Menampilkan daftar tugas dalam satu proyek.
     */
    public function index(Project $project): View
    {
        $tasks = $project->tasks()
            ->with(['parent:id,name', 'resources:id,name'])
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        return view('tasks.index', [
            'project' => $project,
            'tasks' => $tasks,
        ]);
    }

    /**
     * Menampilkan form pembuatan tugas baru.
     */
    public function create(Project $project): View
    {
        $task = new Task(['project_id' => $project->id]);

        return view('tasks.create', [
            'project' => $project,
            'task' => $task,
            'parents' => $this->parentOptions($project),
        ]);
    }

    /**
     * Menyimpan tugas baru ke dalam proyek.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validated($request, $project);

        $project->tasks()->create($data);

        return redirect()
            ->route('projects.tasks.index', $project)
            ->with('success', 'Tugas berhasil ditambahkan.');
    }

    /**
     * Menampilkan detail satu tugas.
     */
    public function show(Project $project, Task $task): View
    {
        $task->load([
            'parent:id,name',
            'children:id,parent_id,name,start_date,duration,priority,color',
            'predecessors:id,name,start_date,duration,priority,color',
            'successors:id,name,start_date,duration,priority,color',
            'resources',
        ]);

        return view('tasks.show', [
            'project' => $project,
            'task' => $task,
            'children' => $task->children,
            'predecessors' => $task->predecessors,
            'successors' => $task->successors,
            'availableResources' => $project->resources()->orderBy('name')->get(),
        ]);
    }

    /**
     * Menampilkan form edit tugas.
     */
    public function edit(Project $project, Task $task): View
    {
        return view('tasks.edit', [
            'project' => $project,
            'task' => $task,
            'parents' => $this->parentOptions($project, $task),
        ]);
    }

    /**
     * Memperbarui tugas.
     */
    public function update(Request $request, Project $project, Task $task): RedirectResponse
    {
        $data = $this->validated($request, $project, $task);

        $task->update($data);

        return redirect()
            ->route('projects.tasks.index', $project)
            ->with('success', 'Tugas berhasil diperbarui.');
    }

    /**
     * Menghapus tugas.
     */
    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $task->delete();

        return redirect()
            ->route('projects.tasks.index', $project)
            ->with('success', 'Tugas berhasil dihapus.');
    }

    /**
     * Aturan validasi bersama untuk store dan update.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, Project $project, ?Task $task = null): array
    {
        $labels = [
            'name' => 'Nama tugas',
            'start_date' => 'Tanggal mulai',
            'duration' => 'Durasi (hari)',
            'priority' => 'Prioritas',
            'color' => 'Warna',
            'parent_id' => 'Tugas induk',
        ];

        $data = $request->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'start_date' => ['required', 'date'],
                'duration' => ['required', 'integer', 'min:1', 'max:3650'],
                'priority' => ['required', Rule::in(['rendah', 'normal', 'tinggi'])],
                'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'parent_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('tasks', 'id')->where('project_id', $project->id),
                ],
            ],
            array_merge(
                $this->indonesianMessages($labels),
                [
                    'priority.in' => 'Prioritas harus berupa rendah, normal, atau tinggi.',
                    'color.regex' => 'Warna harus berupa kode heksadesimal, contoh: #3b82f6.',
                ]
            )
        );

        $data['parent_id'] = $data['parent_id'] ?? null;

        if ($task !== null && (int) $data['parent_id'] === (int) $task->id) {
            $data['parent_id'] = null;
        }

        return $data;
    }

    /**
     * Daftar tugas induk yang boleh dipilih (tugas dalam proyek yang sama).
     *
     * @return Collection<int, Task>
     */
    private function parentOptions(Project $project, ?Task $task = null): Collection
    {
        return $project->tasks()
            ->when($task, fn ($query) => $query->whereKeyNot($task->getKey()))
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();
    }
}
