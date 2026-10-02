<?php

namespace App\Calls;

use App\Support\LocalTime;
use Illuminate\Support\Carbon;

/**
 * How a phone call went. Calls that did not get through plan the next try
 * by themselves, so nobody has to remember to call again.
 */
enum CallOutcome: string
{
    case Connected = 'connected';
    case NoAnswer = 'no_answer';
    case Busy = 'busy';
    case SwitchedOff = 'switched_off';
    case CallBack = 'call_back';
    case WrongNumber = 'wrong_number';

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Talked',
            self::NoAnswer => 'No answer',
            self::Busy => 'Busy',
            self::SwitchedOff => 'Switched off',
            self::CallBack => 'Asked to call back',
            self::WrongNumber => 'Wrong number',
        };
    }

    /** The person picked up (even if only to say "call me later"). */
    public function reached(): bool
    {
        return in_array($this, [self::Connected, self::CallBack], true);
    }

    /**
     * When to try again if nobody says otherwise. Null: the person
     * logging the call decides.
     */
    public function nextTry(): ?Carbon
    {
        return match ($this) {
            self::NoAnswer => now()->addHours(2),
            self::Busy => now()->addHour(),
            self::SwitchedOff, self::CallBack => Carbon::instance(LocalTime::now()->addDay()->setTime(11, 0))->utc(),
            default => null,
        };
    }
}
