<?php

namespace App\Models;

use App\Services\SchedulingService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'parent_id', 'name', 'start_date', 'duration', 'priority', 'color'])]
class Task extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'duration' => 'integer',
        ];
    }

    /**
     * Tanggal selesai dihitung dari tanggal mulai + durasi dalam HARI KERJA
     * (Sabtu-Minggu dan hari libur dilewati sesuai mode proyek).
     */
    protected function endDate(): Attribute
    {
        return Attribute::get(function () {
            if ($this->start_date === null) {
                return null;
            }

            $duration = max((int) $this->duration, 1);
            $project = $this->project;

            if ($project === null) {
                return $this->start_date->copy()->addDays($duration - 1);
            }

            return app(SchedulingService::class)
                ->calculateEndDate($project, $this->start_date, $duration);
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    /**
     * Dependensi di mana tugas ini menjadi tugas yang bergantung (dependent).
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class);
    }

    /**
     * Tugas-tugas yang harus diselesaikan lebih dahulu (predecessor).
     */
    public function predecessors(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'predecessor_id')
            ->withPivot(['type', 'delay'])
            ->withTimestamps();
    }

    /**
     * Tugas-tugas yang bergantung pada tugas ini (successor).
     */
    public function successors(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'predecessor_id', 'task_id')
            ->withPivot(['type', 'delay'])
            ->withTimestamps();
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class)
            ->withPivot('allocation')
            ->withTimestamps();
    }
}
