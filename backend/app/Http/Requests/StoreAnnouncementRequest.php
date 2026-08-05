<?php

namespace App\Http\Requests;

use App\Enums\BodyType;
use App\Enums\Condition;
use App\Enums\FuelType;
use App\Enums\Transmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_id' => ['required', 'exists:brands,id'],
            'model_id' => ['required', 'exists:vehicle_models,id'],
            'city_id' => ['required', 'exists:cities,id'],
            'commune_id' => ['nullable', 'exists:cities,id,parent_id,' . $this->input('city_id')],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'price' => ['required', 'integer', 'min:1000', 'max:5000000000'],
            'year' => ['nullable', 'integer', 'between:1950,2099'],
            'mileage' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'fuel_type' => ['required', Rule::in(FuelType::values())],
            'transmission' => ['required', Rule::in(Transmission::values())],
            'condition' => ['required', Rule::in(Condition::values())],
            'body_type' => ['nullable', Rule::in(BodyType::values())],
            'is_dedouane' => ['boolean'],
            'has_grise' => ['boolean'],
            'origin' => ['nullable', 'string', 'max:60'],
            'engine_cc' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'power_hp' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'doors' => ['nullable', 'integer', 'between:2,9'],
            'seats' => ['nullable', 'integer', 'between:1,12'],
            'number_of_owners' => ['nullable', 'integer', 'min:1', 'max:20'],
            'equipment' => ['nullable', 'array'],
            // Photos : tableau d'images (base64 / URLs en dev local)
            'photos' => ['nullable', 'array', 'max:20'],
            'photos.*' => ['image', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'photos.*.image' => 'Chaque fichier doit être une image valide.',
            'photos.*.max' => 'Chaque photo ne doit pas dépasser 10 Mo.',
            'price.min' => 'Le prix doit être au minimum de 1 000 FCFA.',
            'description.min' => 'La description doit contenir au moins 20 caractères.',
        ];
    }
}