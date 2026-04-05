<?php

namespace App\Http\Requests\Student;

class StoreStudentApplicationRequest extends StudentApplicationDataRequest
{
    /**
     * @return array<int, string>
     */
    protected function pdfRules(): array
    {
        return ['required', 'file', 'mimes:pdf', 'max:10240'];
    }
}
