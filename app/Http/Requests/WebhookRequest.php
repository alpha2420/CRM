<?php

namespace App\Http\Requests;

use App\Enums\WebhookEvent;
use App\Webhooks\UrlGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WebhookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:500', 'url:https'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::enum(WebhookEvent::class)],
        ];
    }

    public function messages(): array
    {
        return ['events.required' => 'Pick at least one event to send.'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $validator->errors()->has('url') && ($problem = app(UrlGuard::class)->problem((string) $this->input('url')))) {
                $validator->errors()->add('url', $problem);
            }
        }];
    }
}
