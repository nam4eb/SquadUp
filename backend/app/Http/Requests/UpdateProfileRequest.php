<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge(['username' => mb_strtolower(trim((string) $this->username))]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => ['sometimes', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9_\.]+$/', Rule::unique('users')->ignore($this->user()->id)],
            'display_name' => ['sometimes', 'string', 'max:80'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'non_binary', 'prefer_not_to_say'])],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'location' => ['sometimes', 'nullable', 'string', 'max:160'],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'cover' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp', 'max:10240'],
        ];
    }
}
