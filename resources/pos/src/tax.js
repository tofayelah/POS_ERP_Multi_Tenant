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
 * Calculate Net Price, Supplementary Duty, and VAT Amount from tax-inclusive Gross MRP.
 * Formula from Design §11.4 & Bangladesh VAT Act 2012:
 *   Value with SD = round(Gross / (1 + VAT_Rate), 2)
 *   VAT Amount = Gross - Value with SD
 *   Net Price = round(Value with SD / (1 + SD_Rate), 2)
 *   SD Amount = Value with SD - Net Price
 *
 * @param {string|number} grossStr
 * @param {string|number} vatRateStr
 * @param {string|number} sdRateStr
 * @returns {{ gross: string, net: string, sd: string, vat: string, grossPaisa: bigint, netPaisa: bigint, sdPaisa: bigint, vatPaisa: bigint }}
 */
export function calculateTaxInclusiveLine(grossStr, vatRateStr, sdRateStr = '0') {
    const grossPaisa = parsePaisa(grossStr);
    const { rateInt: vatRateInt, base100: vatBase100 } = parseRate(vatRateStr);
    const { rateInt: sdRateInt, base100: sdBase100 } = parseRate(sdRateStr);

    if (grossPaisa === 0n) {
        return {
            gross: '0.00',
            net: '0.00',
            sd: '0.00',
            vat: '0.00',
            grossPaisa: 0n,
            netPaisa: 0n,
            sdPaisa: 0n,
            vatPaisa: 0n,
        };
    }

    // Step 1: Extract VAT from Gross
    let valueWithSdPaisa = grossPaisa;
    let vatPaisa = 0n;
    if (vatRateInt > 0n) {
        const num = grossPaisa * vatBase100;
        const den = vatBase100 + vatRateInt;
        valueWithSdPaisa = divideAndRoundHalfUp(num, den);
        vatPaisa = grossPaisa - valueWithSdPaisa;
    }

    // Step 2: Extract Supplementary Duty from Value with SD
    let netPaisa = valueWithSdPaisa;
    let sdPaisa = 0n;
    if (sdRateInt > 0n) {
        const num = valueWithSdPaisa * sdBase100;
        const den = sdBase100 + sdRateInt;
        netPaisa = divideAndRoundHalfUp(num, den);
        sdPaisa = valueWithSdPaisa - netPaisa;
    }

    return {
        gross: formatPaisa(grossPaisa),
        net: formatPaisa(netPaisa),
        sd: formatPaisa(sdPaisa),
        vat: formatPaisa(vatPaisa),
        grossPaisa,
        netPaisa,
        sdPaisa,
        vatPaisa,
    };
}

/**
 * Calculate Supplementary Duty, VAT, and Gross Total for a tax-exclusive line.
 * Formula from Bangladesh VAT Act 2012:
 *   SD Amount = round(Net * SD_Rate, 2)
 *   Value for VAT = Net + SD Amount
 *   VAT Amount = round(Value for VAT * VAT_Rate, 2)
 *   Total Payable = Net + SD Amount + VAT Amount
 *
 * @param {string|number} netStr
 * @param {string|number} vatRateStr
 * @param {string|number} sdRateStr
 * @returns {{ net: string, sd: string, vat: string, total: string }}
 */
export function calculateTaxExclusiveLine(netStr, vatRateStr, sdRateStr = '0') {
    const netPaisa = parsePaisa(netStr);
    const { rateInt: vatRateInt, base100: vatBase100 } = parseRate(vatRateStr);
    const { rateInt: sdRateInt, base100: sdBase100 } = parseRate(sdRateStr);

    let sdPaisa = 0n;
    if (sdRateInt > 0n) {
        sdPaisa = divideAndRoundHalfUp(netPaisa * sdRateInt, sdBase100);
    }

    const valueForVatPaisa = netPaisa + sdPaisa;
    let vatPaisa = 0n;
    if (vatRateInt > 0n) {
        vatPaisa = divideAndRoundHalfUp(valueForVatPaisa * vatRateInt, vatBase100);
    }

    const totalPaisa = valueForVatPaisa + vatPaisa;

    return {
        net: formatPaisa(netPaisa),
        sd: formatPaisa(sdPaisa),
        vat: formatPaisa(vatPaisa),
        total: formatPaisa(totalPaisa),
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
 * @returns {{ rounded_cash: string, adjustment: string }}
 */
export function roundCashToInteger(amountStr) {
    const paisa = parsePaisa(amountStr);
    const quotient = paisa / 100n;
    const remainder = paisa % 100n;
    const roundedTaka = remainder >= 50n ? quotient + 1n : quotient;
    const roundedPaisa = roundedTaka * 100n;
    const diffPaisa = roundedPaisa - paisa;
    return {
        rounded_cash: `${roundedTaka}.00`,
        adjustment: formatPaisa(diffPaisa),
    };
}
