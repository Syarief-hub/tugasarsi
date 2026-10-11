@csrf

<div class="grid gap-5">
    <div>
        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">
            Nama Tugas <span class="text-red-500">*</span>
        </label>
        <input type="text" id="name" name="name" value="{{ old('name', $task->name) }}" required
               maxlength="255" placeholder="Contoh: Analisis Kebutuhan"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="start_date" class="mb-1 block text-sm font-medium text-slate-700">
                Tanggal Mulai <span class="text-red-500">*</span>
            </label>
            <input type="date" id="start_date" name="start_date"
                   value="{{ old('start_date', $task->start_date?->format('Y-m-d')) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
        </div>
        <div>
            <label for="duration" class="mb-1 block text-sm font-medium text-slate-700">
                Durasi (hari) <span class="text-red-500">*</span>
            </label>
            <input type="number" id="duration" name="duration" min="1" max="3650" step="1"
                   value="{{ old('duration', $task->duration ?? 1) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-slate-500">Lamanya pengerjaan dalam hari kalender.</p>
        </div>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="priority" class="mb-1 block text-sm font-medium text-slate-700">
                Prioritas <span class="text-red-500">*</span>
            </label>
            <select id="priority" name="priority"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                @foreach (['rendah', 'normal', 'tinggi'] as $level)
                    <option value="{{ $level }}" @selected(old('priority', $task->priority ?? 'normal') === $level)>
                        {{ ucfirst($level) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="color" class="mb-1 block text-sm font-medium text-slate-700">Warna</label>
            <input type="color" id="color" name="color"
                   value="{{ old('color', $task->color ?? '#3b82f6') }}"
                   class="h-10 w-24 cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
        </div>
    </div>

    <div>
        <label for="parent_id" class="mb-1 block text-sm font-medium text-slate-700">Tugas Induk (untuk sub-tugas)</label>
        <select id="parent_id" name="parent_id"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <option value="">— Tanpa induk (tugas utama) —</option>
            @foreach ($parents as $parent)
                <option value="{{ $parent->id }}" @selected((string) old('parent_id', $task->parent_id) === (string) $parent->id)>
                    {{ $parent->name }} (mulai {{ $parent->start_date->format('d/m/Y') }})
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Kosongkan bila tugas ini adalah tugas utama.</p>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit"
            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
        {{ $submitLabel ?? 'Simpan' }}
    </button>
    <a href="{{ $cancelUrl ?? route('projects.tasks.index', $project) }}"
       class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
        Batal
    </a>
</div>
