<?php

namespace App\Ai;

use RuntimeException;

/** A failure whose message is safe to show to the user. */
final class AssistantException extends RuntimeException {}
