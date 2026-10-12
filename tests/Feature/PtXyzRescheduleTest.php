<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\Project;
use Database\Seeders\ProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pengujian penjadwalan ulang proyek PT XYZ lewat tombol "Jadwalkan Ulang"
 * dengan hari libur custom 17 Juli 2026:
 *
 * - Analisis Kebutuhan : 30/06 - 06/07 (akhir pekan dilewati)
 * - Analisis Sistem    : mulai 03/07 (SS+3), selesai 14/07
 * - Perancangan        : mulai 10/07 (SS+5), selesai 21/07 (17/07 LIBUR)
 *
 * Tanggal selesai proyek dilaporkan apa adanya (tanpa mengubah logika
 * aplikasi): tanggal selesai = tanggal selesai tugas paling akhir.
 */
class PtXyzRescheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_reschedule_pt_xyz_skips_custom_holiday_and_reports_project_end_date(): void
    {
        $this->seed(ProjectSeeder::class);

        $project = Project::where('name', 'PT XYZ')->firstOrFail();

        // Libur custom 17 Juli 2026 (Jumat) melalui rute CRUD hari libur.
        $this->post(route('holidays.store'), [
            'name' => 'Libur Khusus',
            'date' => '2026-07-17',
        ])->assertSessionHas('success');

        // Jadwalkan ulang seluruh tugas lewat tombol di halaman proyek.
        $response = $this->post(route('projects.schedule', $project));

        $response->assertRedirect(route('projects.show', $project));
        $response->assertSessionHas('success');

        $message = session('success');
        $this->assertStringContainsString('tugas berhasil dijadwalkan ulang', $message);

        $tasks = $project->tasks()->orderBy('id')->get()->keyBy('name');

        // Analisis Kebutuhan: Selasa 30/06 + 5 hari kerja -> Senin 06/07.
        $kebutuhan = $tasks->get('Analisis Kebutuhan');
        $this->assertTrue($kebutuhan->start_date->equalTo(Carbon::parse('2026-06-30')));
        $this->assertTrue($kebutuhan->end_date->equalTo(Carbon::parse('2026-07-06')));

        // Analisis Sistem: SS+3 -> mulai 03/07, 8 hari kerja -> selesai 14/07.
        $sistem = $tasks->get('Analisis Sistem');
        $this->assertTrue($sistem->start_date->equalTo(Carbon::parse('2026-07-03')));
        $this->assertTrue(
            $sistem->end_date->equalTo(Carbon::parse('2026-07-14')),
            'Analisis Sistem harus selesai 14 Juli 2026, aktual: ' . $sistem->end_date->toDateString()
        );

        // Perancangan: SS+5 -> mulai 10/07, 7 hari kerja dengan 17/07 libur
        // -> 10, 13, 14, 15, 16, 20, 21 => selesai 21/07 (bukan 20/07).
        $perancangan = $tasks->get('Perancangan');
        $this->assertTrue($perancangan->start_date->equalTo(Carbon::parse('2026-07-10')));
        $this->assertTrue(
            $perancangan->end_date->equalTo(Carbon::parse('2026-07-21')),
            'Perancangan harus selesai 21 Juli 2026 (17 Juli dilewati), aktual: '
                . $perancangan->end_date->toDateString()
        );

        // Tidak ada tugas yang selesai tepat pada 17 Juli 2026 (hari libur).
        $endsOnHoliday = $tasks->contains(
            fn ($task) => $task->end_date->equalTo(Carbon::parse('2026-07-17'))
        );

        $this->assertFalse($endsOnHoliday, 'Tidak ada tugas yang boleh selesai pada hari libur 17 Juli 2026.');

        // Tanggal selesai proyek APA ADANYA: tanggal selesai tugas paling
        // akhir setelah penjadwalan ulang. Dihitung dari data tersimpan,
        // bukan hard-code, agar mencerminkan logika aplikasi.
        $projectEnd = $tasks->map(fn ($task) => $task->end_date)->max();

        $this->assertNotNull($projectEnd);

        // Dengan libur 17 Juli rantai berakhir: Implementasi 22/07-28/07,
        // Pengujian 29/07-30/07, Deployment 31/07-03/08 => selesai 03/08/2026.
        $this->assertTrue(
            $projectEnd->equalTo(Carbon::parse('2026-08-03')),
            'Tanggal selesai proyek PT XYZ: ' . $projectEnd->toDateString()
        );

        // Rantai penjadwalan ulang terverifikasi end-to-end lewat halaman proyek.
        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Deployment & Pemeliharaan');
    }
}
