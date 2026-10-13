<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an external dependency (DeepSeek, the speech service, Bachs) fails or returns unusable data.
 */
class ExternalServiceException extends RuntimeException {}
