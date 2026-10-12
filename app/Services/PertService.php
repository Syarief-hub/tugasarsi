<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Validation\ValidationException;

/**
 * Analisis jalur kritis manual (PERT/CPM) per proyek.
 *
 * Konvensi:
 * - Durasi dihitung sebagai jumlah hari polos (bukan hari kerja),
 *   sesuai perhitungan manual pada materi (mis. A-J total 33 hari).
 * - Kode aktivitas (A, B, C, ...) ditetapkan berdasarkan urutan ID tugas.
 * - ES = durasi paling awal semua predecessor; EF = ES + durasi.
 * - LF = batas akhir paling awal semua successor; LS = LF - durasi.
 * - Slack = LS - ES; slack 0 = aktivitas kritis.
 * - Semua jalur dari node awal (tanpa predecessor) ke node akhir
 *   (tanpa successor) dihitung; jalur dengan total terpanjang = jalur
 *   kritis, totalnya = durasi proyek.
 * - Simulasi keterlambatan: durasi satu aktivitas ditambah N hari lalu
 *   seluruh analisis dihitung ulang.
 */
class PertService
{
    /**
     * Batas pengaman jumlah jalur yang dienumerasi.
     */
    private const MAX_PATHS = 1000;

    /**
     * Menjalankan analisis CPM untuk seluruh tugas proyek.
     *
     * @param  array<int, int>  $durationOverrides  id tugas => durasi baru (untuk simulasi)
     * @return array{
     *     activities: array<int, array{id: int, code: string, name: string, duration: int, predecessors: array<int, string>, es: int, ef: int, ls: int, lf: int, slack: int, is_critical: bool}>,
     *     order: array<int, int>,
     *     paths: array<int, array{codes: array<int, string>, total: int}>,
     *     critical: ?array{codes: array<int, string>, total: int},
     *     duration: int
     * }
     *
     * @throws ValidationException Bila dependensi membentuk siklus.
     */
    public function analyze(Project $project, array $durationOverrides = []): array
    {
        $tasks = $project->tasks()
            ->with('dependencies')
            ->orderBy('id')
            ->get()
            ->keyBy(fn (Task $task) => (int) $task->getKey());

        $ids = $tasks->keys()->all();
        $codes = [];

        foreach ($ids as $index => $id) {
            $codes[$id] = $this->codeFor($index);
        }

        $durations = [];
        $preds = [];
        $succs = [];

        foreach ($tasks as $id => $task) {
            $durations[$id] = max(1, (int) ($durationOverrides[$id] ?? $task->duration));
            $preds[$id] = [];
            $succs[$id] = [];
        }

        foreach ($tasks as $id => $task) {
            foreach ($task->dependencies as $dependency) {
                $from = (int) $dependency->predecessor_id;

                if (! isset($durations[$from]) || $from === $id) {
                    continue;
                }

                $preds[$id][$from] = true;
                $succs[$from][$id] = true;
            }
        }

        // Kumpulkan seluruh edge dulu, baru konversi ke daftar berurutan
        // agar penambahan edge tidak menimpa struktur yang sudah dikonversi.
        foreach ($ids as $id) {
            $preds[$id] = array_keys($preds[$id]);
            $succs[$id] = array_keys($succs[$id]);
        }

        $order = $this->topologicalOrder($ids, $succs);

        // Forward pass: ES = batas paling akhir dari seluruh predecessor.
        $ES = [];
        $EF = [];

        foreach ($order as $id) {
            $es = 0;

            foreach ($preds[$id] as $from) {
                $es = max($es, $EF[$from]);
            }

            $ES[$id] = $es;
            $EF[$id] = $es + $durations[$id];
        }

        $duration = $EF === [] ? 0 : max($EF);

        // Backward pass: LF = batas paling awal dari seluruh successor.
        $LF = [];
        $LS = [];

        foreach (array_reverse($order) as $id) {
            $lf = $duration;

            if ($succs[$id] !== []) {
                $lf = null;

                foreach ($succs[$id] as $to) {
                    $lf = $lf === null ? $LS[$to] : min($lf, $LS[$to]);
                }
            }

            $LF[$id] = $lf;
            $LS[$id] = $lf - $durations[$id];
        }

        $activities = [];

        foreach ($order as $id) {
            $task = $tasks->get($id);
            $slack = $LS[$id] - $ES[$id];

            $activities[$id] = [
                'id' => $id,
                'code' => $codes[$id],
                'name' => $task->name,
                'duration' => $durations[$id],
                'predecessors' => array_map(fn (int $from) => $codes[$from], $preds[$id]),
                'es' => $ES[$id],
                'ef' => $EF[$id],
                'ls' => $LS[$id],
                'lf' => $LF[$id],
                'slack' => $slack,
                'is_critical' => $slack === 0,
            ];
        }

        $paths = $this->enumeratePaths($ids, $preds, $succs, $durations, $codes);

        usort($paths, function (array $a, array $b) {
            return $b['total'] <=> $a['total'];
        });

        return [
            'activities' => $activities,
            'order' => $order,
            'paths' => $paths,
            'critical' => $paths[0] ?? null,
            'duration' => $duration,
        ];
    }

