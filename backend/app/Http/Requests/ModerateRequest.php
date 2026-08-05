<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ModerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = $this->input('action');

        $rules = [
            'action' => ['required', 'in:approved,rejected,request_changes,on_hold'],
        ];

        // Motif obligatoire pour refus / demande de modification
        if (in_array($action, ['rejected', 'request_changes'], true)) {
            $rules['reason'] = ['required', 'string', 'min:5', 'max:2000'];
        } else {
            $rules['reason'] = ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['reason.required' => 'Le motif est obligatoire pour refuser ou demander une modification.'];
    }
}