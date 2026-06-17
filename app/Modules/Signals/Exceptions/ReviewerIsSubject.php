<?php

namespace App\Modules\Signals\Exceptions;

use RuntimeException;

/** Reviewer adalah subjek sinyal → harus ditinjau orang lain / eskalasi (OPS-606). */
class ReviewerIsSubject extends RuntimeException {}
