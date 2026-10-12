<?php

namespace Tests\Feature;

use App\Models\Project;
use Database\Seeders\PemberkasanSeeder;
use Database\Seeders\ProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test: setiap route GET utama mengembalikan 200 untuk data seeder
 * (proyek PT XYZ dan proyek Pengelolaan Pemberkasan / aktivitas A-J).
 */
class SmokeRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Route GET global (tanpa parameter proyek) harus 200.
     */
    public function test_home_projects_and_holidays_pages_return_200(): void
    {
        $this->seed(ProjectSeeder::class);
        $this->seed(PemberkasanSeeder::class);

        $this->get(route('home'))->assertOk();
        $this->get(route('projects.index'))->assertOk();
        $this->get(route('holidays.index'))->assertOk();
    }

    /**
     * Seluruh route GET per proyek harus 200 untuk PT XYZ dan A-J.
     */
    public function test_per_project_pages_return_200_for_seeded_projects(): void
    {
        $this->seed(ProjectSeeder::class);
        $this->seed(PemberkasanSeeder::class);

        $projects = Project::whereIn('name', ['PT XYZ', 'Pengelolaan Pemberkasan'])
            ->orderBy('name')
            ->get();

        $this->assertCount(2, $projects, 'Dua proyek hasil seeder harus tersedia.');

        $routes = [
            'projects.show',
            'projects.edit',
            'projects.tasks.index',
            'projects.resources.index',
            'projects.gantt',
            'projects.pert',
            'projects.criticalPath',
        ];

        foreach ($projects as $project) {
            foreach ($routes as $routeName) {
                $this->get(route($routeName, $project))
                    ->assertOk();
            }
        }
    }
}
