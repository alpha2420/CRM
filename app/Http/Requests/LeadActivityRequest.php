<?php

namespace App\Http\Requests;

use App\Support\LocalTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class LeadActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('lead'));
    }

    public function rules(): array
    {
        return [
            'status_id' => ['required', Rule::exists('lead_statuses', 'id')->where('organization_id', $this->user()->organization_id)],
            'note' => ['nullable', 'string', 'max:5000'],
            'next_follow_up_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array{status_id: int, note: ?string, next_follow_up_at: ?Carbon}
     */
    public function activityData(): array
    {
        return [
            'status_id' => (int) $this->validated('status_id'),
            'note' => $this->validated('note'),
            'next_follow_up_at' => LocalTime::toUtc($this->validated('next_follow_up_at')),
        ];
    }
}
