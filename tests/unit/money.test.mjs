import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseDecimal, formatDecimal, lineTotal, money } from '../../ms/orbita-front/packages/contracts/src/money.mjs';
test('financial totals preserve decimal prices without rounding up', () => { assert.equal(lineTotal('449.64','3'),'1348.92'); assert.equal(lineTotal('19000','0.125'),'2375.00'); assert.equal(lineTotal('0.01','0.500'),'0.01'); });
test('decimal transport accepts comma but rejects invalid and overprecision values', () => { assert.equal(parseDecimal('449,64'),44964n); for(const value of ['-1','NaN','Infinity','1e3','10.001']) assert.throws(()=>parseDecimal(value)); });
test('large values stay exact without binary float', () => { assert.equal(formatDecimal(parseDecimal('9007199254740993.99')),'9007199254740993.99'); assert.equal(money('449.64'),'449,64\u00a0₽'); });
