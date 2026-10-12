<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Services\SchedulingService;
use Database\Seeders\ProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Pengujian layanan penjadwalan: hari kerja (akhir pekan + hari libur),
 * seluruh tipe dependensi dengan delay 0 dan > 0, penolakan siklus,
 * dan critical path (CPM).
 */
class SchedulingServiceTest extends TestCase
{
    use RefreshDatabase;

    private SchedulingService $scheduling;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scheduling = app(SchedulingService::class);
    }

    /* ------------------------------------------------------------------ */
    /* Skenario PT XYZ dari seeder + libur custom 17 Juli 2026             */
    /* ------------------------------------------------------------------ */

    public function test_pt_xyz_seed_scenario_skips_weekends_and_custom_holiday(): void
    {
        $this->seed(ProjectSeeder::class);

        Holiday::create(['name' => 'Libur Custom', 'date' => '2026-07-17', 'repeats' => 'none']);

        $project = Project::where('name', 'PT XYZ')->firstOrFail();
        $tasks = $project->tasks()->orderBy('id')->get()->keyBy('name');

        $result = $this->scheduling->reschedule($project);

        $this->assertSame(6, $result['rescheduled']);

        // Analisis Kebutuhan: Selasa 30/06 + 5 hari kerja -> Senin 06/07
        // (Sabtu 04/07 dan Minggu 05/07 dilewati).
        $kebutuhan = $tasks->get('Analisis Kebutuhan');
        $this->assertTrue($kebutuhan->start_date->equalTo(Carbon::parse('2026-06-30')));
        $this->assertTrue($kebutuhan->end_date->equalTo(Carbon::parse('2026-07-06')));

        // Analisis Sistem: SS+3 dari Analisis Kebutuhan -> mulai Jumat 03/07,
        // durasi 8 hari kerja -> 03, 06, 07, 08, 09, 10, 13, 14 Juli (selesai 14/07).
        $sistem = $tasks->get('Analisis Sistem')->fresh();
        $this->assertTrue($sistem->start_date->equalTo(Carbon::parse('2026-07-03')));
        $this->assertTrue($sistem->end_date->equalTo(Carbon::parse('2026-07-14')));

        // Perancangan: SS+5 dari Analisis Sistem (mulai 03/07) -> mulai Jumat
        // 10/07, durasi 7 hari kerja: 10, 13, 14, 15, 16, 17 LIBUR, 20, 21
        // -> selesai Selasa 21/07. Bukti libur custom 17 Juli 2026 dilewati.
        $perancangan = $tasks->get('Perancangan')->fresh();
        $this->assertTrue($perancangan->start_date->equalTo(Carbon::parse('2026-07-10')));
        $this->assertTrue($perancangan->end_date->equalTo(Carbon::parse('2026-07-21')));
    }

    public function test_custom_holiday_17_july_moves_end_date_from_20_to_21_july(): void
    {
        $this->seed(ProjectSeeder::class);

        $project = Project::where('name', 'PT XYZ')->firstOrFail();
        $perancangan = $project->tasks()->where('name', 'Perancangan')->firstOrFail();

        // Jadwalkan ulang dulu (SS+5 dari Analisis Sistem) tanpa hari libur:
        // mulai 10/07 + 7 hari kerja = 10, 13, 14, 15, 16, 17, 20 Juli.
        $this->scheduling->reschedule($project);
        $perancangan = $perancangan->fresh();
        $this->assertTrue($perancangan->start_date->equalTo(Carbon::parse('2026-07-10')));
        $this->assertTrue($perancangan->end_date->equalTo(Carbon::parse('2026-07-20')));

        Holiday::create(['name' => 'Libur Custom', 'date' => '2026-07-17', 'repeats' => 'none']);

        // Dengan libur 17 Juli: hari kerja keenam bergeser ke 20 Juli dan
        // hari ketujuh ke 21 Juli, sehingga selesai 21/07 (bukan 20/07).
        // (end_date diakses lewat instance segar karena hasil akses sebelumnya
        // pada instance lama sudah ter-cache saat libur belum ada.)
        $this->assertTrue($perancangan->fresh()->end_date->equalTo(Carbon::parse('2026-07-21')));
    }

    /* ------------------------------------------------------------------ */
    /* Setiap tipe dependensi: delay 0 dan delay > 0                       */
    /* ------------------------------------------------------------------ */

    public function test_all_dependency_types_with_zero_and_positive_delay(): void
    {
        // Predecessor bersama: mulai Senin 06/07/2026, durasi 5 hari kerja
        // -> 06, 07, 08, 09, 10 (selesai Jumat 10/07).
        $scenarios = [
            // type, delay, expected start, expected end ( successor )
            ['FS', 0, '2026-07-13', '2026-07-15'], // mulai hari kerja pertama setelah selesai
            ['FS', 2, '2026-07-15', '2026-07-17'], // + 2 hari kerja (13, 14, 15)
            ['SS', 0, '2026-07-06', '2026-07-08'], // mulai bersamaan predecessor
            ['SS', 2, '2026-07-08', '2026-07-10'], // 06 + 2 hari kerja = 08/07
            ['FF', 0, '2026-07-08', '2026-07-10'], // selesai bersamaan predecessor
            ['FF', 2, '2026-07-10', '2026-07-14'], // selesai 10 + 2 hari kerja = 14/07
            ['SF', 0, '2026-07-02', '2026-07-06'], // selesai bersamaan mulai predecessor
            ['SF', 2, '2026-07-06', '2026-07-08'], // selesai 06 + 2 hari kerja = 08/07
        ];

        foreach ($scenarios as [$type, $delay, $expectedStart, $expectedEnd]) {
            $project = Project::create(['name' => "Proyek {$type}+{$delay}"]);

            $predecessor = $project->tasks()->create([
                'name' => 'Predecessor',
                'start_date' => '2026-07-06',
                'duration' => 5,
                'priority' => 'normal',
                'color' => '#333333',
            ]);

            $successor = $project->tasks()->create([
                'name' => 'Successor',
                'start_date' => '2026-07-01', // tanggal lama, harus digeser oleh dependensi
                'duration' => 3,
                'priority' => 'normal',
                'color' => '#666666',
            ]);

            TaskDependency::create([
                'task_id' => $successor->getKey(),
                'predecessor_id' => $predecessor->getKey(),
                'type' => $type,
                'delay' => $delay,
            ]);

            $this->scheduling->reschedule($project);

            $successor->refresh();

            $this->assertTrue(
                $successor->start_date->equalTo(Carbon::parse($expectedStart)),
                "{$type}+{$delay}: mulai {$successor->start_date->toDateString()}, diharapkan {$expectedStart}."
            );

            $this->assertTrue(
                $successor->end_date->equalTo(Carbon::parse($expectedEnd)),
                "{$type}+{$delay}: selesai {$successor->end_date->toDateString()}, diharapkan {$expectedEnd}."
            );
        }
    }

    /* ------------------------------------------------------------------ */
    /* Dependensi melingkar ditolak                                        */
    /* ------------------------------------------------------------------ */

    public function test_circular_dependency_is_rejected_with_indonesian_message(): void
    {
        $project = Project::create(['name' => 'Siklus']);

        $a = $project->tasks()->create([
            'name' => 'Alpha', 'start_date' => '2026-07-01', 'duration' => 2,
            'priority' => 'normal', 'color' => '#111111',
        ]);
        $b = $project->tasks()->create([
            'name' => 'Beta', 'start_date' => '2026-07-01', 'duration' => 2,
            'priority' => 'normal', 'color' => '#222222',
        ]);
        $c = $project->tasks()->create([
            'name' => 'Gamma', 'start_date' => '2026-07-01', 'duration' => 2,
            'priority' => 'normal', 'color' => '#333333',
        ]);

        // Alpha <- Beta <- Gamma <- Alpha (siklus).
        TaskDependency::create(['task_id' => $a->getKey(), 'predecessor_id' => $b->getKey(), 'type' => 'FS', 'delay' => 0]);
        TaskDependency::create(['task_id' => $b->getKey(), 'predecessor_id' => $c->getKey(), 'type' => 'FS', 'delay' => 0]);
        TaskDependency::create(['task_id' => $c->getKey(), 'predecessor_id' => $a->getKey(), 'type' => 'FS', 'delay' => 0]);

        try {
            $this->scheduling->reschedule($project);
            $this->fail('ValidationException tidak dilempar untuk dependensi melingkar.');
        } catch (ValidationException $exception) {
            $messages = implode(' ', \Illuminate\Support\Arr::flatten($exception->errors()));
            $this->assertStringContainsString('Dependensi melingkar', $messages);
            $this->assertStringContainsString('Alpha', $messages);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Critical path (CPM) 10 aktivitas A-J                                */
    /* ------------------------------------------------------------------ */

    public function test_critical_path_activities_a_to_j(): void
    {
        $project = Project::create(['name' => 'CPM A-J']);

        $durations = [
            'A' => 1, 'B' => 2, 'C' => 3, 'D' => 5, 'E' => 7,
            'F' => 4, 'G' => 12, 'H' => 21, 'I' => 2, 'J' => 3,
        ];

        $tasks = [];

        foreach ($durations as $name => $duration) {
            $tasks[$name] = $project->tasks()->create([
                'name' => $name,
                'start_date' => '2026-07-01',
                'duration' => $duration,
                'priority' => 'normal',
                'color' => '#444444',
            ]);
        }

        // D=A; E=B; F=B; G=C; H=D,E; I=G; J=F,H,I (semua FS delay 0).
        $links = [
            ['D', 'A'], ['E', 'B'], ['F', 'B'], ['G', 'C'],
            ['H', 'D'], ['H', 'E'], ['I', 'G'],
            ['J', 'F'], ['J', 'H'], ['J', 'I'],
        ];

        foreach ($links as [$task, $predecessor]) {
            TaskDependency::create([
                'task_id' => $tasks[$task]->getKey(),
                'predecessor_id' => $tasks[$predecessor]->getKey(),
                'type' => 'FS',
                'delay' => 0,
            ]);
        }

        $result = $this->scheduling->reschedule($project);

        // Durasi proyek B(2) + E(7) + H(21) + J(3) = 33 hari kerja.
        $this->assertSame(33, $result['duration']);

        $this->assertSame(
            ['B', 'E', 'H', 'J'],
            $result['critical'],
            'Jalur kritis diharapkan B-E-H-J.'
        );

        // Slack beberapa tugas non-kritis (LS - ES).
        $detail = $this->scheduling->criticalPath($project);
        $slack = array_map(fn ($info) => $info['slack'], $detail['tasks']);

        $this->assertSame(0, $slack[$tasks['B']->getKey()]);
        $this->assertSame(0, $slack[$tasks['E']->getKey()]);
        $this->assertSame(0, $slack[$tasks['H']->getKey()]);
        $this->assertSame(0, $slack[$tasks['J']->getKey()]);
        $this->assertSame(3, $slack[$tasks['A']->getKey()]);
        $this->assertSame(3, $slack[$tasks['D']->getKey()]);
        $this->assertSame(13, $slack[$tasks['C']->getKey()]);
        $this->assertSame(13, $slack[$tasks['G']->getKey()]);
        $this->assertSame(13, $slack[$tasks['I']->getKey()]);
        $this->assertSame(24, $slack[$tasks['F']->getKey()]);
    }

    /* ------------------------------------------------------------------ */
    /* Penolakan siklus lewat form tugas (TaskRequest) dan tombol          */
    /* "Jadwalkan ulang"                                                   */
    /* ------------------------------------------------------------------ */

    public function test_task_form_rejects_circular_dependency_with_indonesian_message(): void
    {
        $project = Project::create(['name' => 'Siklus Form']);

        $x = $project->tasks()->create([
            'name' => 'Xena', 'start_date' => '2026-07-01', 'duration' => 2,
            'priority' => 'normal', 'color' => '#111111',
        ]);
        $y = $project->tasks()->create([
            'name' => 'Yoga', 'start_date' => '2026-07-01', 'duration' => 2,
            'priority' => 'normal', 'color' => '#222222',
        ]);

        // X bergantung pada Y (tersimpan), lalu form Y menambah dependensi ke X.
        TaskDependency::create(['task_id' => $x->getKey(), 'predecessor_id' => $y->getKey(), 'type' => 'FS', 'delay' => 0]);

        $response = $this->put("/projects/{$project->getKey()}/tasks/{$y->getKey()}", [
            'name' => 'Yoga',
            'start_date' => '2026-07-01',
            'duration' => 2,
            'priority' => 'normal',
            'color' => '#222222',
            'parent_id' => '',
            'dependencies' => [
                ['predecessor_id' => $x->getKey(), 'type' => 'FS', 'delay' => 0],
            ],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('dependencies');

        $messages = implode(' ', session('errors')->get('dependencies'));
        $this->assertStringContainsString('Dependensi melingkar', $messages);
        $this->assertStringContainsString('Xena', $messages);
        $this->assertStringContainsString('Yoga', $messages);

        // Dependensi tersimpan tidak berubah (X -> Y tetap satu-satunya).
        $this->assertSame(1, TaskDependency::count());
    }

    public function test_schedule_button_reports_cycle_in_flash_error(): void
    {
        $project = Project::create(['name' => 'Siklus Tombol']);

        $a = $project->tasks()->create([
            'name' => 'Atma', 'start_date' => '2026-07-01', 'duration' => 2,
            'priority' => 'normal', 'color' => '#111111',
        ]);
        $b = $project->tasks()->create([
            'name' => 'Bima', 'start_date' => '2026-07-01', 'duration' => 2,
            'priority' => 'normal', 'color' => '#222222',
        ]);

        TaskDependency::create(['task_id' => $a->getKey(), 'predecessor_id' => $b->getKey(), 'type' => 'FS', 'delay' => 0]);
        TaskDependency::create(['task_id' => $b->getKey(), 'predecessor_id' => $a->getKey(), 'type' => 'FS', 'delay' => 0]);

        $response = $this->post("/projects/{$project->getKey()}/schedule");

        $response->assertRedirect("/projects/{$project->getKey()}");
        $response->assertSessionHasErrors('schedule');

        $messages = implode(' ', session('errors')->get('schedule'));
        $this->assertStringContainsString('Dependensi melingkar', $messages);
    }
}
