<?php

namespace App\Http\Requests;

use App\Models\Lead;
use App\Support\LocalTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TaskRequest extends FormRequest
{
    public function rules(): array
    {
        $org = $this->user()->organization_id;

        return [
            'title' => ['required', 'string', 'max:200'],
            'due' => ['nullable', 'in:today,tomorrow,next_week,custom'],
            'due_at' => ['nullable', 'required_if:due,custom', 'date'],
            'lead_id' => ['nullable', Rule::exists('leads', 'id')->where('organization_id', $org)],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $org)->where('is_active', true)],
        ];
    }

    public function messages(): array
    {
        return ['due_at.required_if' => 'Pick a date and time, or choose Today or Tomorrow.'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $leadId = $this->input('lead_id');

            if ($leadId && ! $this->user()->can('view', Lead::query()->find($leadId))) {
                $validator->errors()->add('lead_id', 'You can only add to-dos to your own leads.');
            }
        }];
    }

    /**
     * Who the to-do is for: agents set them for themselves, admins for anyone.
     */
    public function assigneeId(): int
    {
        $requested = $this->validated('user_id');

        return $requested && $this->user()->isAdmin() ? (int) $requested : $this->user()->id;
    }

    /**
     * Quick choices in local time: "today" is 6 pm (or an hour from now
     * once it is late), "tomorrow" and "next week" are 10 am.
     */
    public function dueAt(): ?Carbon
    {
        $now = LocalTime::now();
        $local = match ($this->validated('due')) {
            'today' => $now->hour < 17 ? $now->setTime(18, 0) : $now->addHour()->startOfHour(),
            'tomorrow' => $now->addDay()->setTime(10, 0),
            'next_week' => $now->next('Monday')->setTime(10, 0),
            'custom' => null,
            default => null,
        };

        return $this->validated('due') === 'custom'
            ? LocalTime::toUtc((string) $this->validated('due_at'))
            : ($local ? Carbon::instance($local)->utc() : null);
    }
}
