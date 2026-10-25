<?php

namespace App\Payments;

use App\Models\PaymentMethod;
use App\Payments\Contracts\PaymentGateway;
use InvalidArgumentException;

class GatewayManager
{
    /** @return array<string, class-string<PaymentGateway>> */
    public function drivers(): array
    {
        return array_filter(
            config('payments.drivers', []),
            fn ($class) => is_string($class) && class_exists($class) && is_subclass_of($class, PaymentGateway::class)
        );
    }

    public function has(?string $driver): bool
    {
        return $driver !== null && array_key_exists($driver, $this->drivers());
    }

    /** @return array<string, string> driver key => label */
    public function options(): array
    {
        return array_map(fn ($class) => $class::label(), $this->drivers());
    }

    public function fieldsFor(?string $driver): array
    {
        return $this->has($driver) ? $this->drivers()[$driver]::configFields() : [];
    }

    public function make(PaymentMethod $method): PaymentGateway
    {
        if (! $method->isGateway() || ! $this->has($method->driver)) {
            throw new InvalidArgumentException("No gateway driver configured for payment method [{$method->code}].");
        }

        $class = $this->drivers()[$method->driver];

        return new $class($method);
    }
}
