<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian CRUD proyek, tugas, resource, dan hari libur:
 * tambah, ubah, hapus; form kosong menghasilkan pesan validasi
 * berbahasa Indonesia; libur dengan tanggal duplikat ditolak.
 */
class CrudTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------------------------------------------ */
    /* Proyek                                                              */
    /* ------------------------------------------------------------------ */

    public function test_project_create_update_delete(): void
    {
        $response = $this->post(route('projects.store'), [
            'name' => 'Proyek CRUD',
            'organization' => 'PT CRUD',
            'website' => 'https://crud.test',
            'description' => 'Deskripsi proyek CRUD.',
        ]);

        $project = Project::where('name', 'Proyek CRUD')->firstOrFail();

        $response->assertRedirect(route('projects.show', $project));
        $response->assertSessionHas('success', 'Proyek berhasil dibuat.');

        // Ubah.
        $response = $this->put(route('projects.update', $project), [
            'name' => 'Proyek CRUD Diubah',
            'organization' => 'PT Diubah',
            'website' => 'https://diubah.test',
            'description' => 'Deskripsi baru.',
        ]);

        $response->assertRedirect(route('projects.show', $project));
        $response->assertSessionHas('success', 'Proyek berhasil diperbarui.');

        $this->assertDatabaseHas('projects', [
            'id' => $project->getKey(),
            'name' => 'Proyek CRUD Diubah',
            'organization' => 'PT Diubah',
        ]);

        // Hapus.
        $response = $this->delete(route('projects.destroy', $project));

        $response->assertRedirect(route('projects.index'));
        $response->assertSessionHas('success', 'Proyek berhasil dihapus.');

        $this->assertDatabaseMissing('projects', ['id' => $project->getKey()]);
    }

    public function test_empty_project_form_shows_indonesian_validation_messages(): void
    {
        $response = $this->post(route('projects.store'), []);

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'name' => 'Nama proyek wajib diisi.',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Tugas                                                               */
    /* ------------------------------------------------------------------ */

    public function test_task_create_update_delete(): void
    {
        $project = Project::create(['name' => 'Proyek Tugas CRUD']);

        $response = $this->post(route('projects.tasks.store', $project), [
            'name' => 'Tugas Pertama',
            'start_date' => '2026-08-03',
            'duration' => 5,
            'priority' => 'tinggi',
            'color' => '#3355ff',
        ]);

        $task = $project->tasks()->where('name', 'Tugas Pertama')->firstOrFail();

        $response->assertRedirect(route('projects.tasks.index', $project));
        $response->assertSessionHas('success', 'Tugas berhasil ditambahkan.');

        // Ubah.
        $response = $this->put(route('projects.tasks.update', [$project, $task]), [
            'name' => 'Tugas Pertama Diubah',
            'start_date' => '2026-08-04',
            'duration' => 7,
            'priority' => 'normal',
            'color' => '#112233',
        ]);

        $response->assertRedirect(route('projects.tasks.index', $project));
        $response->assertSessionHas('success', 'Tugas berhasil diperbarui.');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->getKey(),
            'name' => 'Tugas Pertama Diubah',
            'duration' => 7,
            'priority' => 'normal',
        ]);

        // Hapus.
        $response = $this->delete(route('projects.tasks.destroy', [$project, $task]));

        $response->assertRedirect(route('projects.tasks.index', $project));
        $response->assertSessionHas('success', 'Tugas berhasil dihapus.');

        $this->assertDatabaseMissing('tasks', ['id' => $task->getKey()]);
    }

    public function test_empty_task_form_shows_indonesian_validation_messages(): void
    {
        $project = Project::create(['name' => 'Proyek Tugas Kosong']);

        $response = $this->post(route('projects.tasks.store', $project), []);

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'name' => 'Nama tugas wajib diisi.',
            'start_date' => 'Tanggal mulai wajib diisi.',
            'duration' => 'Durasi (hari kerja) wajib diisi.',
            'priority' => 'Prioritas wajib diisi.',
            'color' => 'Warna wajib diisi.',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Resource                                                            */
    /* ------------------------------------------------------------------ */

    public function test_resource_create_update_delete(): void
    {
        $project = Project::create(['name' => 'Proyek Resource CRUD']);

        $response = $this->post(route('projects.resources.store', $project), [
            'name' => 'Budi',
            'phone' => '0812-0000-0000',
            'email' => 'budi@contoh.test',
            'role' => 'Programmer',
            'salary' => 10000000,
        ]);

        $resource = $project->resources()->where('name', 'Budi')->firstOrFail();

        $response->assertRedirect(route('projects.resources.index', $project));
        $response->assertSessionHas('success', 'Resource berhasil ditambahkan.');

        // Ubah.
        $response = $this->put(route('projects.resources.update', [$project, $resource]), [
            'name' => 'Budi Santoso',
            'phone' => '0812-1111-1111',
            'email' => 'budi.santoso@contoh.test',
            'role' => 'Tech Lead',
            'salary' => 15000000,
        ]);

        $response->assertRedirect(route('projects.resources.index', $project));
        $response->assertSessionHas('success', 'Resource berhasil diperbarui.');

        $this->assertDatabaseHas('resources', [
            'id' => $resource->getKey(),
            'name' => 'Budi Santoso',
            'role' => 'Tech Lead',
        ]);

        // Hapus.
        $response = $this->delete(route('projects.resources.destroy', [$project, $resource]));

        $response->assertRedirect(route('projects.resources.index', $project));
        $response->assertSessionHas('success', 'Resource berhasil dihapus.');

        $this->assertDatabaseMissing('resources', ['id' => $resource->getKey()]);
    }

    public function test_empty_resource_form_shows_indonesian_validation_messages(): void
    {
        $project = Project::create(['name' => 'Proyek Resource Kosong']);

        $response = $this->post(route('projects.resources.store', $project), []);

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'name' => 'Nama wajib diisi.',
            'role' => 'Peran wajib diisi.',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Hari libur                                                          */
    /* ------------------------------------------------------------------ */

    public function test_holiday_create_update_delete(): void
    {
        $response = $this->post(route('holidays.store'), [
            'name' => 'Libur Uji',
            'date' => '2026-12-25',
        ]);

        $holiday = Holiday::where('date', '2026-12-25')->firstOrFail();

        $response->assertRedirect(route('holidays.index'));
        $response->assertSessionHas('success', 'Hari libur berhasil ditambahkan.');

        // Ubah.
        $response = $this->put(route('holidays.update', $holiday), [
            'name' => 'Libur Uji Diubah',
            'date' => '2026-12-26',
        ]);

        $response->assertRedirect(route('holidays.index'));
        $response->assertSessionHas('success', 'Hari libur berhasil diperbarui.');

        $this->assertDatabaseHas('holidays', [
            'id' => $holiday->getKey(),
            'name' => 'Libur Uji Diubah',
            'date' => '2026-12-26',
        ]);

        // Hapus.
        $response = $this->delete(route('holidays.destroy', $holiday));

        $response->assertRedirect(route('holidays.index'));
        $response->assertSessionHas('success', 'Hari libur berhasil dihapus.');

        $this->assertDatabaseMissing('holidays', ['id' => $holiday->getKey()]);
    }

    public function test_duplicate_holiday_date_is_rejected(): void
    {
        $this->post(route('holidays.store'), [
            'name' => 'Libur Natal',
            'date' => '2026-12-25',
        ])->assertSessionHas('success');

        $response = $this->post(route('holidays.store'), [
            'name' => 'Libur Ganda',
            'date' => '2026-12-25',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'date' => 'Tanggal tersebut sudah terdaftar sebagai hari libur.',
        ]);

        $this->assertSame(1, Holiday::count());
    }

    public function test_empty_holiday_form_shows_indonesian_validation_messages(): void
    {
        $response = $this->post(route('holidays.store'), []);

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'name' => 'Nama hari libur wajib diisi.',
            'date' => 'Tanggal wajib diisi.',
        ]);
    }
}
