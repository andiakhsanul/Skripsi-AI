<?php

namespace App\Http\Requests\Student;

class UpdateStudentApplicationRequest extends StudentApplicationDataRequest
{
    /**
     * @return array<int, string>
     */
    protected function pdfRules(): array
    {
        return ['nullable', 'file', 'mimes:pdf', 'max:10240'];
    }
}
