@csrf

<div class="grid gap-5">
    <div>
        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">
            Nama <span class="text-red-500">*</span>
        </label>
        <input type="text" id="name" name="name" value="{{ old('name', $resource->name) }}" required
               maxlength="255" placeholder="Contoh: Budi Santoso"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="phone" class="mb-1 block text-sm font-medium text-slate-700">Telepon</label>
            <input type="text" id="phone" name="phone" value="{{ old('phone', $resource->phone) }}"
                   maxlength="30" placeholder="0812-0000-0000"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
        </div>
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $resource->email) }}"
                   maxlength="255" placeholder="nama@example.com"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
        </div>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="role" class="mb-1 block text-sm font-medium text-slate-700">
                Peran <span class="text-red-500">*</span>
            </label>
            <input type="text" id="role" name="role" value="{{ old('role', $resource->role) }}" required
                   maxlength="255" placeholder="Contoh: Programmer"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
        </div>
        <div>
            <label for="salary" class="mb-1 block text-sm font-medium text-slate-700">Gaji (Rp)</label>
            <input type="number" id="salary" name="salary" min="0" step="1000"
                   value="{{ old('salary', $resource->salary ?? 0) }}"
                   placeholder="12000000"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
        </div>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit"
            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
        {{ $submitLabel ?? 'Simpan' }}
    </button>
    <a href="{{ $cancelUrl ?? route('projects.resources.index', $project) }}"
       class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
        Batal
    </a>
</div>
