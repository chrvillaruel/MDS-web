<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

use InvalidArgumentException;

/**
 * Money in integer minor units (PHP centavos).
 *
 * Per PRD TR-6.5.6: amounts move through the system as integers end-to-end.
 * Decimals exist only at the persistence and presentation boundaries, never
 * inside arithmetic — floating-point drift is regulatory poison.
 */
final readonly class Money
{
    public function __construct(public int $centavos) {}

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromCentavos(int $centavos): self
    {
        return new self($centavos);
    }

    /**
     * Build from a decimal string (e.g. user input "1234.5600").
     * PHP_ROUND_HALF_UP at 2 decimals, then converted to centavos.
     */
    public static function fromDecimalString(string $amount): self
    {
        if (! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException("Invalid money amount: {$amount}");
        }

        $rounded = round((float) $amount, 2, PHP_ROUND_HALF_UP);

        return new self((int) round($rounded * 100, 0, PHP_ROUND_HALF_UP));
    }

    public function plus(self $other): self
    {
        return new self($this->centavos + $other->centavos);
    }

    public function minus(self $other): self
    {
        return new self($this->centavos - $other->centavos);
    }

    public function times(int $multiplier): self
    {
        return new self($this->centavos * $multiplier);
    }

    public function isNegative(): bool
    {
        return $this->centavos < 0;
    }

    public function isZero(): bool
    {
        return $this->centavos === 0;
    }

    public function equals(self $other): bool
    {
        return $this->centavos === $other->centavos;
    }

    /**
     * Two-decimal pesos as a string ("1234.56").
     */
    public function toDecimalString(): string
    {
        $sign = $this->centavos < 0 ? '-' : '';
        $abs = abs($this->centavos);
        $pesos = intdiv($abs, 100);
        $cents = $abs % 100;

        return sprintf('%s%d.%02d', $sign, $pesos, $cents);
    }

    public function toFloat(): float
    {
        return $this->centavos / 100;
    }
}
