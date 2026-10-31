/**
 * Bangladesh Retail Tax-Inclusive Calculation Engine (Design §11.4)
 * Uses exact BigInt integer arithmetic (paisa). Never floating point.
 */

/**
 * Divide BigInt numerator by denominator with standard half-up rounding.
 *
 * @param {bigint} numerator
 * @param {bigint} denominator
 * @returns {bigint}
 */
export function divideAndRoundHalfUp(numerator, denominator) {
    if (denominator === 0n) {
        throw new Error('Division by zero in tax calculation');
    }
    const quotient = numerator / denominator;
    const remainder = numerator % denominator;

    // For positive numbers, half-up rounds up when 2 * remainder >= denominator
    if (remainder * 2n >= denominator) {
        return quotient + 1n;
    }
    return quotient;
}

/**
 * Parse an amount string into integer paisa (1 Taka = 100 Paisa).
 *
 * @param {string|number} amountStr
 * @returns {bigint}
 */
export function parsePaisa(amountStr) {
    const s = String(amountStr).trim();
    if (!s) {
        return 0n;
    }
    const isNegative = s.startsWith('-');
    const cleanStr = isNegative ? s.slice(1) : s;

    const parts = cleanStr.split('.');
    const integerPart = BigInt(parts[0] || '0');
    let fractionalPartStr = (parts[1] || '').slice(0, 2);
    while (fractionalPartStr.length < 2) {
        fractionalPartStr += '0';
    }
    const fractionalPart = BigInt(fractionalPartStr);

    const paisa = integerPart * 100n + fractionalPart;
    return isNegative ? -paisa : paisa;
}

/**
 * Format integer paisa into a 2-decimal Taka string.
 *
 * @param {bigint} paisa
 * @returns {string}
 */
export function formatPaisa(paisa) {
    const sign = paisa < 0n ? '-' : '';
    const absPaisa = paisa < 0n ? -paisa : paisa;
    const taka = absPaisa / 100n;
    const remainingPaisa = absPaisa % 100n;
    return `${sign}${taka}.${remainingPaisa.toString().padStart(2, '0')}`;
}

/**
 * Parse a percentage rate string (e.g. "15", "5", "7.5", "0") into integer scale.
 *
 * @param {string|number} rateStr
 * @returns {{ rateInt: bigint, base100: bigint }}
 */
export function parseRate(rateStr) {
    const s = String(rateStr).trim();
    if (!s || s === '0') {
        return { rateInt: 0n, base100: 100n };
    }

    const parts = s.split('.');
    const integerPart = BigInt(parts[0] || '0');
    const decimals = parts[1] || '';
    const scale = decimals.length;

    let rateInt = integerPart;
    for (let i = 0; i < scale; i++) {
        rateInt = rateInt * 10n + BigInt(decimals[i]);
    }

    let base100 = 100n;
    for (let i = 0; i < scale; i++) {
        base100 *= 10n;
    }

    return { rateInt, base100 };
}

/**
 * Calculate Net Price and VAT Amount from tax-inclusive Gross MRP.
 * Formula from Design §11.4:
 *   Net Price = round(Gross MRP / (1 + V), 2)
 *   VAT Amount = Gross MRP - Net Price
 *
 * @param {string|number} grossStr
 * @param {string|number} vatRateStr
 * @returns {{ gross: string, net: string, vat: string, grossPaisa: bigint, netPaisa: bigint, vatPaisa: bigint }}
 */
export function calculateTaxInclusiveLine(grossStr, vatRateStr) {
    const grossPaisa = parsePaisa(grossStr);
    const { rateInt, base100 } = parseRate(vatRateStr);

    if (rateInt === 0n || grossPaisa === 0n) {
        return {
            gross: formatPaisa(grossPaisa),
            net: formatPaisa(grossPaisa),
            vat: '0.00',
            grossPaisa,
            netPaisa: grossPaisa,
            vatPaisa: 0n,
        };
    }

    // Net Paisa = roundHalfUp((GrossPaisa * Base100) / (Base100 + RateInt))
    const numerator = grossPaisa * base100;
    const denominator = base100 + rateInt;
    const netPaisa = divideAndRoundHalfUp(numerator, denominator);
    const vatPaisa = grossPaisa - netPaisa;

    return {
        gross: formatPaisa(grossPaisa),
        net: formatPaisa(netPaisa),
        vat: formatPaisa(vatPaisa),
        grossPaisa,
        netPaisa,
        vatPaisa,
    };
}

/**
 * Aggregate a collection of lines into basket totals.
 *
 * @param {Array<{ gross: string|number, vat_rate: string|number }>} lines
 * @returns {{ gross: string, net: string, vat: string }}
 */
export function calculateBasket(lines) {
    let totalGrossPaisa = 0n;
    let totalNetPaisa = 0n;
    let totalVatPaisa = 0n;

    for (const line of lines) {
        const lineResult = calculateTaxInclusiveLine(line.gross, line.vat_rate);
        totalGrossPaisa += lineResult.grossPaisa;
        totalNetPaisa += lineResult.netPaisa;
        totalVatPaisa += lineResult.vatPaisa;
    }

    return {
        gross: formatPaisa(totalGrossPaisa),
        net: formatPaisa(totalNetPaisa),
        vat: formatPaisa(totalVatPaisa),
    };
}

/**
 * Round cash drawer tender amount to nearest integer Taka ৳1 (§11.4).
 *
 * @param {string|number} amountStr
 * @returns {string}
 */
export function roundCashToInteger(amountStr) {
    const paisa = parsePaisa(amountStr);
    const quotient = paisa / 100n;
    const remainder = paisa % 100n;
    const roundedTaka = remainder >= 50n ? quotient + 1n : quotient;
    return `${roundedTaka}.00`;
}
