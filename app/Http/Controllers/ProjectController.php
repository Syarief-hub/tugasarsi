<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
