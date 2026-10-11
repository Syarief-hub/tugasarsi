<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Layanan penjadwalan tugas berdasarkan hari kerja.
 *
 * Konvensi yang dipakai (semua hitungan hari kerja, Sabtu-Minggu dan
 * hari libur dilewati kecuali mode proyek menentukan "tetap jalan"):
 *
 * - Tanggal selesai bersifatinklusif: tanggal mulai dihitung sebagai hari
 *   ke-1 bila hari kerja (mis. durasi 5 hari kerja sejak 30 Juni 2026 = 6 Juli 2026).
 * - FS + delay : mulai = hari kerja pertama setelah selesai predecessor, ditambah `delay` hari kerja.
 * - SS + delay : mulai = mulai predecessor + `delay` hari kerja.
 * - FF + delay : selesai = selesai predecessor + `delay` hari kerja.
 * - SF + delay : selesai = mulai predecessor + `delay` hari kerja.
 * - Durasi delay selalu dalam hari kerja.
 * - Tugas parent (punya sub-tugas): mulai = mulai paling awal sub-tugas,
 *   selesai = selesai paling akhir sub-tugas, durasi = jumlah hari kerja di rentang itu.
 * - Critical path (CPM) dihitung pada ruang indeks hari kerja (durasi murni),
 *   forward pass + backward pass, slack = LS - ES, slack 0 = jalur kritis.
 */
class SchedulingService
{
    /**
     * Batas pengaman jumlah hari yang di-scan saat mencari hari kerja.
     */
    private const MAX_SCAN_DAYS = 36600;

    /**
     * Cache daftar hari libur agar tidak query berulang di dalam satu rentang.
     *
     * @var array<int, array{date: Carbon, repeats: string}>|null
     */
    private ?array $holidayCache = null;

    /* ------------------------------------------------------------------ */
    /* Kalender hari kerja                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Apakah tanggal ini hari kerja menurut aturan proyek?
     */
    public function isWorkingDay(Project $project, CarbonInterface $date): bool
    {
        $weekday = (int) $date->format('w'); // 0 = Minggu .. 6 = Sabtu

        if ($project->weekend_mode !== 'work' && ($weekday === 0 || $weekday === 6)) {
            return false;
        }

        if ($project->holiday_mode !== 'work' && $this->isHoliday($date)) {
            return false;
        }

        return true;
    }

