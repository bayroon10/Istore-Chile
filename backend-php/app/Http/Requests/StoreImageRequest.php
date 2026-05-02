<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // La autorización se maneja a nivel de middleware de ruta (auth y admin)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048', // 2MB máximo
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
            ],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Mensajes de error personalizados (Opcional, pero recomendado para UX)
     */
    public function messages(): array
    {
        return [
            'image.required' => 'El archivo de imagen es obligatorio.',
            'image.image' => 'El archivo debe ser una imagen válida.',
            'image.mimes' => 'Solo se permiten formatos JPG, JPEG, PNG y WEBP.',
            'image.max' => 'La imagen no debe superar los 2MB.',
            'image.dimensions' => 'La imagen debe tener entre 100x100 y 4000x4000 píxeles.',
        ];
    }
}
