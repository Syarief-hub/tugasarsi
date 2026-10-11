@extends('layouts.app')

@section('title', 'Proyek Baru - Manajemen Proyek')

@section('content')
    <div class="mx-auto max-w-2xl">
        <div class="mb-6">
            <p class="text-sm text-slate-500">
                <a href="{{ route('projects.index') }}" class="hover:text-indigo-600">Proyek</a>
                <span class="mx-1">/</span> Proyek Baru
            </p>
            <h1 class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Proyek Baru</h1>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <form action="{{ route('projects.store') }}" method="POST">
                @include('projects._form', ['submitLabel' => 'Simpan Proyek'])
            </form>
        </div>
    </div>
@endsection
