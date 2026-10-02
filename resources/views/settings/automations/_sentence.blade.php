@php
    use App\Enums\AutomationTrigger;
    $name = fn ($list, $id, $fallback) => '<b>'.e($list->firstWhere('id', $id)?->name ?? $fallback).'</b>';

    $when = match ($automation->trigger) {
        AutomationTrigger::LeadCreated => 'a new lead arrives',
        AutomationTrigger::StatusChanged => 'a lead moves',
        AutomationTrigger::WhatsAppReceived => 'a lead sends a WhatsApp message',
        AutomationTrigger::LeadQuiet => 'a lead has been quiet for <b>'.$automation->trigger_after.' '.Str::plural('day', $automation->trigger_after).'</b>',
        AutomationTrigger::FollowUpOverdue => 'a follow-up is <b>'.$automation->trigger_after.' '.Str::plural('hour', $automation->trigger_after).'</b> overdue',
    };
    if ($words = $automation->condition('keywords')) $when .= ' mentioning <b>'.e(implode(', ', \App\Automations\Conditions::keywords($words))).'</b>';
    if ($id = $automation->condition('status_id')) $when .= ($automation->trigger === AutomationTrigger::StatusChanged ? ' to ' : ' in ').$name($statuses, $id, '?');
    elseif ($automation->trigger === AutomationTrigger::StatusChanged) $when .= ' to any status';
    if ($id = $automation->condition('source_id')) $when .= ' from '.$name($sources, $id, '?');

    $only = [];
    if ($priority = $automation->condition('priority')) $only[] = 'priority is <b>'.e(ucfirst($priority)).'</b>';
    if ($min = $automation->condition('min_value')) $only[] = 'value is at least <b>'.e(\App\Support\Money::full($min)).'</b>';
    if ($city = $automation->condition('city')) $only[] = 'city is <b>'.e($city).'</b>';
    if ($key = $automation->condition('field_key')) $only[] = '<b>'.e($fields->firstWhere('key', $key)?->label ?? $key).'</b> is <b>'.e($automation->condition('field_value')).'</b>';
    if ($only) $when .= ' and '.implode(' and ', $only);

    $parts = [];
    if ($id = $automation->action('assign_to')) $parts[] = 'assign to '.$name($users, $id, 'a removed user');
    if ($id = $automation->action('set_status_id')) $parts[] = 'set status '.$name($statuses, $id, '?');
    if ($priority = $automation->action('set_priority')) $parts[] = 'set priority <b>'.e(ucfirst($priority)).'</b>';
    if ($id = $automation->action('whatsapp_template_id')) $parts[] = 'send WhatsApp '.$name($templates, $id, 'template');
    if ($hours = $automation->action('follow_up_in_hours')) $parts[] = 'follow up in <b>'.(int) $hours.'h</b>';
    if ($id = $automation->action('start_sequence_id')) $parts[] = 'start sequence '.$name($sequences, $id, 'a removed sequence');
    if ($id = $automation->action('notify_user_id')) $parts[] = 'notify '.$name($users, $id, 'a removed user');
@endphp
When {!! $when !!} → {!! implode(', ', $parts) !!}
