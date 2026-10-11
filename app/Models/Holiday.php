<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'date', 'repeats'])]
class Holiday extends Model
{
    /**
     * Tanggal disimpan mentah dalam format Y-m-d (bukan Y-m-d H:i:s)
     * supaya aturan validasi "unique" dan urutan tanggal tetap akurat,
     * namun dibaca sebagai instance Carbon untuk keperluan tampilan.
     */
    protected function date(): Attribute
    {
        return Attribute::get(function ($value) {
            return $value === null ? null : Carbon::parse($value);
        });
    }
}
