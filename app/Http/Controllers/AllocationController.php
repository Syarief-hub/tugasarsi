<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AllocationController extends Controller
{
    /**
     * Mengalokasikan resource ke sebuah tugas (persentase alokasi 1-100).
     */
    public function store(Request $request, Task $task): RedirectResponse
    {
        $labels = [
            'resource_id' => 'Resource',
            'allocation' => 'Alokasi (%)',
        ];

        $data = $request->validate(
            [
                'resource_id' => [
                    'required',
                    'integer',
                    Rule::exists('resources', 'id')->where('project_id', $task->project_id),
                ],
                'allocation' => ['required', 'integer', 'min:1', 'max:100'],
            ],
            array_merge(
                $this->indonesianMessages($labels),
                [
                    'resource_id.exists' => 'Resource tidak ditemukan pada proyek ini.',
                ]
            )
        );

        // sync tanpa detaching: menambah alokasi baru atau memperbarui yang sudah ada.
        $task->resources()->sync([
            (int) $data['resource_id'] => ['allocation' => (int) $data['allocation']],
        ], false);

        return back()->with('success', 'Resource berhasil dialokasikan ke tugas.');
    }

    /**
     * Melepas alokasi resource dari sebuah tugas.
     */
    public function detach(Task $task, Resource $resource): RedirectResponse
    {
        $task->resources()->detach($resource->getKey());

        return back()->with('success', 'Alokasi resource berhasil dilepas.');
    }
}
