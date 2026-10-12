<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Proyek "Pengelolaan Pemberkasan" dengan 10 aktivitas A-J untuk
 * analisis jalur kritis manual (PERT/CPM) sesuai materi:
 *
 *     A=1  B=2  C=3  D=5  E=7  F=4  G=12  H=21  I=2  J=3 (hari)
 *     D=A; E=B; F=B; G=C; H=D,E; I=G; J=F,H,I
 *
 * Jalur: A-D-H-J=30, B-E-H-J=33 (kritis), B-F-J=9, C-G-I-J=20.
 *
 * Aman dijalankan ulang: semua data diambil berdasarkan record yang
 * sudah ada (firstOrCreate / updateOrCreate), bukan ID tetap.
 */
class PemberkasanSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::firstOrCreate(
            ['name' => 'Pengelolaan Pemberkasan'],
            [
                'organization' => 'Instansi Pemerintah',
                'website' => null,
                'description' => 'Proyek pengelolaan pemberkasan dengan 10 aktivitas '
                    . '(A sampai J) untuk latihan analisis jalur kritis manual '
                    . 'menggunakan metode PERT/CPM.',
            ]
        );

        // Sepuluh aktivitas beserta durasi (hari) dan tanggal mulai.
        // Tanggal mulai disusun agar konsisten dengan ES (earliest start)
        // hasil CPM manual: A,B,C mulai hari ke-0; D=1; E,F=2; G=3;
        // H=9; I=15; J=30 (dihitung dari durasi dan dependensi di bawah).
        $base = '2026-08-03'; // Senin, dasar penghitungan hari.

        $activities = [
            ['code' => 'A', 'name' => 'Analisis', 'duration' => 1, 'start_offset' => 0],
            ['code' => 'B', 'name' => 'Desain', 'duration' => 2, 'start_offset' => 0],
            ['code' => 'C', 'name' => 'Pengadaan', 'duration' => 3, 'start_offset' => 0],
            ['code' => 'D', 'name' => 'Persiapan', 'duration' => 5, 'start_offset' => 1],
            ['code' => 'E', 'name' => 'Instalasi', 'duration' => 7, 'start_offset' => 2],
            ['code' => 'F', 'name' => 'Konfigurasi', 'duration' => 4, 'start_offset' => 2],
            ['code' => 'G', 'name' => 'Integrasi', 'duration' => 12, 'start_offset' => 3],
            ['code' => 'H', 'name' => 'Pengujian', 'duration' => 21, 'start_offset' => 9],
            ['code' => 'I', 'name' => 'Pelatihan', 'duration' => 2, 'start_offset' => 15],
            ['code' => 'J', 'name' => 'Serah Terima', 'duration' => 3, 'start_offset' => 30],
        ];

        $tasks = [];

        foreach ($activities as $activity) {
            $tasks[$activity['code']] = Task::firstOrCreate(
                [
                    'project_id' => $project->getKey(),
                    'name' => $activity['name'],
                ],
                [
                    'parent_id' => null,
                    'start_date' => Carbon::parse($base)->addDays($activity['start_offset']),
                    'duration' => $activity['duration'],
                    'priority' => 'normal',
                    'color' => '#0f766e',
                ]
            );
        }

        // Dependensi: predecessor => daftar successor (huruf aktivitas).
        $dependencies = [
            'A' => ['D'],
            'B' => ['E', 'F'],
            'C' => ['G'],
            'D' => ['H'],
            'E' => ['H'],
            'G' => ['I'],
            'F' => ['J'],
            'H' => ['J'],
            'I' => ['J'],
        ];

        foreach ($dependencies as $from => $successors) {
            foreach ($successors as $to) {
                TaskDependency::updateOrCreate(
                    [
                        'task_id' => $tasks[$to]->getKey(),
                        'predecessor_id' => $tasks[$from]->getKey(),
                    ],
                    [
                        'type' => 'FS',
                        'delay' => 0,
                    ]
                );
            }
        }
    }
}
