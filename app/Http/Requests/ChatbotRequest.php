<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatbotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Please enter a question about the NBA.',
            'message.max' => 'Question is too long (max 500 characters).',
        ];
    }
}
