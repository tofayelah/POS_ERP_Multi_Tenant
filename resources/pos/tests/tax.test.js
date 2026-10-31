import { describe, it, expect } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { calculateTaxInclusiveLine, calculateBasket } from '../src/tax.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const vectorsPath = path.resolve(__dirname, '../../../tests/golden/vat-vectors.json');
const vectors = JSON.parse(fs.readFileSync(vectorsPath, 'utf-8'));

describe('POS Tax Calculation Engine - Golden Vectors (§11.4)', () => {
    // Index line vectors by id for basket resolution
    const lineVectorsById = {};
    for (const v of vectors) {
        if (!v.lines) {
            lineVectorsById[v.id] = v;
        }
    }

    for (const vector of vectors) {
        if (vector.lines) {
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
        } else {
            it(`matches golden vector [${vector.id}] (gross: ${vector.gross}, rate: ${vector.vat_rate}%)`, () => {
                const result = calculateTaxInclusiveLine(vector.gross, vector.vat_rate);

                expect(result.gross).toBe(vector.gross);
                expect(result.net).toBe(vector.expect.net);
                expect(result.vat).toBe(vector.expect.vat);
            });
        }
    }
});
