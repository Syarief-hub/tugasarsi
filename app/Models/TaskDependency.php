<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'predecessor_id', 'type', 'delay'])]
class TaskDependency extends Model
{
    /**
     * Tipe dependensi yang didukung beserta label berbahasa Indonesia.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'FS' => 'Finish-Start (FS)',
        'SS' => 'Start-Start (SS)',
        'FF' => 'Finish-Finish (FF)',
        'SF' => 'Start-Finish (SF)',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delay' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'predecessor_id');
    }
}
