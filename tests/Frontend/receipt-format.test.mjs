import assert from 'node:assert/strict';
import test from 'node:test';
import { receiptPaperWidth } from '../../resources/js/receipt-format.js';

test('80 mm receipts retain their width for numeric and legacy string payloads', () => {
    assert.equal(receiptPaperWidth({ paper_width: 80 }), 80);
    assert.equal(receiptPaperWidth({ paper_width: '80' }), 80);
});

test('58 mm and missing or unsupported widths use the default paper size', () => {
    for (const value of [58, '58', null, undefined, '', 0, 100]) {
        assert.equal(receiptPaperWidth({ paper_width: value }), 58);
    }
    assert.equal(receiptPaperWidth(), 58);
});
