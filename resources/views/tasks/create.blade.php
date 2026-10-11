@extends('layouts.app')

@section('title', 'Tugas Baru - ' . $project->name)

@section('content')
    <div class="mx-auto max-w-2xl">
        <div class="mb-6">
            <p class="text-sm text-slate-500">
                <a href="{{ route('projects.index') }}" class="hover:text-indigo-600">Proyek</a>
                <span class="mx-1">/</span>
                <a href="{{ route('projects.show', $project) }}" class="hover:text-indigo-600">{{ $project->name }}</a>
                <span class="mx-1">/</span>
                <a href="{{ route('projects.tasks.index', $project) }}" class="hover:text-indigo-600">Tugas</a>
                <span class="mx-1">/</span> Baru
            </p>
            <h1 class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Tugas Baru</h1>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <form action="{{ route('projects.tasks.store', $project) }}" method="POST">
                @include('tasks._form', ['submitLabel' => 'Simpan Tugas'])
            </form>
        </div>
    </div>
@endsection
