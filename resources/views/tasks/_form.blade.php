@csrf

@php
    // Baris dependensi untuk dirender ulang: old input saat gagal validasi,
    // atau data tersimpan saat mengedit.
    $dependencyRows = old('dependencies')
        ?? ($dependencies->map(fn ($dependency) => [
            'predecessor_id' => $dependency->predecessor_id,
            'type' => $dependency->type,
            'delay' => $dependency->delay,
        ])->all());
@endphp

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
                Durasi (hari kerja) <span class="text-red-500">*</span>
            </label>
            <input type="number" id="duration" name="duration" min="1" max="3650" step="1"
                   value="{{ old('duration', $task->duration ?? 1) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-slate-500">Sabtu-Minggu dan hari libur proyek dilewati.</p>
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

    <div>
        <div class="mb-1 flex items-center justify-between">
            <label class="block text-sm font-medium text-slate-700">Dependensi</label>
            <button type="button" id="add-dependency-row"
                    class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                + Tambah baris
            </button>
        </div>
        <p class="mb-2 text-xs text-slate-500">
            Pilih tugas lain dalam proyek yang sama sebagai predecessor, tipe relasi, dan delay dalam hari kerja.
            Tugas tidak boleh bergantung pada dirinya sendiri atau membentuk siklus.
        </p>

        <div id="dependency-rows" class="space-y-3">
            @foreach ($dependencyRows as $index => $row)
                <div class="dependency-row rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <div class="grid gap-3 sm:grid-cols-[1fr_1fr_8rem_auto] sm:items-end">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Predecessor</label>
                            <select name="dependencies[{{ $index }}][predecessor_id]"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">— Pilih tugas —</option>
                                @foreach ($predecessorOptions as $option)
                                    <option value="{{ $option->id }}" @selected((string) old("dependencies.{$index}.predecessor_id", $row['predecessor_id'] ?? '') === (string) $option->id)>
                                        {{ $option->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Tipe</label>
                            <select name="dependencies[{{ $index }}][type]"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                @foreach (\App\Models\TaskDependency::TYPES as $value => $label)
                                    <option value="{{ $value }}" @selected(old("dependencies.{$index}.type", $row['type'] ?? 'FS') === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Delay (hari kerja)</label>
                            <input type="number" name="dependencies[{{ $index }}][delay]" min="0" max="3650" step="1"
                                   value="{{ old("dependencies.{$index}.delay", $row['delay'] ?? 0) }}"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        </div>
                        <button type="button" class="remove-dependency-row mb-0.5 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-red-600 shadow-sm transition hover:bg-red-50"
                                aria-label="Hapus baris dependensi">
                            Hapus
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <p id="dependency-empty" class="{{ $dependencyRows === [] ? '' : 'hidden' }} mt-3 rounded-lg border border-dashed border-slate-300 px-4 py-4 text-center text-sm text-slate-500">
            Belum ada dependensi. Tugas ini bisa langsung dimulai tanpa menunggu tugas lain.
        </p>
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

@once
    <script>
        (function () {
            const container = document.getElementById('dependency-rows');
            const emptyState = document.getElementById('dependency-empty');
            const addButton = document.getElementById('add-dependency-row');

            if (!container || !addButton) {
                return;
            }

            const typeOptions = @json(\App\Models\TaskDependency::TYPES);
            const predecessorOptions = @json(
                $predecessorOptions->map(fn ($option) => ['id' => $option->id, 'name' => $option->name])->values()
            );

            let nextIndex = container.querySelectorAll('.dependency-row').length;

            function updateEmptyState() {
                emptyState.classList.toggle('hidden', container.querySelector('.dependency-row') !== null);
            }

            function buildRow(index) {
                const row = document.createElement('div');
                row.className = 'dependency-row rounded-lg border border-slate-200 bg-slate-50 p-3';

                const grid = document.createElement('div');
                grid.className = 'grid gap-3 sm:grid-cols-[1fr_1fr_8rem_auto] sm:items-end';

                const predecessorCell = document.createElement('div');
                predecessorCell.innerHTML =
                    '<label class="mb-1 block text-xs font-medium text-slate-600">Predecessor</label>';
                const predecessorSelect = document.createElement('select');
                predecessorSelect.name = 'dependencies[' + index + '][predecessor_id]';
                predecessorSelect.className =
                    'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500';

                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = '— Pilih tugas —';
                predecessorSelect.appendChild(placeholder);

                predecessorOptions.forEach(function (task) {
                    const option = document.createElement('option');
                    option.value = task.id;
                    option.textContent = task.name;
                    predecessorSelect.appendChild(option);
                });

                predecessorCell.appendChild(predecessorSelect);

                const typeCell = document.createElement('div');
                typeCell.innerHTML =
                    '<label class="mb-1 block text-xs font-medium text-slate-600">Tipe</label>';
                const typeSelect = document.createElement('select');
                typeSelect.name = 'dependencies[' + index + '][type]';
                typeSelect.className = predecessorSelect.className;

                Object.keys(typeOptions).forEach(function (value) {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = typeOptions[value];
                    typeSelect.appendChild(option);
                });

                typeCell.appendChild(typeSelect);

                const delayCell = document.createElement('div');
                delayCell.innerHTML =
                    '<label class="mb-1 block text-xs font-medium text-slate-600">Delay (hari kerja)</label>';
                const delayInput = document.createElement('input');
                delayInput.type = 'number';
                delayInput.name = 'dependencies[' + index + '][delay]';
                delayInput.min = '0';
                delayInput.max = '3650';
                delayInput.step = '1';
                delayInput.value = '0';
                delayInput.className = predecessorSelect.className;
                delayCell.appendChild(delayInput);

                const removeCell = document.createElement('div');
                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className =
                    'remove-dependency-row mb-0.5 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-red-600 shadow-sm transition hover:bg-red-50';
                removeButton.setAttribute('aria-label', 'Hapus baris dependensi');
                removeButton.textContent = 'Hapus';
                removeCell.appendChild(removeButton);

                grid.appendChild(predecessorCell);
                grid.appendChild(typeCell);
                grid.appendChild(delayCell);
                grid.appendChild(removeCell);
                row.appendChild(grid);

                return row;
            }

            addButton.addEventListener('click', function () {
                container.appendChild(buildRow(nextIndex));
                nextIndex += 1;
                updateEmptyState();
            });

            container.addEventListener('click', function (event) {
                if (!event.target.classList.contains('remove-dependency-row')) {
                    return;
                }

                event.target.closest('.dependency-row').remove();
                updateEmptyState();
            });
        })();
    </script>
@endonce
