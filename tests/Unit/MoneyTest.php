<?php

namespace Tests\Unit;

use App\Core\ValueObjects\Money;
use Brick\Math\BigDecimal;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_can_instantiate_from_string_int_and_big_decimal(): void
    {
        $m1 = Money::of('125.50', 'BDT');
        $m2 = Money::of(125, 'BDT');
        $m3 = Money::of(BigDecimal::of('125.5000'), 'BDT');
        $zero = Money::zero('BDT');

        $this->assertSame('125.50', (string) $m1);
        $this->assertSame('125.00', (string) $m2);
        $this->assertSame('125.50', (string) $m3);
        $this->assertSame('0.00', (string) $zero);
        $this->assertSame('BDT', $m1->getCurrency());
    }

    public function test_rejects_php_float_to_prevent_precision_loss(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Floating point numbers are strictly forbidden in monetary calculations');

        // @phpstan-ignore-next-line
        Money::of(125.50);
    }

    public function test_addition_and_subtraction_are_exact(): void
    {
        $m1 = Money::of('100.25', 'BDT');
        $m2 = Money::of('50.50', 'BDT');

        $sum = $m1->add($m2);
        $diff = $m1->subtract($m2);

        $this->assertSame('150.75', (string) $sum);
        $this->assertSame('49.75', (string) $diff);

        // Immutability: original objects remain untouched
        $this->assertSame('100.25', (string) $m1);
        $this->assertSame('50.50', (string) $m2);
    }

    public function test_cannot_add_or_subtract_mismatched_currencies(): void
    {
        $bdt = Money::of('100.00', 'BDT');
        $usd = Money::of('100.00', 'USD');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency mismatch: BDT !== USD');

        $bdt->add($usd);
    }

    public function test_multiplication_and_division(): void
    {
        $m = Money::of('10.25', 'BDT');

        $multiplied = $m->multiply('3');
        $this->assertSame('30.75', (string) $multiplied);

        $multipliedDecimal = $m->multiply('2.5');
        $this->assertSame('25.625', $multipliedDecimal->getAmount());
        $this->assertSame('25.63', (string) $multipliedDecimal->round());

        $divided = Money::of('100.00', 'BDT')->divide('4');
        $this->assertSame('25.00', (string) $divided);
    }

    public function test_division_by_zero_throws_exception(): void
    {
        $m = Money::of('100.00', 'BDT');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Division by zero');

        $m->divide('0');
    }

    public function test_half_up_rounding_to_two_decimal_places(): void
    {
        // 125.005 rounds up to 125.01
        $m1 = Money::of('125.005', 'BDT')->round(2);
        $this->assertSame('125.01', (string) $m1);

        // 125.004 rounds down to 125.00
        $m2 = Money::of('125.004', 'BDT')->round(2);
        $this->assertSame('125.00', (string) $m2);

        // 125.015 rounds up to 125.02
        $m3 = Money::of('125.015', 'BDT')->round(2);
        $this->assertSame('125.02', (string) $m3);

        // Negative symmetric half-up: -125.005 rounds to -125.01
        $neg = Money::of('-125.005', 'BDT')->round(2);
        $this->assertSame('-125.01', (string) $neg);
    }

    public function test_comparison_methods(): void
    {
        $ten = Money::of('10.00', 'BDT');
        $twenty = Money::of('20.00', 'BDT');
        $neg = Money::of('-5.00', 'BDT');
        $zero = Money::zero('BDT');

        $this->assertTrue($ten->lessThan($twenty));
        $this->assertTrue($twenty->greaterThan($ten));
        $this->assertTrue($ten->lessThanOrEqual($ten));
        $this->assertTrue($ten->greaterThanOrEqual($ten));
        $this->assertTrue($ten->equals(Money::of('10.0000', 'BDT')));

        $this->assertTrue($zero->isZero());
        $this->assertFalse($ten->isZero());
        $this->assertTrue($ten->isPositive());
        $this->assertFalse($neg->isPositive());
        $this->assertTrue($neg->isNegative());
    }

    public function test_json_serialization_produces_string_never_float_acc_05(): void
    {
        $payload = [
            'price' => Money::of('12500.50', 'BDT'),
            'tax' => Money::of('1875.075', 'BDT')->round(2),
        ];

        $json = json_encode($payload);

        // Strict assertion: amounts must be quoted JSON strings, never floats
        $this->assertSame('{"price":"12500.50","tax":"1875.08"}', $json);

        $decoded = json_decode((string) $json, true);
        $this->assertIsString($decoded['price']);
        $this->assertIsString($decoded['tax']);
        $this->assertSame('12500.50', $decoded['price']);
        $this->assertSame('1875.08', $decoded['tax']);
    }
}
