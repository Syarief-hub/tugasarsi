<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi form tambah/ubah tugas beserta baris dependensinya.
 *
 * Selain aturan field tugas, form ini menerima array `dependencies` dengan
 * struktur dependencies[i][predecessor_id|type|delay] yang divalidasi:
 * predecessor harus tugas lain dalam proyek yang sama (bukan diri sendiri),
 * tidak boleh duplikat, dan tidak boleh membentuk dependensi melingkar.
 */
class TaskRequest extends FormRequest
{
    /**
     * Apakah pengguna diizinkan mengirim request ini.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi field tugas dan dependensi.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $projectId = $this->project()?->getKey();
        $taskId = $this->task()?->getKey();

        $predecessorRules = [
            'required',
            'integer',
            Rule::exists('tasks', 'id')->where('project_id', $projectId),
        ];

        if ($taskId !== null) {
            $predecessorRules[] = Rule::notIn([$taskId]);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'duration' => ['required', 'integer', 'min:1', 'max:3650'],
            'priority' => ['required', Rule::in(['rendah', 'normal', 'tinggi'])],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')->where('project_id', $projectId),
                ...(($taskId !== null) ? [Rule::notIn([$taskId])] : []),
            ],
            'dependencies' => ['nullable', 'array'],
            'dependencies.*.predecessor_id' => $predecessorRules,
            'dependencies.*.type' => ['required', Rule::in(array_keys(TaskDependency::TYPES))],
            'dependencies.*.delay' => ['required', 'integer', 'min:0', 'max:3650'],
        ];
    }

    /**
     * Normalisasi input sebelum validasi: baris dependensi dirapikan dan
     * delay dikosongkan menjadi 0 (default).
     */
    protected function prepareForValidation(): void
    {
        $dependencies = $this->input('dependencies', []);

        if (!is_array($dependencies)) {
            $dependencies = [];
        }

        $rows = [];

        foreach ($dependencies as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rows[] = [
                'predecessor_id' => trim((string) ($row['predecessor_id'] ?? '')),
                'type' => (string) ($row['type'] ?? ''),
                'delay' => (($row['delay'] ?? '') === '' || $row['delay'] === null)
                    ? 0
                    : $row['delay'],
            ];
        }

        $this->merge(['dependencies' => $rows]);
    }

    /**
     * Pesan kesalahan berbahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $labels = [
            'name' => 'Nama tugas',
            'start_date' => 'Tanggal mulai',
            'duration' => 'Durasi (hari kerja)',
            'priority' => 'Prioritas',
            'color' => 'Warna',
            'parent_id' => 'Tugas induk',
            'dependencies.*.predecessor_id' => 'Predecessor',
            'dependencies.*.type' => 'Tipe dependensi',
            'dependencies.*.delay' => 'Delay',
        ];

        $templates = [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            'min' => ':attribute minimal :min.',
            'max' => ':attribute maksimal :max.',
            'regex' => ':attribute tidak sesuai format yang diminta.',
            'in' => ':attribute harus salah satu dari pilihan yang tersedia.',
            'exists' => ':attribute tidak ditemukan dalam proyek ini.',
            'array' => ':attribute harus berupa daftar.',
            'not_in' => ':attribute tidak boleh menunjuk tugas itu sendiri.',
        ];

        $messages = [];

        foreach ($labels as $field => $label) {
            foreach ($templates as $rule => $template) {
                $messages[$field . '.' . $rule] = str_replace(':attribute', $label, $template);
            }
        }

        $messages['dependencies.*.type.in'] =
            'Tipe dependensi harus Finish-Start, Start-Start, Finish-Finish, atau Start-Finish.';
        $messages['dependencies.*.predecessor_id.exists'] =
            'Predecessor harus berupa tugas lain dalam proyek yang sama.';
        $messages['dependencies.*.predecessor_id.not_in'] =
            'Tugas tidak boleh bergantung pada dirinya sendiri.';
        $messages['parent_id.not_in'] = 'Tugas induk tidak boleh menunjuk tugas itu sendiri.';
        $messages['color.regex'] = 'Warna harus berupa kode heksadesimal, contoh: #3b82f6.';
        $messages['priority.in'] = 'Prioritas harus berupa rendah, normal, atau tinggi.';

        return $messages;
    }

    /**
     * Label field untuk penampilan pesan kesalahan.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'dependencies.*.predecessor_id' => 'Predecessor',
            'dependencies.*.type' => 'Tipe dependensi',
            'dependencies.*.delay' => 'Delay',
        ];
    }

    /**
     * Pemeriksaan lanjutan: predecessor ganda dan dependensi melingkar.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateDuplicatePredecessors($validator);
            $this->validateNoCycle($validator);
        });
    }

    /**
     * Menolak predecessor yang sama dipilih lebih dari sekali.
     */
    private function validateDuplicatePredecessors(Validator $validator): void
    {
        $seen = [];

        foreach ($this->input('dependencies', []) as $index => $row) {
            $predecessorId = (string) ($row['predecessor_id'] ?? '');

            if ($predecessorId === '' || isset($seen[$predecessorId])) {
                if ($predecessorId !== '' && isset($seen[$predecessorId])) {
                    $validator->errors()->add(
                        "dependencies.{$index}.predecessor_id",
                        'Predecessor yang sama tidak boleh dipilih dua kali.'
                    );
                }

                continue;
            }

            $seen[$predecessorId] = $index;
        }
    }

