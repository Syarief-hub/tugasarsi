<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Resource;
use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeder proyek PT XYZ (30 Juni - 31 Juli) dengan 6 tahap SDLC,
     * dependensi antar tugas, dan 7 anggota tim.
     *
     * Aman dijalankan ulang: semua data diambil berdasarkan record yang
     * sudah ada (firstOrCreate / updateOrCreate), bukan ID tetap.
     */
    public function run(): void
    {
        $project = Project::firstOrCreate(
            ['name' => 'PT XYZ'],
            [
                'organization' => 'PT XYZ',
                'website' => 'https://www.ptxyz.co.id',
                'description' => 'Proyek pengembangan sistem informasi PT XYZ '
                    . 'periode 30 Juni - 31 Juli, menerapkan 6 tahap siklus hidup '
                    . 'pengembangan perangkat lunak (SDLC).',
            ]
        );

        // Enam tahap SDLC beserta tanggal mulai dan durasi (hari).
        $stages = [
            [
                'name' => 'Analisis Kebutuhan',
                'start_date' => '2026-06-30',
                'duration' => 5,
                'priority' => 'tinggi',
                'color' => '#2563eb',
            ],
            [
                'name' => 'Analisis Sistem',
                'start_date' => '2026-07-03',
                'duration' => 5,
                'priority' => 'tinggi',
                'color' => '#7c3aed',
            ],
            [
                'name' => 'Perancangan',
                'start_date' => '2026-07-08',
                'duration' => 6,
                'priority' => 'normal',
                'color' => '#0891b2',
            ],
            [
                'name' => 'Implementasi',
                'start_date' => '2026-07-14',
                'duration' => 10,
                'priority' => 'tinggi',
                'color' => '#ea580c',
            ],
            [
                'name' => 'Pengujian',
                'start_date' => '2026-07-24',
                'duration' => 5,
                'priority' => 'normal',
                'color' => '#16a34a',
            ],
            [
                'name' => 'Deployment & Pemeliharaan',
                'start_date' => '2026-07-29',
                'duration' => 3,
                'priority' => 'rendah',
                'color' => '#ca8a04',
            ],
        ];

        $tasks = [];

        foreach ($stages as $stage) {
            $tasks[$stage['name']] = Task::firstOrCreate(
                [
                    'project_id' => $project->getKey(),
                    'name' => $stage['name'],
                ],
                [
                    'parent_id' => null,
                    'start_date' => $stage['start_date'],
                    'duration' => $stage['duration'],
                    'priority' => $stage['priority'],
                    'color' => $stage['color'],
                ]
            );
        }

        // Dependensi antar tugas memakai ID dari model yang baru dibuat.
        $dependencies = [
            [
                'task' => 'Analisis Sistem',
                'predecessor' => 'Analisis Kebutuhan',
                'type' => 'SS',
                'delay' => 3,
            ],
            [
                'task' => 'Perancangan',
                'predecessor' => 'Analisis Sistem',
                'type' => 'SS',
                'delay' => 5,
            ],
            [
                'task' => 'Implementasi',
                'predecessor' => 'Perancangan',
                'type' => 'FS',
                'delay' => 0,
            ],
            [
                'task' => 'Pengujian',
                'predecessor' => 'Implementasi',
                'type' => 'FS',
                'delay' => 0,
            ],
            [
                'task' => 'Deployment & Pemeliharaan',
                'predecessor' => 'Pengujian',
                'type' => 'FS',
                'delay' => 0,
            ],
        ];

        foreach ($dependencies as $dependency) {
            TaskDependency::updateOrCreate(
                [
                    'task_id' => $tasks[$dependency['task']]->getKey(),
                    'predecessor_id' => $tasks[$dependency['predecessor']]->getKey(),
                ],
                [
                    'type' => $dependency['type'],
                    'delay' => $dependency['delay'],
                ]
            );
        }

        // Tujuh anggota tim beserta alokasi (dalam persen) ke tahap SDLC.
        $team = [
            [
                'name' => 'Budi Santoso',
                'phone' => '0812-1000-1001',
                'email' => 'budi.santoso@ptxyz.co.id',
                'role' => 'Project Manager',
                'salary' => 18000000,
                'allocations' => [
                    'Analisis Kebutuhan' => 30,
                    'Analisis Sistem' => 30,
                    'Perancangan' => 30,
                    'Implementasi' => 30,
                    'Pengujian' => 30,
                    'Deployment & Pemeliharaan' => 40,
                ],
            ],
            [
                'name' => 'Siti Aminah',
                'phone' => '0812-1000-1002',
                'email' => 'siti.aminah@ptxyz.co.id',
                'role' => 'Analis',
                'salary' => 12000000,
                'allocations' => [
                    'Analisis Kebutuhan' => 100,
                    'Analisis Sistem' => 100,
                    'Perancangan' => 50,
                ],
            ],
            [
                'name' => 'Rudi Hartono',
                'phone' => '0812-1000-1003',
                'email' => 'rudi.hartono@ptxyz.co.id',
                'role' => 'Analis',
                'salary' => 12000000,
                'allocations' => [
                    'Analisis Kebutuhan' => 100,
                    'Analisis Sistem' => 100,
                    'Perancangan' => 50,
                ],
            ],
            [
                'name' => 'Andi Wijaya',
                'phone' => '0812-1000-1004',
                'email' => 'andi.wijaya@ptxyz.co.id',
                'role' => 'Programmer',
                'salary' => 14000000,
                'allocations' => [
                    'Perancangan' => 50,
                    'Implementasi' => 100,
                    'Deployment & Pemeliharaan' => 50,
                ],
            ],
            [
                'name' => 'Dwi Prasetyo',
                'phone' => '0812-1000-1005',
                'email' => 'dwi.prasetyo@ptxyz.co.id',
                'role' => 'Programmer',
                'salary' => 14000000,
                'allocations' => [
                    'Implementasi' => 100,
                    'Deployment & Pemeliharaan' => 50,
                ],
            ],
            [
                'name' => 'Lestari Dewi',
                'phone' => '0812-1000-1006',
                'email' => 'lestari.dewi@ptxyz.co.id',
                'role' => 'Tester',
                'salary' => 10000000,
                'allocations' => [
                    'Pengujian' => 100,
                    'Deployment & Pemeliharaan' => 50,
                ],
            ],
            [
                'name' => 'Maya Putri',
                'phone' => '0812-1000-1007',
                'email' => 'maya.putri@ptxyz.co.id',
                'role' => 'Tester',
                'salary' => 10000000,
                'allocations' => [
                    'Pengujian' => 100,
                    'Deployment & Pemeliharaan' => 50,
                ],
            ],
        ];

        foreach ($team as $member) {
            $allocations = $member['allocations'];
            unset($member['allocations']);

            $resource = Resource::firstOrCreate(
                [
                    'project_id' => $project->getKey(),
                    'name' => $member['name'],
                ],
                $member
            );

            foreach ($allocations as $taskName => $allocation) {
                $tasks[$taskName]->resources()->sync([
                    $resource->getKey() => ['allocation' => $allocation],
                ], false);
            }
        }
    }
}
