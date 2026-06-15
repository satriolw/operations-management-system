<?php

namespace App\Modules\Ingestion\Exceptions;

use RuntimeException;

/**
 * Kegagalan request NEVIRA non-auth yang tak pulih (4xx selain 401/403, atau 5xx
 * setelah retry habis). Alarm jika bentuk response tak terduga (R4).
 */
class NeviraRequestException extends RuntimeException {}
