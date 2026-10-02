<?php

namespace App\Http\Requests;

use App\Autopilot\AutopilotSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutopilotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    public function rules(): array
    {
        return [
            'first_follow_up_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'speed_to_lead_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'next_follow_up_days' => ['required', 'integer', 'min:1', 'max:30'],
            'reengage_days' => ['required', 'integer', 'min:3', 'max:90'],
            'reengage_template_id' => ['nullable', Rule::exists('whatsapp_templates', 'id')->where('organization_id', $this->user()->organization_id)],
            'meeting_template_id' => ['nullable', Rule::exists('whatsapp_templates', 'id')->where('organization_id', $this->user()->organization_id)],
            'auto_close_days' => ['required', 'integer', 'min:14', 'max:365'],
            'away_text' => ['required', 'string', 'max:500'],
            'work_start' => ['required', 'integer', 'min:0', 'max:23'],
            'work_end' => ['required', 'integer', 'max:24', 'gt:work_start'],
        ];
    }

    public function messages(): array
    {
        return ['work_end.gt' => 'Working hours must end after they start.'];
    }

    /**
     * Every setting, typed like its default: unticked switches are false.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $validated = $this->validated();

        return collect(AutopilotSettings::DEFAULTS)->map(fn (mixed $default, string $key) => match (true) {
            is_bool($default) => $this->boolean($key),
            is_int($default) => (int) $validated[$key],
            str_ends_with($key, '_template_id') => isset($validated[$key]) ? (int) $validated[$key] : null,
            default => $validated[$key],
        })->all();
    }
}
