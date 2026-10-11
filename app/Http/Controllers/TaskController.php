<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
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
            'predecessorOptions' => $this->predecessorOptions($project),
            'dependencies' => collect(),
        ]);
    }

    /**
     * Menyimpan tugas baru ke dalam proyek beserta dependensinya.
     */
    public function store(TaskRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $dependencies = $data['dependencies'] ?? [];
        unset($data['dependencies']);

        $data['parent_id'] = $data['parent_id'] ?? null;

        if ($task = $project->tasks()->create($data)) {
            $this->syncDependencies($task, $dependencies);
        }

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
     * Menampilkan form edit tugas termasuk dependensi yang tersimpan.
     */
    public function edit(Project $project, Task $task): View
    {
        return view('tasks.edit', [
            'project' => $project,
            'task' => $task,
            'parents' => $this->parentOptions($project, $task),
            'predecessorOptions' => $this->predecessorOptions($project, $task),
            'dependencies' => $task->dependencies()->orderBy('id')->get(),
        ]);
    }

    /**
     * Memperbarui tugas beserta dependensinya (sinkron penuh dengan input form).
     */
    public function update(TaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        $data = $request->validated();
        $dependencies = $data['dependencies'] ?? [];
        unset($data['dependencies']);

        $data['parent_id'] = $data['parent_id'] ?? null;

        if ((int) $data['parent_id'] === (int) $task->getKey()) {
            $data['parent_id'] = null;
        }

        $task->update($data);
        $this->syncDependencies($task, $dependencies);

        return redirect()
            ->route('projects.tasks.index', $project)
            ->with('success', 'Tugas berhasil diperbarui.');
    }

    /**
     * Menghapus tugas (dependensi turut terhapus lewat constraint onDelete cascade).
     */
    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $task->delete();

        return redirect()
            ->route('projects.tasks.index', $project)
            ->with('success', 'Tugas berhasil dihapus.');
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

    /**
     * Daftar predecessor yang boleh dipilih: tugas lain dalam proyek yang sama.
     *
     * @return Collection<int, Task>
     */
    private function predecessorOptions(Project $project, ?Task $task = null): Collection
    {
        return $project->tasks()
            ->when($task, fn ($query) => $query->whereKeyNot($task->getKey()))
            ->orderBy('start_date')
            ->orderBy('id')
            ->get(['id', 'name', 'start_date', 'duration', 'parent_id']);
    }

    /**
     * Menyinkronkan baris dependensi dari form dengan tabel task_dependencies.
     * Baris lama yang tidak ada di form dihapus, baris baru ditambahkan, dan
     * baris lama yang berubah diperbarui.
     *
     * @param  array<int, array{predecessor_id: string, type: string, delay: int|string}>  $rows
     */
    private function syncDependencies(Task $task, array $rows): void
    {
        $sync = [];

        foreach ($rows as $row) {
            $predecessorId = (int) ($row['predecessor_id'] ?? 0);

            if ($predecessorId <= 0) {
                continue;
            }

            $sync[$predecessorId] = [
                'type' => $row['type'],
                'delay' => (int) ($row['delay'] ?? 0),
            ];
        }

        // predecessors() adalah BelongsToMany dengan pivot type/delay,
        // kunci array = id predecessor sehingga sinkron penuh dengan form.
        $task->predecessors()->sync($sync);
    }
}
