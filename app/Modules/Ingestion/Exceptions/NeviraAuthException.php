<?php

namespace App\Modules\Ingestion\Exceptions;

use RuntimeException;

/**
 * Kegagalan auth NEVIRA (401/403). BUKAN error transient — tidak di-backoff/retry
 * seperti 429/5xx. Diserahkan ke token lifecycle (OPS-108) untuk re-login + alert.
 */
class NeviraAuthException extends RuntimeException {}
