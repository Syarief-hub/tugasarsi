@php
    $priorityClass = match ($priority) {
        'tinggi' => 'bg-red-100 text-red-700',
        'rendah' => 'bg-slate-100 text-slate-600',
        default => 'bg-blue-100 text-blue-700',
    };
@endphp
<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $priorityClass }}">
    {{ ucfirst($priority) }}
</span>
