<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayController extends Controller
{
    /**
     * Menampilkan daftar hari libur.
     */
    public function index(): View
    {
        return view('holidays.index', [
            'holidays' => Holiday::orderBy('date')->get(),
            'holiday' => new Holiday(),
        ]);
    }

    /**
     * Menambahkan hari libur baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $labels = [
            'name' => 'Nama hari libur',
            'date' => 'Tanggal',
        ];

        $data = $request->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'date' => ['required', 'date', 'unique:holidays,date'],
            ],
            array_merge(
                $this->indonesianMessages($labels),
                [
                    'date.unique' => 'Tanggal tersebut sudah terdaftar sebagai hari libur.',
                ]
            )
        );

        Holiday::create($data);

        return redirect()
            ->route('holidays.index')
            ->with('success', 'Hari libur berhasil ditambahkan.');
    }

    /**
     * Menghapus hari libur.
     */
    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return redirect()
            ->route('holidays.index')
            ->with('success', 'Hari libur berhasil dihapus.');
    }
}
