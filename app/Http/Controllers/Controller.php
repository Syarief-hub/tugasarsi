<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Membuat pesan kesalahan validasi berbahasa Indonesia.
     *
     * Kunci pesan disusun sebagai "field.rule", label field dipakai sebagai
     * pengganti atribut bawaan Laravel.
     *
     * @param  array<string, string>  $labels  field => label (Bahasa Indonesia)
     * @return array<string, string>
     */
    protected function indonesianMessages(array $labels): array
    {
        $templates = [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'numeric' => ':attribute harus berupa angka.',
            'email' => ':attribute harus berupa alamat email yang valid.',
            'url' => ':attribute harus berupa URL yang valid, contoh: https://example.com.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            'min' => ':attribute minimal :min.',
            'max' => ':attribute maksimal :max.',
            'regex' => ':attribute tidak sesuai format yang diminta.',
            'in' => ':attribute harus salah satu dari pilihan yang tersedia.',
            'exists' => ':attribute tidak ditemukan.',
        ];

        $messages = [];

        foreach ($labels as $field => $label) {
            foreach ($templates as $rule => $template) {
                $messages[$field . '.' . $rule] = str_replace(':attribute', $label, $template);
            }
        }

        return $messages;
    }
}
