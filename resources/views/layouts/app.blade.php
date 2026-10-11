<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Manajemen Proyek')</title>

    {{-- Pakai aset Vite bila sudah dibangun, kalau belum pakai build browser Tailwind. --}}
    @if (file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">MP</span>
                <span class="text-base font-semibold text-slate-900 sm:text-lg">Manajemen Proyek</span>
            </a>

            <nav class="flex flex-wrap items-center gap-2 text-sm">
                <a href="{{ route('projects.index') }}"
                   class="rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs('projects.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    Proyek
                </a>
                <a href="{{ route('holidays.index') }}"
                   class="rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs('holidays.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    Hari Libur
                </a>
                <a href="{{ route('projects.create') }}"
                   class="rounded-lg bg-indigo-600 px-3 py-2 font-medium text-white shadow-sm transition hover:bg-indigo-700">
                    + Proyek Baru
                </a>
            </nav>
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6">
        @include('partials.flash')
        @include('partials.errors')
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-4 text-sm text-slate-500 sm:px-6">
            Aplikasi manajemen proyek &middot; Semua tanggal ditampilkan dalam format dd/mm/yyyy
        </div>
    </footer>
</body>
</html>
