<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership is enforced in the controller via ProductPolicy
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'brand' => ['nullable', 'string', 'max:80'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
