<?php

namespace App\Modules\Delivery\Exceptions;

use RuntimeException;

/** Kegagalan transport pengiriman → memicu fallback ke hybrid (System Design §3.8). */
class DeliveryFailed extends RuntimeException {}
