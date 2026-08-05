<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnnouncementContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Visiteur non connecté => champs obligatoires ; connecté => pré-remplis
        return [
            'name' => ['required_without:auth', 'string', 'max:100'],
            'phone' => ['required_without:auth', 'string', 'regex:/^[+0-9 ]{8,20}$/'],
            'email' => ['nullable', 'email', 'max:100'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'channel' => ['required', 'in:form,whatsapp'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Le numéro de téléphone est invalide.',
            'message.min' => 'Le message doit contenir au moins 10 caractères.',
        ];
    }
}