<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kip' => ['required', 'integer', 'in:0,1'],
            'pkh' => ['required', 'integer', 'in:0,1'],
            'kks' => ['required', 'integer', 'in:0,1'],
            'dtks' => ['required', 'integer', 'in:0,1'],
            'sktm' => ['required', 'integer', 'in:0,1'],
            'penghasilan_ayah_rupiah' => ['required', 'integer', 'min:0'],
            'penghasilan_ibu_rupiah' => ['required', 'integer', 'min:0'],
            'jumlah_tanggungan_raw' => ['required', 'integer', 'min:0'],
            'anak_ke_raw' => ['required', 'integer', 'min:1'],
            'status_orangtua_text' => ['required', 'string', 'max:255'],
            'status_rumah_text' => ['required', 'string', 'max:255'],
            'daya_listrik_text' => ['required', 'string', 'max:255'],
            'submitted_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'schema_version' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kip' => 'KIP',
            'pkh' => 'PKH',
            'kks' => 'KKS',
            'dtks' => 'DTKS',
            'sktm' => 'SKTM',
            'penghasilan_ayah_rupiah' => 'penghasilan ayah',
            'penghasilan_ibu_rupiah' => 'penghasilan ibu',
            'jumlah_tanggungan_raw' => 'jumlah tanggungan',
            'anak_ke_raw' => 'anak ke',
            'status_orangtua_text' => 'status orang tua',
            'status_rumah_text' => 'status rumah',
            'daya_listrik_text' => 'daya listrik',
            'submitted_pdf' => 'dokumen PDF',
            'schema_version' => 'schema version',
        ];
    }
}