    /**
     * Apakah tanggal ini termasuk hari libur (satu kali maupun berulang)?
     */
    public function isHoliday(CarbonInterface $date): bool
    {
        foreach ($this->holidayList() as $holiday) {
            $observed = $holiday['date'];

            $matched = match ($holiday['repeats']) {
                'yearly' => $observed->format('m-d') === $date->format('m-d'),
                'monthly' => $observed->format('d') === $date->format('d'),
                'weekly' => $observed->format('w') === $date->format('w'),
                default => $observed->format('Y-m-d') === $date->format('Y-m-d'),
            };

            if ($matched) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tanggal selesai = tanggal mulai + durasi dalam HARI KERJA (inklusif).
     *
     * Bila tanggal mulai bukan hari kerja, penghitungan dimulai pada hari
     * kerja pertama sesudahnya.
     *
     * @throws RuntimeException Bila tidak ditemukan hari kerja sama sekali.
     */
    public function calculateEndDate(Project $project, CarbonInterface|string $start, int $duration): Carbon
    {
        $duration = max($duration, 1);
        $cursor = $this->toCarbon($start);
        $count = 0;
        $scanned = 0;

        while ($count < $duration) {
            if ($this->isWorkingDay($project, $cursor)) {
                $count++;
            }

            if ($count >= $duration) {
                break;
            }

            $cursor->addDay();

            if (++$scanned > self::MAX_SCAN_DAYS) {
                throw new RuntimeException(
                    'Perhitungan tanggal selesai tidak menemukan hari kerja. Periksa mode akhir pekan, hari libur, dan durasi tugas.'
                );
            }
        }

        return $cursor;
    }

    /**
     * Majukan tanggal sebanyak `steps` hari kerja (setiap langkah = hari kerja
     * berikutnya setelah tanggal berjalan). steps = 0 mengembalikan tanggal sama.
     *
     * @throws RuntimeException Bila tidak ditemukan hari kerja sama sekali.
     */
    public function addWorkingDays(Project $project, CarbonInterface|string $date, int $steps): Carbon
    {
        $cursor = $this->toCarbon($date);

        if ($steps <= 0) {
            return $cursor;
        }

        $moved = 0;
        $scanned = 0;

        while ($moved < $steps) {
            $cursor->addDay();

            if ($this->isWorkingDay($project, $cursor)) {
                $moved++;
            }

            if (++$scanned > self::MAX_SCAN_DAYS) {
                throw new RuntimeException(
                    'Perhitungan hari kerja maju tidak menemukan hari kerja. Periksa mode akhir pekan dan hari libur proyek.'
                );
            }
        }

        return $cursor;
    }

    /**
     * Mundurkan tanggal sebanyak `steps` hari kerja.
     *
     * @throws RuntimeException Bila tidak ditemukan hari kerja sama sekali.
     */
    public function subtractWorkingDays(Project $project, CarbonInterface|string $date, int $steps): Carbon
    {
        $cursor = $this->toCarbon($date);

        if ($steps <= 0) {
            return $cursor;
        }

        $moved = 0;
        $scanned = 0;

        while ($moved < $steps) {
            $cursor->subDay();

            if ($this->isWorkingDay($project, $cursor)) {
                $moved++;
            }

            if (++$scanned > self::MAX_SCAN_DAYS) {
                throw new RuntimeException(
                    'Perhitungan hari kerja mundur tidak menemukan hari kerja. Periksa mode akhir pekan dan hari libur proyek.'
                );
            }
        }

        return $cursor;
    }

    /**
     * Hari kerja pertama yang tidak lebih awal dari tanggal diberikan.
     *
     * @throws RuntimeException Bila tidak ditemukan hari kerja sama sekali.
     */
    public function firstWorkingDayOnOrAfter(Project $project, CarbonInterface|string $date): Carbon
    {
        $cursor = $this->toCarbon($date);
        $scanned = 0;

        while (!$this->isWorkingDay($project, $cursor)) {
            $cursor->addDay();

            if (++$scanned > self::MAX_SCAN_DAYS) {
                throw new RuntimeException(
                    'Tidak menemukan hari kerja setelah tanggal tersebut. Periksa mode akhir pekan dan hari libur proyek.'
                );
            }
        }

        return $cursor;
    }

    /**
     * Jumlah hari kerja dari `from` sampai `to` (kedua ujung dihitung bila hari kerja).
     */
    public function workingDaysBetween(Project $project, CarbonInterface|string $from, CarbonInterface|string $to): int
    {
        $cursor = $this->toCarbon($from);
        $end = $this->toCarbon($to);

        if ($cursor->greaterThan($end)) {
            return 0;
        }

        $count = 0;
        $scanned = 0;

        while ($cursor->lessThanOrEqualTo($end)) {
            if ($this->isWorkingDay($project, $cursor)) {
                $count++;
            }

            $cursor->addDay();

            if (++$scanned > self::MAX_SCAN_DAYS) {
                throw new RuntimeException('Perhitungan hari kerja melewati batas aman. Rentang tanggal terlalu panjang.');
            }
        }

        return $count;
    }

    /* ------------------------------------------------------------------ */
    /* Penjadwalan ulang                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Menjadwalkan ulang semua tugas dalam satu proyek berdasarkan dependensi,
     * lalu menerapkan rollup untuk tugas parent.
     *
     * @return array{rescheduled: int, critical: array<int, string>, duration: int}
     *
     * @throws ValidationException Bila terdeteksi dependensi melingkar.
     */
    public function reschedule(Project $project): array
    {
        $this->holidayCache = null;

        $tasks = $project->tasks()
            ->with('dependencies')
            ->get()
            ->keyBy(fn (Task $task) => (int) $task->getKey());

        if ($tasks->isEmpty()) {
            return ['rescheduled' => 0, 'critical' => [], 'duration' => 0];
        }

        $order = $this->topologicalOrder($tasks);
        $rescheduled = 0;

        foreach ($order as $taskId) {
            $task = $tasks->get($taskId);

            if ($task === null) {
                continue;
            }

            $rescheduled++;

            $children = $tasks->filter(
                fn (Task $candidate) => $candidate->parent_id !== null
                    && (int) $candidate->parent_id === $taskId
            );

            if ($children->isNotEmpty()) {
                // Rollup: mulai = paling awal, selesai = paling akhir, durasi = hari kerja di rentang itu.
                $starts = [];
                $ends = [];

                foreach ($children as $child) {
                    $starts[] = $child->start_date->copy();
                    $ends[] = $this->calculateEndDate($project, $child->start_date, (int) $child->duration);
                }

                $start = collect($starts)->min();
                $end = collect($ends)->max();
                $duration = max(1, $this->workingDaysBetween($project, $start, $end));

                $task->update(['start_date' => $start, 'duration' => $duration]);
                $rescheduled++;

                continue;
            }

            $start = $this->resolveStart($project, $task, $tasks);

            if ($start === null) {
                // Tugas tanpa dependensi: pertahankan tanggal tersimpan (dijaga tetap hari kerja).
                $start = $this->firstWorkingDayOnOrAfter($project, $task->start_date);
            }

            if (!$start->equalTo($task->start_date)) {
                $task->update(['start_date' => $start]);
            }
        }

        $critical = $this->criticalPath($project);

        return [
            'rescheduled' => $rescheduled,
            'critical' => $critical['critical'],
            'duration' => $critical['duration'],
        ];
    }

    /**
     * Menghitung tanggal mulai paling awal untuk satu tugas leaf dari seluruh
     * predecessor-nya. Mengembalikan null bila tugas tidak punya dependensi.
     *
     * @param  Collection<int, Task>  $tasks
     */
    private function resolveStart(Project $project, Task $task, Collection $tasks): ?Carbon
    {
        $candidates = [];
        $duration = max(1, (int) $task->duration);

        foreach ($task->dependencies as $dependency) {
            $predecessor = $tasks->get((int) $dependency->predecessor_id);

            if ($predecessor === null) {
                continue;
            }

            $delay = max(0, (int) $dependency->delay);
            $predecessorStart = $predecessor->start_date->copy();
            $predecessorEnd = $this->calculateEndDate($project, $predecessorStart, (int) $predecessor->duration);

            $candidates[] = match ($dependency->type) {
                'SS' => $this->addWorkingDays($project, $predecessorStart, $delay),
                'FF' => $this->subtractWorkingDays(
                    $project,
                    $this->addWorkingDays($project, $predecessorEnd, $delay),
                    $duration - 1
                ),
                'SF' => $this->subtractWorkingDays(
                    $project,
                    $this->addWorkingDays($project, $predecessorStart, $delay),
                    $duration - 1
                ),
                // FS (default): hari kerja pertama setelah selesai, ditambah delay.
                default => $this->addWorkingDays($project, $predecessorEnd, $delay + 1),
            };
        }

        if ($candidates === []) {
            return null;
        }

        $start = $candidates[0];

        foreach ($candidates as $candidate) {
            if ($candidate->greaterThan($start)) {
                $start = $candidate;
            }
        }

        return $start;
    }

    /* ------------------------------------------------------------------ */
    /* Critical Path Method (CPM)                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Forward pass + backward pass pada indeks hari kerja.
     *
     * @return array{
     *     duration: int,
     *     critical: array<int, string>,
     *     slack: array<int, int>,
     *     tasks: array<int, array{name: string, es: int, ef: int, ls: int, lf: int, slack: int, is_critical: bool}>,
     *     order: array<int, int>
     * }
     *
     * @throws ValidationException Bila terdeteksi dependensi melingkar.
     */
    public function criticalPath(Project $project): array
    {
        $this->holidayCache = null;

        $tasks = $project->tasks()->get()->keyBy(fn (Task $task) => (int) $task->getKey());

        if ($tasks->isEmpty()) {
            return ['duration' => 0, 'critical' => [], 'slack' => [], 'tasks' => [], 'order' => []];
        }

        // Peta setiap tugas ke leluhur tingkat atasnya; node CPM = tugas tingkat atas
        // agar durasi parent (rollup) tidak dihitung ganda bersama sub-tugasnya.
        $root = [];

        foreach ($tasks as $id => $task) {
            $node = $task;
            $guard = 0;

            while ($node->parent_id !== null
                && $tasks->has((int) $node->parent_id)
                && $guard++ < 50
            ) {
                $node = $tasks->get((int) $node->parent_id);
            }

            $root[$id] = (int) $node->getKey();
        }

        $nodes = array_values(array_unique($root));
        sort($nodes);

        $durations = [];
        foreach ($nodes as $nodeId) {
            $durations[$nodeId] = max(1, (int) $tasks->get($nodeId)->duration);
        }

        // Kumpulkan dependensi antar node, dipetakan ke leluhur tingkat atas.
        $outEdges = []; // from => list of ['to' => int, 'type' => string, 'delay' => int]
        $inEdges = [];  // to   => list of ['from' => int, 'type' => string, 'delay' => int]

        foreach ($tasks as $task) {
            foreach ($task->dependencies as $dependency) {
                $to = $root[(int) $task->getKey()] ?? null;
                $from = $root[(int) $dependency->predecessor_id] ?? null;

                if ($from === null || $to === null || !isset($durations[$from]) || $from === $to) {
                    continue;
                }

                $edge = [
                    'from' => $from,
                    'to' => $to,
                    'type' => $dependency->type,
                    'delay' => max(0, (int) $dependency->delay),
                ];

                $outEdges[$from][] = $edge;
                $inEdges[$to][] = $edge;
            }
        }

        $order = $this->topologicalOrderNodes($nodes, $outEdges, $tasks);

        // Forward pass: ES = batas bawah dari seluruh predecessor, EF = ES + durasi.
        $ES = [];
        $EF = [];

        foreach ($order as $id) {
            $es = 0;

            foreach ($inEdges[$id] ?? [] as $edge) {
                $from = $edge['from'];
                $delay = $edge['delay'];

                $candidate = match ($edge['type']) {
                    'SS' => $ES[$from] + $delay,
                    'FF' => $EF[$from] + $delay - $durations[$id],
                    'SF' => $ES[$from] + $delay + 1 - $durations[$id],
                    default => $EF[$from] + $delay, // FS
                };

                if ($candidate > $es) {
                    $es = $candidate;
                }
            }

            $ES[$id] = $es;
            $EF[$id] = $es + $durations[$id];
        }

        $finish = $EF === [] ? 0 : max($EF);

        // Backward pass: LF = batas atas dari seluruh successor, LS = LF - durasi.
        $LF = [];
        $LS = [];

        foreach (array_reverse($order) as $id) {
            $lf = null;

            foreach ($outEdges[$id] ?? [] as $edge) {
                $to = $edge['to'];
                $delay = $edge['delay'];

                $bound = match ($edge['type']) {
                    'SS' => $LS[$to] - $delay + $durations[$id],
                    'FF' => $LF[$to] - $delay,
                    'SF' => $LF[$to] - $delay - 1 + $durations[$id],
                    default => $LS[$to] - $delay, // FS
                };

                $lf = $lf === null ? $bound : min($lf, $bound);
            }

            $LF[$id] = $lf ?? $finish;
            $LS[$id] = $LF[$id] - $durations[$id];
        }

        $slack = [];
        $info = [];
        $critical = [];

        foreach ($order as $id) {
            $value = $LS[$id] - $ES[$id];
            $slack[$id] = $value;

            $info[$id] = [
                'name' => $tasks->get($id)->name,
                'es' => $ES[$id],
                'ef' => $EF[$id],
                'ls' => $LS[$id],
                'lf' => $LF[$id],
                'slack' => $value,
                'is_critical' => $value === 0,
            ];

            if ($value === 0) {
                $critical[] = $tasks->get($id)->name;
            }
        }

        return [
            'duration' => $finish,
            'critical' => $critical,
            'slack' => $slack,
            'tasks' => $info,
            'order' => $order,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Urutan topologis & deteksi siklus                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Urutan topologis seluruh tugas: edge predecessor -> tugas dan anak -> parent.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array<int, int>
     *
     * @throws ValidationException Bila terdeteksi dependensi melingkar.
     */
    private function topologicalOrder(Collection $tasks): array
    {
        $ids = $tasks->keys()->map(fn ($id) => (int) $id)->all();
        $edges = [];
        $indegree = array_fill_keys($ids, 0);

        foreach ($tasks as $task) {
            $to = (int) $task->getKey();

            foreach ($task->dependencies as $dependency) {
                $from = (int) $dependency->predecessor_id;

                if (!array_key_exists($from, $indegree) || isset($edges[$from][$to])) {
                    continue;
                }

                $edges[$from][$to] = true;
                $indegree[$to]++;
            }

            if ($task->parent_id !== null && array_key_exists((int) $task->parent_id, $indegree)) {
                $from = (int) $task->getKey();
                $toParent = (int) $task->parent_id;

                if (!isset($edges[$from][$toParent])) {
                    $edges[$from][$toParent] = true;
                    $indegree[$toParent]++;
                }
            }
        }

        return $this->kahn($ids, $edges, $indegree, $tasks);
    }

    /**
     * Urutan topologis node CPM.
     *
     * @param  array<int, int>  $nodes
     * @param  array<int, array<int, array{from: int, to: int, type: string, delay: int}>>  $outEdges
     * @param  Collection<int, Task>  $tasks
     * @return array<int, int>
     *
     * @throws ValidationException Bila terdeteksi dependensi melingkar.
     */
    private function topologicalOrderNodes(array $nodes, array $outEdges, Collection $tasks): array
    {
        $edges = [];
        $indegree = array_fill_keys($nodes, 0);

        foreach ($outEdges as $from => $list) {
            foreach ($list as $edge) {
                $to = $edge['to'];

                if (isset($edges[$from][$to])) {
                    continue;
                }

                $edges[$from][$to] = true;
                $indegree[$to]++;
            }
        }

        return $this->kahn($nodes, $edges, $indegree, $tasks);
    }

    /**
     * Algoritma Kahn; melempar ValidationException berbahasa Indonesia bila siklus.
     *
     * @param  array<int, int>  $ids
     * @param  array<int, array<int, bool>>  $edges
     * @param  array<int, int>  $indegree
     * @param  Collection<int, Task>  $tasks
     * @return array<int, int>
     *
     * @throws ValidationException
     */
    private function kahn(array $ids, array $edges, array $indegree, Collection $tasks): array
    {
        $queue = array_values(array_filter($ids, fn ($id) => ($indegree[$id] ?? 0) === 0));
        sort($queue);

        $order = [];

        while ($queue !== []) {
            $id = array_shift($queue);
            $order[] = $id;

            $nexts = array_keys($edges[$id] ?? []);
            sort($nexts);

            foreach ($nexts as $next) {
                $indegree[$next]--;

                if ($indegree[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        if (count($order) < count($ids)) {
            $remaining = array_fill_keys(array_values(array_diff($ids, $order)), true);
            $this->throwCycle($tasks, $remaining, $edges);
        }

        return $order;
    }

    /**
     * Cari satu siklus di antara node tersisa lalu tolak dengan pesan validasi
     * berbahasa Indonesia.
     *
     * @param  Collection<int, Task>  $tasks
     * @param  array<int, true>  $remaining
     * @param  array<int, array<int, bool>>  $edges
     *
     * @throws ValidationException
     */
    private function throwCycle(Collection $tasks, array $remaining, array $edges): never
    {
        $path = [];
        $inPath = [];

        $visit = function (int $node) use (&$visit, &$path, &$inPath, $edges, $remaining): ?array {
            if (isset($inPath[$node])) {
                $start = array_search($node, $path, true);

                return array_merge(array_slice($path, (int) $start), [$node]);
            }

            if (!isset($remaining[$node])) {
                return null;
            }

            $path[] = $node;
            $inPath[$node] = true;

            foreach (array_keys($edges[$node] ?? []) as $next) {
                $next = (int) $next;

                if (!isset($remaining[$next])) {
                    continue;
                }

                $cycle = $visit($next);

                if ($cycle !== null) {
                    return $cycle;
                }
            }

            array_pop($path);
            unset($inPath[$node]);

            return null;
        };

        $cycle = null;

        foreach (array_keys($remaining) as $node) {
            $cycle = $visit((int) $node);

            if ($cycle !== null) {
                break;
            }
        }

        $names = array_map(
            fn ($id) => $tasks->get($id)?->name ?? ('#' . $id),
            $cycle ?? array_keys($remaining)
        );

        throw ValidationException::withMessages([
            'schedule' => 'Dependensi melingkar terdeteksi antar tugas: '
                . implode(' → ', $names)
                . '. Perbaiki dependensi tugas sebelum menjadwalkan ulang.',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Utilitas                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<int, array{date: Carbon, repeats: string}>
     */
    private function holidayList(): array
    {
        if ($this->holidayCache !== null) {
            return $this->holidayCache;
        }

        $this->holidayCache = Holiday::query()->get()
            ->map(fn (Holiday $holiday) => [
                'date' => $holiday->date,
                'repeats' => $holiday->repeats ?? 'none',
            ])
            ->all();

        return $this->holidayCache;
    }

    private function toCarbon(CarbonInterface|string $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::parse($value->format('Y-m-d H:i:s'));
        }

        return Carbon::parse($value);
    }
}
