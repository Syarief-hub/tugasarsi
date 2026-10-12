<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Services\PertService;
use Database\Seeders\PemberkasanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian analisis jalur kritis manual proyek A-J:
 * empat jalur dengan total 30, 33, 9, dan 20 hari; critical path
 * B-E-H-J (33 hari); serta simulasi keterlambatan E +3 hari (36 hari)
 * dan F +3 hari (durasi proyek tetap 33 hari karena F bukan jalur kritis).
 */
class CriticalPathAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function pemberkasan(): Project
    {
        $this->seed(PemberkasanSeeder::class);

        return Project::where('name', 'Pengelolaan Pemberkasan')->firstOrFail();
    }

    public function test_analysis_lists_exactly_four_paths_with_expected_totals(): void
    {
        $project = $this->pemberkasan();

        $analysis = app(PertService::class)->analyze($project);

        $totals = array_map(fn (array $path) => $path['total'], $analysis['paths']);

        // Diurutkan terpanjang-atas: 33, 30, 20, 9.
        $this->assertSame([33, 30, 20, 9], $totals);

        $pathsByCode = [];

        foreach ($analysis['paths'] as $path) {
            $pathsByCode[implode('-', $path['codes'])] = $path['total'];
        }

        $this->assertSame(4, count($analysis['paths']));
        $this->assertSame(33, $pathsByCode['B-E-H-J']);
        $this->assertSame(30, $pathsByCode['A-D-H-J']);
        $this->assertSame(20, $pathsByCode['C-G-I-J']);
        $this->assertSame(9, $pathsByCode['B-F-J']);
    }

    public function test_critical_path_is_b_e_h_j_with_33_days(): void
    {
        $project = $this->pemberkasan();

        $analysis = app(PertService::class)->analyze($project);

        $this->assertSame(['B', 'E', 'H', 'J'], $analysis['critical']['codes']);
        $this->assertSame(33, $analysis['critical']['total']);
        $this->assertSame(33, $analysis['duration']);

        // Halaman analisis menampilkan jalur kritis dan durasi proyek.
        $response = $this->get(route('projects.criticalPath', $project));

        $response->assertOk();
        $response->assertSee('B -> E -> H -> J');
        $response->assertSee('Total 33 hari');

        // Aktivitas pada jalur kritis ditandai; F (bukan jalur kritis) tidak.
        $f = $project->tasks()->where('name', 'Konfigurasi')->firstOrFail();

        $this->assertFalse($analysis['activities'][$f->getKey()]['is_critical']);
    }

    public function test_simulation_delaying_e_by_3_days_extends_duration_to_36(): void
    {
        $project = $this->pemberkasan();

        $e = $project->tasks()->where('name', 'Instalasi')->firstOrFail();

        $response = $this->get(route('projects.criticalPath', [
            'project' => $project,
            'activity' => $e->getKey(),
            'days' => 3,
        ]));

        $response->assertOk();

        // B-E-H-J dengan E = 10 => 2 + 10 + 21 + 3 = 36 hari.
        $response->assertSee('36 hari');
        $response->assertSee('B -> E -> H -> J');

        // Durasi lama tetap ditampilkan sebagai pembanding.
        $response->assertSee('33 hari');
    }

    public function test_simulation_delaying_f_by_3_days_keeps_duration_at_33(): void
    {
        $project = $this->pemberkasan();

        $f = $project->tasks()->where('name', 'Konfigurasi')->firstOrFail();

        $response = $this->get(route('projects.criticalPath', [
            'project' => $project,
            'activity' => $f->getKey(),
            'days' => 3,
        ]));

        $response->assertOk();

        // F bukan jalur kritis: B-F-J menjadi 2 + 7 + 3 = 12 hari,
        // tetap di bawah 33 sehingga durasi proyek tidak berubah.
        $simulation = app(PertService::class)->simulate($project, $f->getKey(), 3);

        $this->assertSame(33, $simulation['base_duration']);
        $this->assertSame(33, $simulation['new_duration']);
        $this->assertSame(['B', 'E', 'H', 'J'], $simulation['new_critical']['codes']);

        // Halaman menampilkan hasil simulasi dengan durasi tetap 33 hari.
        $response->assertSee('Hasil simulasi');
        $response->assertSee('33 hari');
    }
}
