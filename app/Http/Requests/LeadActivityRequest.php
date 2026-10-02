<?php

namespace App\Http\Requests;

use App\Calls\CallOutcome;
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
            'lost_reason_id' => ['nullable', Rule::exists('lost_reasons', 'id')->where('organization_id', $this->user()->organization_id)],
            // Set when the follow-up is logged straight after a call where they talked.
            'call_seconds' => ['nullable', 'integer', 'min:0', 'max:36000'],
        ];
    }

    /**
     * @return array{status_id: int, note: ?string, next_follow_up_at: ?Carbon, lost_reason_id: ?int, call_outcome?: string, call_seconds?: ?int}
     */
    public function activityData(): array
    {
        $data = [
            'status_id' => (int) $this->validated('status_id'),
            'note' => $this->validated('note'),
            'next_follow_up_at' => LocalTime::toUtc($this->validated('next_follow_up_at')),
            'lost_reason_id' => $this->validated('lost_reason_id') ? (int) $this->validated('lost_reason_id') : null,
        ];

        if ($this->has('call_seconds')) {
            $data += ['call_outcome' => CallOutcome::Connected->value, 'call_seconds' => $this->validated('call_seconds') !== null ? (int) $this->validated('call_seconds') : null];
        }

        return $data;
    }
}
