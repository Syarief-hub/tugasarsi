<?php

namespace Tests\Feature;

use App\Models\Project;
use Database\Seeders\PemberkasanSeeder;
use Database\Seeders\ProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian halaman PERT chart dan Analisis Jalur Kritis Manual
 * untuk proyek "Pengelolaan Pemberkasan" (aktivitas A-J sesuai materi).
 */
class PertAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function pemberkasan(): Project
    {
        $this->seed(PemberkasanSeeder::class);

        return Project::where('name', 'Pengelolaan Pemberkasan')->firstOrFail();
    }

    /* ------------------------------------------------------------------ */
    /* Halaman PERT                                                        */
    /* ------------------------------------------------------------------ */

    public function test_pert_page_returns_200_and_shows_all_activity_names(): void
    {
        $project = $this->pemberkasan();

        $response = $this->get(route('projects.pert', $project));

        $response->assertOk();

        foreach ($project->tasks as $task) {
            $response->assertSee($task->name);
        }
    }

    public function test_pert_page_returns_200_for_seeded_pt_xyz(): void
    {
        $this->seed(ProjectSeeder::class);

        $project = Project::where('name', 'PT XYZ')->firstOrFail();

        $this->get(route('projects.pert', $project))->assertOk();
    }

    /* ------------------------------------------------------------------ */
    /* Halaman Analisis Jalur Kritis Manual                                */
    /* ------------------------------------------------------------------ */

    public function test_critical_path_page_returns_200_and_shows_activity_table(): void
    {
        $project = $this->pemberkasan();

        $response = $this->get(route('projects.criticalPath', $project));

        $response->assertOk();
        $response->assertSee('Tabel Aktivitas');

        foreach ($project->tasks as $task) {
            $response->assertSee($task->name);
        }
    }

    public function test_a_j_analysis_shows_critical_path_b_e_h_j_with_duration_33(): void
    {
        $project = $this->pemberkasan();

        $response = $this->get(route('projects.criticalPath', $project));

        $response->assertOk();

        // Jalur terpanjang B -> E -> H -> J (2 + 7 + 21 + 3 = 33 hari)
        // ditandai sebagai critical path beserta total durasinya.
        $response->assertSee('B -> E -> H -> J');
        $response->assertSee('Total 33 hari');

        // Durasi proyek = total jalur kritis.
        $response->assertSee('Durasi Proyek');
        $response->assertSee('33 hari');
    }

    /* ------------------------------------------------------------------ */
    /* Simulasi keterlambatan                                              */
    /* ------------------------------------------------------------------ */

    public function test_simulation_delaying_activity_e_by_3_days_yields_36(): void
    {
        $project = $this->pemberkasan();

        // Aktivitas E = "Instalasi" (urutan ke-5, durasi 7 hari).
        $activityE = $project->tasks()->where('name', 'Instalasi')->firstOrFail();

        $response = $this->get(route('projects.criticalPath', [
            'project' => $project,
            'activity' => $activityE->getKey(),
            'days' => 3,
        ]));

        $response->assertOk();

        // B-E-H-J dengan E = 10 hari => 2 + 10 + 21 + 3 = 36 hari.
        $response->assertSee('36 hari');
        $response->assertSee('B -> E -> H -> J');

        // Durasi lama tetap ditampilkan sebagai pembanding.
        $response->assertSee('33 hari');
    }

    public function test_simulation_requires_activity_belonging_to_project(): void
    {
        $this->seed(ProjectSeeder::class);

        $project = Project::where('name', 'PT XYZ')->firstOrFail();
        $other = Project::create(['name' => 'Proyek Lain']);
        $foreignTask = $other->tasks()->create([
            'name' => 'Tugas Asing',
            'start_date' => '2026-09-01',
            'duration' => 3,
        ]);

        $response = $this->get(route('projects.criticalPath', [
            'project' => $project,
            'activity' => $foreignTask->getKey(),
            'days' => 3,
        ]));

        $response->assertSessionHasErrors('activity');
    }
}
