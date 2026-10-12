<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian form dependensi tugas: menyimpan keempat tipe (FS, SS, FF, SF)
 * beserta delay, membuka ulang form edit untuk memverifikasi nilai tersimpan,
 * serta penolakan predecessor diri sendiri, duplikat, dan melingkar.
 */
class DependencyFormTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Proyek dengan lima tugas berurutan (A..E) untuk dijadikan
     * predecessor maupun tugas yang diedit.
     *
     * @return array{0: Project, 1: array<string, Task>}
     */
    private function projectWithTasks(): array
    {
        $project = Project::create(['name' => 'Proyek Dependensi']);

        $tasks = [];

        foreach (['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon'] as $name) {
            $tasks[$name] = $project->tasks()->create([
                'name' => $name,
                'start_date' => '2026-08-03',
                'duration' => 3,
                'priority' => 'normal',
                'color' => '#3355ff',
            ]);
        }

        return [$project, $tasks];
    }

    /**
     * Data form tugas dasar untuk update.
     *
     * @return array<string, mixed>
     */
    private function basePayload(Task $task): array
    {
        return [
            'name' => $task->name,
            'start_date' => $task->start_date->format('Y-m-d'),
            'duration' => $task->duration,
            'priority' => $task->priority,
            'color' => $task->color,
            'parent_id' => '',
        ];
    }

    /**
     * Ekstrak nilai dependensi yang dirender pada form edit:
     * predecessor terpilih, tipe terpilih, dan delay tiap baris.
     *
     * @return array<int, array{predecessor_id: string, type: string, delay: string}>
     */
    private function dependencyRowsFromForm(string $html): array
    {
        $rows = [];

        preg_match_all(
            '/<div class="dependency-row.*?<\/div>\s*<\/div>/s',
            $html,
            $rowBlocks
        );

        foreach ($rowBlocks[0] as $block) {
            if (! preg_match('/name="dependencies\[(\d+)\]\[predecessor_id\]"/', $block, $indexMatch)) {
                continue;
            }

            $index = (int) $indexMatch[1];

            preg_match(
                '/name="dependencies\[' . $index . '\]\[predecessor_id\]".*?<option value="(\d+)"[^>]*selected/s',
                $block,
                $predecessor
            );

            preg_match(
                '/name="dependencies\[' . $index . '\]\[type\]".*?<option value="([A-Z]+)"[^>]*selected/s',
                $block,
                $type
            );

            preg_match(
                '/name="dependencies\[' . $index . '\]\[delay\]"[^>]*value="(\d*)"/s',
                $block,
                $delay
            );

            $rows[$index] = [
                'predecessor_id' => $predecessor[1] ?? '',
                'type' => $type[1] ?? '',
                'delay' => $delay[1] ?? '',
            ];
        }

        ksort($rows);

        return $rows;
    }

    /* ------------------------------------------------------------------ */
    /* Simpan keempat tipe dependensi + delay                              */
    /* ------------------------------------------------------------------ */

    public function test_all_four_dependency_types_with_delay_are_saved_and_reloaded_in_edit_form(): void
    {
        [$project, $tasks] = $this->projectWithTasks();

        $target = $tasks['Epsilon'];

        $response = $this->put(route('projects.tasks.update', [$project, $target]), array_merge(
            $this->basePayload($target),
            [
                'dependencies' => [
                    ['predecessor_id' => $tasks['Alpha']->getKey(), 'type' => 'FS', 'delay' => 2],
                    ['predecessor_id' => $tasks['Beta']->getKey(), 'type' => 'SS', 'delay' => 3],
                    ['predecessor_id' => $tasks['Gamma']->getKey(), 'type' => 'FF', 'delay' => 4],
                    ['predecessor_id' => $tasks['Delta']->getKey(), 'type' => 'SF', 'delay' => 5],
                ],
            ]
        ));

        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Tugas berhasil diperbarui.');

        // Tersimpan benar di database.
        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $target->getKey(),
            'predecessor_id' => $tasks['Alpha']->getKey(),
            'type' => 'FS',
            'delay' => 2,
        ]);
        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $target->getKey(),
            'predecessor_id' => $tasks['Beta']->getKey(),
            'type' => 'SS',
            'delay' => 3,
        ]);
        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $target->getKey(),
            'predecessor_id' => $tasks['Gamma']->getKey(),
            'type' => 'FF',
            'delay' => 4,
        ]);
        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $target->getKey(),
            'predecessor_id' => $tasks['Delta']->getKey(),
            'type' => 'SF',
            'delay' => 5,
        ]);

        $this->assertSame(4, TaskDependency::where('task_id', $target->getKey())->count());

        // Buka ulang form edit: nilai tersimpan dirender kembali.
        $edit = $this->get(route('projects.tasks.edit', [$project, $target]));

        $edit->assertOk();

        $rows = $this->dependencyRowsFromForm($edit->getContent());

        $this->assertCount(4, $rows, 'Form edit harus menampilkan 4 baris dependensi.');

        $expected = [
            ['predecessor_id' => (string) $tasks['Alpha']->getKey(), 'type' => 'FS', 'delay' => '2'],
            ['predecessor_id' => (string) $tasks['Beta']->getKey(), 'type' => 'SS', 'delay' => '3'],
            ['predecessor_id' => (string) $tasks['Gamma']->getKey(), 'type' => 'FF', 'delay' => '4'],
            ['predecessor_id' => (string) $tasks['Delta']->getKey(), 'type' => 'SF', 'delay' => '5'],
        ];

        foreach ($expected as $index => $expectedRow) {
            $this->assertSame(
                $expectedRow,
                $rows[$index] ?? null,
                'Baris dependensi ke-' . $index . ' pada form edit tidak sesuai.'
            );
        }
    }

    /* ------------------------------------------------------------------ */
    /* Penolakan input dependensi yang tidak valid                         */
    /* ------------------------------------------------------------------ */

    public function test_dependency_on_itself_is_rejected(): void
    {
        [$project, $tasks] = $this->projectWithTasks();

        $target = $tasks['Epsilon'];

        $response = $this->put(route('projects.tasks.update', [$project, $target]), array_merge(
            $this->basePayload($target),
            [
                'dependencies' => [
                    ['predecessor_id' => $target->getKey(), 'type' => 'FS', 'delay' => 0],
                ],
            ]
        ));

        $response->assertStatus(302);
        $response->assertSessionHasErrors('dependencies.0.predecessor_id');

        $messages = implode(' ', session('errors')->get('dependencies.0.predecessor_id'));
        $this->assertStringContainsString('dirinya sendiri', $messages);

        $this->assertSame(0, TaskDependency::where('task_id', $target->getKey())->count());
    }

    public function test_duplicate_predecessor_is_rejected(): void
    {
        [$project, $tasks] = $this->projectWithTasks();

        $target = $tasks['Epsilon'];

        $response = $this->put(route('projects.tasks.update', [$project, $target]), array_merge(
            $this->basePayload($target),
            [
                'dependencies' => [
                    ['predecessor_id' => $tasks['Alpha']->getKey(), 'type' => 'FS', 'delay' => 0],
                    ['predecessor_id' => $tasks['Alpha']->getKey(), 'type' => 'SS', 'delay' => 1],
                ],
            ]
        ));

        $response->assertStatus(302);
        $response->assertSessionHasErrors('dependencies.1.predecessor_id');

        $messages = implode(' ', session('errors')->get('dependencies.1.predecessor_id'));
        $this->assertStringContainsString('dua kali', $messages);

        $this->assertSame(0, TaskDependency::where('task_id', $target->getKey())->count());
    }

    public function test_circular_dependency_is_rejected(): void
    {
        [$project, $tasks] = $this->projectWithTasks();

        // Alpha sudah bergantung pada Epsilon; form Epsilon mencoba bergantung pada Alpha.
        TaskDependency::create([
            'task_id' => $tasks['Alpha']->getKey(),
            'predecessor_id' => $tasks['Epsilon']->getKey(),
            'type' => 'FS',
            'delay' => 0,
        ]);

        $target = $tasks['Epsilon'];

        $response = $this->put(route('projects.tasks.update', [$project, $target]), array_merge(
            $this->basePayload($target),
            [
                'dependencies' => [
                    ['predecessor_id' => $tasks['Alpha']->getKey(), 'type' => 'FS', 'delay' => 0],
                ],
            ]
        ));

        $response->assertStatus(302);
        $response->assertSessionHasErrors('dependencies');

        $messages = implode(' ', session('errors')->get('dependencies'));
        $this->assertStringContainsString('Dependensi melingkar', $messages);
        $this->assertStringContainsString('Alpha', $messages);
        $this->assertStringContainsString('Epsilon', $messages);

        // Hanya dependensi lama (Alpha <- Epsilon) yang tersimpan.
        $this->assertSame(1, TaskDependency::where('task_id', $tasks['Alpha']->getKey())->count());
        $this->assertSame(0, TaskDependency::where('task_id', $target->getKey())->count());
    }

    public function test_predecessor_from_other_project_is_rejected(): void
    {
        [$project, $tasks] = $this->projectWithTasks();

        $other = Project::create(['name' => 'Proyek Lain']);
        $foreign = $other->tasks()->create([
            'name' => 'Asing',
            'start_date' => '2026-08-03',
            'duration' => 2,
            'priority' => 'normal',
            'color' => '#666666',
        ]);

        $target = $tasks['Epsilon'];

        $response = $this->put(route('projects.tasks.update', [$project, $target]), array_merge(
            $this->basePayload($target),
            [
                'dependencies' => [
                    ['predecessor_id' => $foreign->getKey(), 'type' => 'FS', 'delay' => 0],
                ],
            ]
        ));

        $response->assertStatus(302);
        $response->assertSessionHasErrors('dependencies.0.predecessor_id');

        $this->assertSame(0, TaskDependency::count());
    }
}
