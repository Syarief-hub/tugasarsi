@csrf

<div class="grid gap-5">
    <div>
        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">
            Nama Proyek <span class="text-red-500">*</span>
        </label>
        <input type="text" id="name" name="name" value="{{ old('name', $project->name) }}" required
               maxlength="255" placeholder="Contoh: Sistem Informasi PT XYZ"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
    </div>

    <div>
        <label for="organization" class="mb-1 block text-sm font-medium text-slate-700">Organisasi</label>
        <input type="text" id="organization" name="organization" value="{{ old('organization', $project->organization) }}"
               maxlength="255" placeholder="Contoh: PT XYZ"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
    </div>

    <div>
        <label for="website" class="mb-1 block text-sm font-medium text-slate-700">Website</label>
        <input type="url" id="website" name="website" value="{{ old('website', $project->website) }}"
               maxlength="255" placeholder="https://www.example.com"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
        <p class="mt-1 text-xs text-slate-500">Sertakan http:// atau https://</p>
    </div>

    <div>
        <label for="description" class="mb-1 block text-sm font-medium text-slate-700">Deskripsi</label>
        <textarea id="description" name="description" rows="4"
                  placeholder="Jelaskan ruang lingkup proyek..."
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">{{ old('description', $project->description) }}</textarea>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit"
            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
        {{ $submitLabel ?? 'Simpan' }}
    </button>
    <a href="{{ $cancelUrl ?? route('projects.index') }}"
       class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
        Batal
    </a>
</div>
