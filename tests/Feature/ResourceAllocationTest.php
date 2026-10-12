<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian alokasi resource ke tugas: pasang alokasi (baru maupun
 * pembaruan persentase) dan cabut alokasi kembali.
 */
class ResourceAllocationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Proyek dengan satu tugas dan satu resource.
     *
     * @return array{0: Project, 1: Task, 2: \App\Models\Resource}
     */
    private function projectWithTaskAndResource(): array
    {
        $project = Project::create(['name' => 'Proyek Alokasi']);

        $task = $project->tasks()->create([
            'name' => 'Tugas Alokasi',
            'start_date' => '2026-08-03',
            'duration' => 4,
            'priority' => 'normal',
            'color' => '#3355ff',
        ]);

        $resource = $project->resources()->create([
            'name' => 'Aloki',
            'role' => 'Programmer',
            'salary' => 8000000,
        ]);

        return [$project, $task, $resource];
    }

    public function test_resource_can_be_allocated_to_task(): void
    {
        [, $task, $resource] = $this->projectWithTaskAndResource();

        $response = $this->post(route('allocations.store', $task), [
            'resource_id' => $resource->getKey(),
            'allocation' => 60,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Resource berhasil dialokasikan ke tugas.');

        $this->assertDatabaseHas('resource_task', [
            'resource_id' => $resource->getKey(),
            'task_id' => $task->getKey(),
            'allocation' => 60,
        ]);

        // Tampil juga di halaman detail tugas.
        $this->get(route('projects.tasks.show', [$task->project_id, $task]))
            ->assertOk()
            ->assertSee($resource->name);
    }

    public function test_existing_allocation_percentage_can_be_updated(): void
    {
        [, $task, $resource] = $this->projectWithTaskAndResource();

        $this->post(route('allocations.store', $task), [
            'resource_id' => $resource->getKey(),
            'allocation' => 60,
        ])->assertSessionHas('success');

        // Alokasi kedua untuk pasangan yang sama memperbarui persentase,
        // bukan menambah baris baru.
        $this->post(route('allocations.store', $task), [
            'resource_id' => $resource->getKey(),
            'allocation' => 85,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('resource_task', [
            'resource_id' => $resource->getKey(),
            'task_id' => $task->getKey(),
            'allocation' => 85,
        ]);

        $this->assertSame(1, $task->resources()->count());
    }

    public function test_allocation_can_be_detached_from_task(): void
    {
        [, $task, $resource] = $this->projectWithTaskAndResource();

        $this->post(route('allocations.store', $task), [
            'resource_id' => $resource->getKey(),
            'allocation' => 60,
        ])->assertSessionHas('success');

        $response = $this->delete(route('allocations.destroy', [$task, $resource]));

        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Alokasi resource berhasil dilepas.');

        $this->assertDatabaseMissing('resource_task', [
            'resource_id' => $resource->getKey(),
            'task_id' => $task->getKey(),
        ]);

        $this->assertSame(0, $task->resources()->count());
    }

    public function test_empty_allocation_form_shows_indonesian_validation_messages(): void
    {
        [, $task] = $this->projectWithTaskAndResource();

        $response = $this->post(route('allocations.store', $task), []);

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'resource_id' => 'Resource wajib diisi.',
            'allocation' => 'Alokasi (%) wajib diisi.',
        ]);
    }

    public function test_resource_from_another_project_cannot_be_allocated(): void
    {
        [, $task] = $this->projectWithTaskAndResource();

        $other = Project::create(['name' => 'Proyek Lain Alokasi']);
        $foreign = $other->resources()->create([
            'name' => 'Asing',
            'role' => 'Tester',
        ]);

        $response = $this->post(route('allocations.store', $task), [
            'resource_id' => $foreign->getKey(),
            'allocation' => 50,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'resource_id' => 'Resource tidak ditemukan pada proyek ini.',
        ]);

        $this->assertDatabaseMissing('resource_task', [
            'resource_id' => $foreign->getKey(),
            'task_id' => $task->getKey(),
        ]);
    }
}