    /**
     * Simulasi keterlambatan satu aktivitas: durasi aktivitas ditambah
     * $extraDays hari, lalu analisis dihitung ulang.
     *
     * @return array{
     *     activity: array{id: int, code: string, name: string},
     *     extra_days: int,
     *     base_duration: int,
     *     new_duration: int,
     *     base_critical: ?array{codes: array<int, string>, total: int},
     *     new_critical: ?array{codes: array<int, string>, total: int}
     * }
     *
     * @throws ValidationException Bila aktivitas bukan milik proyek.
     */
    public function simulate(Project $project, int $taskId, int $extraDays): array
    {
        $base = $this->analyze($project);

        if (! isset($base['activities'][$taskId])) {
            throw ValidationException::withMessages([
                'activity' => 'Aktivitas tidak ditemukan pada proyek ini.',
            ]);
        }

        $activity = $base['activities'][$taskId];
        $extra = max(0, $extraDays);

        $simulated = $this->analyze($project, [$taskId => $activity['duration'] + $extra]);

        return [
            'activity' => [
                'id' => $taskId,
                'code' => $activity['code'],
                'name' => $activity['name'],
            ],
            'extra_days' => $extra,
            'base_duration' => $base['duration'],
            'new_duration' => $simulated['duration'],
            'base_critical' => $base['critical'],
            'new_critical' => $simulated['critical'],
        ];
    }

    /**
     * Kode aktivitas berdasarkan indeks urutan: 0 = A, 1 = B, ... 25 = Z,
     * 26 = AA, dst.
     */
    public function codeFor(int $index): string
    {
        if ($index < 26) {
            return chr(65 + $index);
        }

        return 'A' . chr(65 + ($index % 26));
    }

    /**
     * Urutan topologis (Kahn); melempar ValidationException bila siklus.
     *
     * @param  array<int, int>  $ids
     * @param  array<int, array<int, int>>  $succs
     * @return array<int, int>
     *
     * @throws ValidationException
     */
    private function topologicalOrder(array $ids, array $succs): array
    {
        $indegree = array_fill_keys($ids, 0);

        foreach ($succs as $to) {
            foreach ($to as $id) {
                $indegree[$id]++;
            }
        }

        $queue = array_values(array_filter($ids, fn (int $id) => $indegree[$id] === 0));
        sort($queue);

        $order = [];

        while ($queue !== []) {
            $id = array_shift($queue);
            $order[] = $id;

            foreach ($succs[$id] as $next) {
                if (--$indegree[$next] === 0) {
                    $queue[] = $next;
                    sort($queue);
                }
            }
        }

        if (count($order) !== count($ids)) {
            throw ValidationException::withMessages([
                'tasks' => 'Dependensi antar tugas membentuk siklus sehingga analisis jalur kritis tidak dapat dihitung.',
            ]);
        }

        return $order;
    }

    /**
     * Enumerasi semua jalur node awal (tanpa predecessor) ke node akhir
     * (tanpa successor), lengkap dengan total durasinya.
     *
     * @param  array<int, int>  $ids
     * @param  array<int, array<int, int>>  $preds
     * @param  array<int, array<int, int>>  $succs
     * @param  array<int, int>  $durations
     * @param  array<int, string>  $codes
     * @return array<int, array{codes: array<int, string>, total: int}>
     */
    private function enumeratePaths(array $ids, array $preds, array $succs, array $durations, array $codes): array
    {
        $starts = array_values(array_filter($ids, fn (int $id) => $preds[$id] === []));
        $paths = [];

        $walk = function (int $id, array $trail) use (&$walk, &$paths, $succs, $durations, $codes): void {
            $trail[] = $id;

            if ($succs[$id] === []) {
                $total = 0;

                foreach ($trail as $node) {
                    $total += $durations[$node];
                }

                $paths[] = [
                    'codes' => array_map(fn (int $node) => $codes[$node], $trail),
                    'total' => $total,
                ];

                return;
            }

            foreach ($succs[$id] as $next) {
                if (count($paths) >= self::MAX_PATHS) {
                    return;
                }

                $walk($next, $trail);
            }
        };

        foreach ($starts as $start) {
            if (count($paths) >= self::MAX_PATHS) {
                break;
            }

            $walk($start, []);
        }

        return $paths;
    }
}
