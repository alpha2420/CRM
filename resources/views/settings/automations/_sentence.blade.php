@php
    $parts = [];
    if ($id = $automation->action('assign_to')) $parts[] = 'assign to <b>'.e($users->firstWhere('id', $id)?->name ?? 'a removed user').'</b>';
    if ($id = $automation->action('set_status_id')) $parts[] = 'set status <b>'.e($statuses->firstWhere('id', $id)?->name ?? '?').'</b>';
    if ($id = $automation->action('whatsapp_template_id')) $parts[] = 'send WhatsApp <b>'.e($templates->firstWhere('id', $id)?->name ?? 'template').'</b>';
    if ($hours = $automation->action('follow_up_in_hours')) $parts[] = 'follow up in <b>'.$hours.'h</b>';
    if ($id = $automation->action('notify_user_id')) $parts[] = 'notify <b>'.e($users->firstWhere('id', $id)?->name ?? 'a removed user').'</b>';
    $when = $automation->trigger === \App\Enums\AutomationTrigger::LeadCreated ? 'a new lead arrives' : 'a lead moves';
    if ($id = $automation->condition('status_id')) $when .= ($automation->trigger === \App\Enums\AutomationTrigger::LeadCreated ? ' in ' : ' to ').'<b>'.e($statuses->firstWhere('id', $id)?->name ?? '?').'</b>';
    elseif ($automation->trigger === \App\Enums\AutomationTrigger::StatusChanged) $when .= ' to any status';
    if ($id = $automation->condition('source_id')) $when .= ' from <b>'.e($sources->firstWhere('id', $id)?->name ?? '?').'</b>';
@endphp
When {!! $when !!} → {!! implode(', ', $parts) !!}
