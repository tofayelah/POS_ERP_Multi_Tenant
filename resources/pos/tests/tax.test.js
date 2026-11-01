import { describe, it, expect } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
    calculateTaxInclusiveLine,
    calculateTaxExclusiveLine,
    calculateBasket,
    roundCashToInteger,
    parsePaisa,
    formatPaisa,
} from '../src/tax.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const vectorsPath = path.resolve(__dirname, '../../../tests/golden/vat-vectors.json');
const vectors = JSON.parse(fs.readFileSync(vectorsPath, 'utf-8'));

describe('POS Tax Calculation Engine - Golden Vectors (§11.4)', () => {
    // Index line vectors by id for basket resolution
    const lineVectorsById = {};
    for (const v of vectors) {
        if (!v.lines && !v.type) {
            lineVectorsById[v.id] = v;
        }
    }

    for (const vector of vectors) {
        if (vector.id === 'basket-total') {
            it(`matches golden vector [${vector.id}] (basket total)`, () => {
                const basketLines = vector.lines.map((lineId) => {
                    const line = lineVectorsById[lineId];
                    if (!line) {
                        throw new Error(`Referenced line [${lineId}] not found in golden vectors`);
                    }
                    return line;
                });

                const result = calculateBasket(basketLines);

                expect(result.gross).toBe(vector.expect.gross);
                expect(result.net).toBe(vector.expect.net);
                expect(result.vat).toBe(vector.expect.vat);
            });
        } else if (vector.type === 'invoice_discount') {
            it(`matches golden vector [${vector.id}] (invoice discount)`, () => {
                const discountPaisa = parsePaisa(vector.invoice_discount);
                const totalGrossPaisa = vector.lines.reduce(
                    (acc, l) => acc + parsePaisa(l.gross),
                    0n
                );

                let allocatedDiscountPaisaSum = 0n;
                const lineResults = [];

                for (let i = 0; i < vector.lines.length; i++) {
                    const line = vector.lines[i];
                    const lineGrossPaisa = parsePaisa(line.gross);

                    let lineDiscountPaisa;
                    if (i === vector.lines.length - 1) {
                        lineDiscountPaisa = discountPaisa - allocatedDiscountPaisaSum;
                    } else {
                        lineDiscountPaisa =
                            (discountPaisa * lineGrossPaisa) / totalGrossPaisa;
                        allocatedDiscountPaisaSum += lineDiscountPaisa;
                    }

                    const discountedGrossPaisa = lineGrossPaisa - lineDiscountPaisa;
                    const res = calculateTaxInclusiveLine(
                        formatPaisa(discountedGrossPaisa),
                        line.vat_rate
                    );
                    lineResults.push(res);
                }

                const totalGross = formatPaisa(totalGrossPaisa);
                const discountedGross = formatPaisa(totalGrossPaisa - discountPaisa);
                const netTotal = formatPaisa(
                    lineResults.reduce((acc, r) => acc + r.netPaisa, 0n)
                );
                const vatTotal = formatPaisa(
                    lineResults.reduce((acc, r) => acc + r.vatPaisa, 0n)
                );

                expect(totalGross).toBe(vector.expect.gross);
                expect(discountedGross).toBe(vector.expect.discounted_gross);
                expect(netTotal).toBe(vector.expect.net);
                expect(vatTotal).toBe(vector.expect.vat);
            });
        } else if (vector.type === 'cash_rounding') {
            it(`matches golden vector [${vector.id}] (cash rounding to ৳1)`, () => {
                for (const c of vector.cases) {
                    const result = roundCashToInteger(c.payable);
                    expect(result.rounded_cash).toBe(c.rounded_cash);
                    expect(result.adjustment).toBe(c.adjustment);
                }
            });
        } else if (vector.basis === 'tax_exclusive') {
            it(`matches golden vector [${vector.id}] (tax exclusive wholesale)`, () => {
                const result = calculateTaxExclusiveLine(
                    vector.net,
                    vector.vat_rate,
                    vector.sd_rate || '0'
                );

                expect(result.net).toBe(vector.expect.net);
                expect(result.sd).toBe(vector.expect.sd);
                expect(result.vat).toBe(vector.expect.vat);
                expect(result.total).toBe(vector.expect.total);
            });
        } else {
            it(`matches golden vector [${vector.id}] (gross: ${vector.gross}, vat: ${vector.vat_rate}%, sd: ${vector.sd_rate || '0'}%)`, () => {
                const result = calculateTaxInclusiveLine(
                    vector.gross,
                    vector.vat_rate,
                    vector.sd_rate || '0'
                );

                const expectedGross = vector.expect.gross !== undefined ? vector.expect.gross : vector.gross;
                expect(result.gross).toBe(expectedGross);
                expect(result.net).toBe(vector.expect.net);
                if (vector.expect.sd !== undefined) {
                    expect(result.sd).toBe(vector.expect.sd);
                }
                expect(result.vat).toBe(vector.expect.vat);
            });
        }
    }
});
