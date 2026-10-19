import test from 'node:test';
import assert from 'node:assert/strict';
import { imageUrl } from '../src/lib/site.ts';
test('legacy image hashes preserve already encoded Unicode filenames', () => { assert.equal(imageUrl('%D0%BF%D0%B5%D1%81.jpg', 'https://media.example/images/'), 'https://media.example/images/%D0%BF%D0%B5%D1%81.jpg'); assert.equal(imageUrl('пес.jpg', 'https://media.example/images/'), 'https://media.example/images/%D0%BF%D0%B5%D1%81.jpg'); assert.equal(imageUrl(null), '/images/product-placeholder.svg'); });
