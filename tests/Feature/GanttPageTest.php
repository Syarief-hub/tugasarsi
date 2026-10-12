<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use Database\Seeders\ProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian halaman Gantt chart per proyek.
 */
class GanttPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_gantt_page_returns_200_and_shows_all_project_task_names(): void
    {
        $this->seed(ProjectSeeder::class);

        $project = Project::where('name', 'PT XYZ')->firstOrFail();

        $response = $this->get(route('projects.gantt', $project));

        $response->assertOk();

        foreach ($project->tasks as $task) {
            $response->assertSee($task->name);
        }
    }

    public function test_gantt_page_shows_subtasks_indented_and_resource_names(): void
    {
        $this->seed(ProjectSeeder::class);

        $project = Project::where('name', 'PT XYZ')->firstOrFail();
        $parent = $project->tasks()->where('name', 'Implementasi')->firstOrFail();
        $resource = $project->resources()->firstOrFail();

        $subtask = Task::create([
            'project_id' => $project->getKey(),
            'parent_id' => $parent->getKey(),
            'name' => 'Modul Laporan',
            'start_date' => '2026-07-21',
            'duration' => 3,
            'priority' => 'normal',
            'color' => '#0f766e',
        ]);

        $subtask->resources()->attach($resource->getKey(), ['allocation' => 50]);

        $response = $this->get(route('projects.gantt', $project));

        $response->assertOk();
        $response->assertSee('Modul Laporan');
        $response->assertSee($resource->name);
    }

    public function test_gantt_page_renders_empty_state_for_project_without_tasks(): void
    {
        $project = Project::create(['name' => 'Proyek Kosong']);

        $response = $this->get(route('projects.gantt', $project));

        $response->assertOk();
        $response->assertSee('Belum ada tugas pada proyek ini.');
    }
}
