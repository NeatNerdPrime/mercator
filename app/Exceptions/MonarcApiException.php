<?php

namespace App\Exceptions;

use Exception;

/**
 * Raised for any Monarc Front Office API failure (misconfiguration, auth
 * failure, unreachable host, unexpected response). The message is always
 * a translated, user-facing string — safe to flash back to the UI.
 */
class MonarcApiException extends Exception {}
