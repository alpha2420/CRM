<?php

namespace App\Consent;

use DomainException;

/** A message was refused because the lead asked not to get messages. */
final class OptedOutException extends DomainException {}