    /**
     * Menolak dependensi melingkar dengan pesan berbahasa Indonesia yang
     * menyebut nama-nama tugas pada siklus tersebut.
     *
     * Graf dibangun dari seluruh dependensi tugas dalam proyek (dependensi
     * tugas yang sedang diedit diganti dengan isi form), lalu dicari siklus
     * dengan penelusuran berarah (DFS). Pemeriksaan hanya relevan saat mengubah
     * tugas yang sudah ada, karena tugas baru belum punya tugas yang bergantung
     * padanya sehingga tidak mungkin membentuk siklus.
     */
    private function validateNoCycle(Validator $validator): void
    {
        $task = $this->task();

        if ($task === null || $validator->errors()->isNotEmpty()) {
            return;
        }

        $project = $this->project();

        if ($project === null) {
            return;
        }

        $tasks = $project->tasks()->get(['id', 'name'])->keyBy('id');

        if ($tasks->isEmpty()) {
            return;
        }

        /** @var array<int, array<int, true>> $edges */
        $edges = [];

        // Dependensi tugas lain dalam proyek.
        $existing = $project->tasks()
            ->join('task_dependencies', 'task_dependencies.task_id', '=', 'tasks.id')
            ->where('tasks.id', '!=', $task->getKey())
            ->get([
                'task_dependencies.predecessor_id',
                'task_dependencies.task_id',
            ]);

        foreach ($existing as $row) {
            $edges[(int) $row->predecessor_id][(int) $row->task_id] = true;
        }

        // Isi form untuk tugas yang sedang diedit menggantikan dependensinya.
        foreach ($this->input('dependencies', []) as $row) {
            $predecessorId = (int) ($row['predecessor_id'] ?? 0);

            if ($predecessorId <= 0 || !$tasks->has($predecessorId)) {
                continue;
            }

            $edges[$predecessorId][(int) $task->getKey()] = true;
        }

        $cycle = $this->findCycle(array_keys($edges), $edges, $tasks);

        if ($cycle !== null) {
            $names = array_map(fn ($id) => $tasks->get($id)->name, $cycle);

            $validator->errors()->add(
                'dependencies',
                'Dependensi melingkar terdeteksi antar tugas: '
                    . implode(' → ', $names)
                    . '. Perbaiki dependensi tugas terlebih dahulu.'
            );
        }
    }

    /**
     * Cari satu siklus pada graf dependensi memakai DFS berwarna.
     *
     * @param  array<int, int>  $starts
     * @param  array<int, array<int, true>>  $edges
     * @param  \Illuminate\Support\Collection<int, Task>  $tasks
     * @return array<int, int>|null  Daftar id tugas pada siklus, atau null bila tidak ada.
     */
    private function findCycle(array $starts, array $edges, $tasks): ?array
    {
        $state = []; // 0 = putih, 1 = abu (dalam lintasan), 2 = hitam
        $path = [];
        $cycle = null;

        $visit = function (int $node) use (&$visit, &$state, &$path, &$cycle, $edges): void {
            if ($cycle !== null) {
                return;
            }

            $state[$node] = 1;
            $path[] = $node;

            foreach (array_keys($edges[$node] ?? []) as $next) {
                $next = (int) $next;

                if (($state[$next] ?? 0) === 0) {
                    $visit($next);

                    if ($cycle !== null) {
                        return;
                    }
                } elseif (($state[$next] ?? 0) === 1) {
                    $start = array_search($next, $path, true);
                    $cycle = array_slice($path, (int) $start);

                    return;
                }
            }

            array_pop($path);
            $state[$node] = 2;
        };

        foreach ($starts as $node) {
            if (($state[(int) $node] ?? 0) === 0) {
                $visit((int) $node);

                if ($cycle !== null) {
                    return $cycle;
                }
            }
        }

        return null;
    }

    /**
     * Proyek dari route bersarang {project}.
     */
    private function project(): ?Project
    {
        $project = $this->route('project');

        return $project instanceof Project ? $project : null;
    }

    /**
     * Tugas dari route {task}; null saat menyimpan tugas baru.
     */
    private function task(): ?Task
    {
        $task = $this->route('task');

        return $task instanceof Task ? $task : null;
    }
}
