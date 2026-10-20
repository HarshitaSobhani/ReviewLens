import { describe, expect, it } from 'vitest';
import { formatDate, formatNumber, pct, titleCase } from './format';

describe('format helpers', () => {
    it('computes safe percentages', () => {
        expect(pct(1, 4)).toBe(25);
        expect(pct(1, 0)).toBe(0);
    });
    it('handles missing values', () => {
        expect(formatDate(null)).toBe('—');
        expect(formatDate('not a date')).toBe('—');
        expect(formatNumber(undefined)).toBe('—');
        expect(formatNumber(1234)).toContain('234');
    });
    it('capitalises', () => {
        expect(titleCase('quality')).toBe('Quality');
    });
});
