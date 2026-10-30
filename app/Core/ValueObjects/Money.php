<?php

namespace App\Core\ValueObjects;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\DivisionByZeroException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

class Money implements JsonSerializable, Stringable
{
    protected BigDecimal $amount;

    protected string $currency;

    protected function __construct(BigDecimal $amount, string $currency = 'BDT')
    {
        $this->amount = $amount;
        $this->currency = strtoupper(trim($currency));
    }

    /**
     * Create a Money value object. Floats are explicitly rejected.
     */
    public static function of(mixed $amount, string $currency = 'BDT'): self
    {
        if (is_float($amount)) {
            throw new InvalidArgumentException('Floating point numbers are strictly forbidden in monetary calculations (ACC-05). Use string or integer.');
        }

        if ($amount instanceof self) {
            return new self($amount->amount, $currency ?: $amount->currency);
        }

        if ($amount instanceof BigDecimal) {
            return new self($amount, $currency);
        }

        if (is_int($amount) || is_string($amount)) {
            return new self(BigDecimal::of((string) $amount), $currency);
        }

        throw new InvalidArgumentException('Invalid amount provided for Money: expected string, int, or BigDecimal.');
    }

    public static function zero(string $currency = 'BDT'): self
    {
        return new self(BigDecimal::zero(), $currency);
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getAmount(): string
    {
        return (string) $this->amount;
    }

    public function getBigDecimal(): BigDecimal
    {
        return $this->amount;
    }

    public function getFormattedAmount(int $scale = 2): string
    {
        return (string) $this->amount->toScale($scale, RoundingMode::HALF_UP);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount->plus($other->amount), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount->minus($other->amount), $this->currency);
    }

    public function multiply(mixed $multiplier): self
    {
        if (is_float($multiplier)) {
            throw new InvalidArgumentException('Floating point multipliers are strictly forbidden. Use string, integer or BigDecimal.');
        }

        $multiplierObj = $multiplier instanceof BigDecimal ? $multiplier : BigDecimal::of((string) $multiplier);

        return new self($this->amount->multipliedBy($multiplierObj), $this->currency);
    }

    public function divide(mixed $divisor, int $scale = 4): self
    {
        if (is_float($divisor)) {
            throw new InvalidArgumentException('Floating point divisors are strictly forbidden. Use string, integer or BigDecimal.');
        }

        try {
            $divisorObj = $divisor instanceof BigDecimal ? $divisor : BigDecimal::of((string) $divisor);
            if ($divisorObj->isZero()) {
                throw new InvalidArgumentException('Division by zero.');
            }

            return new self($this->amount->dividedBy($divisorObj, $scale, RoundingMode::HALF_UP), $this->currency);
        } catch (DivisionByZeroException $e) {
            throw new InvalidArgumentException('Division by zero.', 0, $e);
        }
    }

    public function round(int $scale = 2): self
    {
        return new self($this->amount->toScale($scale, RoundingMode::HALF_UP), $this->currency);
    }

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->amount->compareTo($other->amount);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount->compareTo($other->amount) === 0;
    }

    public function lessThan(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function lessThanOrEqual(self $other): bool
    {
        return $this->compareTo($other) <= 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    public function isZero(): bool
    {
        return $this->amount->isZero();
    }

    public function isPositive(): bool
    {
        return $this->amount->isPositive();
    }

    public function isNegative(): bool
    {
        return $this->amount->isNegative();
    }

    protected function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Currency mismatch: {$this->currency} !== {$other->currency}");
        }
    }

    public function jsonSerialize(): string
    {
        return $this->getFormattedAmount(2);
    }

    public function __toString(): string
    {
        return $this->getFormattedAmount(2);
    }
}
