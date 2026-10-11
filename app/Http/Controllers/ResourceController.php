<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Resource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResourceController extends Controller
{
    /**
     * Menampilkan daftar resource dalam satu proyek.
     */
    public function index(Project $project): View
    {
        $resources = $project->resources()
            ->withCount('tasks')
            ->orderBy('name')
            ->get();

        return view('resources.index', [
            'project' => $project,
            'resources' => $resources,
        ]);
    }

    /**
     * Menampilkan form pembuatan resource baru.
     */
    public function create(Project $project): View
    {
        $resource = new Resource(['project_id' => $project->id]);

        return view('resources.create', [
            'project' => $project,
            'resource' => $resource,
        ]);
    }

    /**
     * Menyimpan resource baru ke dalam proyek.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validated($request);

        $project->resources()->create($data);

        return redirect()
            ->route('projects.resources.index', $project)
            ->with('success', 'Resource berhasil ditambahkan.');
    }

    /**
     * Menampilkan form edit resource.
     */
    public function edit(Project $project, Resource $resource): View
    {
        return view('resources.edit', [
            'project' => $project,
            'resource' => $resource,
        ]);
    }

    /**
     * Memperbarui resource.
     */
    public function update(Request $request, Project $project, Resource $resource): RedirectResponse
    {
        $data = $this->validated($request);

        $resource->update($data);

        return redirect()
            ->route('projects.resources.index', $project)
            ->with('success', 'Resource berhasil diperbarui.');
    }

    /**
     * Menghapus resource beserta alokasinya.
     */
    public function destroy(Project $project, Resource $resource): RedirectResponse
    {
        $resource->delete();

        return redirect()
            ->route('projects.resources.index', $project)
            ->with('success', 'Resource berhasil dihapus.');
    }

    /**
     * Aturan validasi bersama untuk store dan update.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $labels = [
            'name' => 'Nama',
            'phone' => 'Telepon',
            'email' => 'Email',
            'role' => 'Peran',
            'salary' => 'Gaji',
        ];

        $data = $request->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
                'email' => ['nullable', 'string', 'max:255', 'email'],
                'role' => ['required', 'string', 'max:255'],
                'salary' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            ],
            $this->indonesianMessages($labels)
        );

        $data['salary'] = $data['salary'] ?? 0;

        return $data;
    }
}
