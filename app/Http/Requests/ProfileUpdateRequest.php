<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'alpha_num',
                'min:3',
                'max:30',
                'lowercase',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'avatar' => ['required', 'string', Rule::in(User::AVATARS)],
            'bio' => ['nullable', 'string', 'max:160'],
            'country' => ['nullable', 'string', 'size:2'],
            'province' => ['nullable', 'string', 'max:50', 'required_if:country,ES'],
            'locale' => ['required', 'string', Rule::in(config('app.supported_locales'))],
        ];
    }
}
