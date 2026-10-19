#!/usr/bin/env python3
"""Validate headerless MySQL CSV exports without printing or copying cell contents."""
import argparse
import csv
import json
from collections import Counter
from pathlib import Path

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('directory', type=Path)
args = parser.parse_args()
manifest = json.loads((Path(__file__).resolve().parents[1] / 'database/recovery-manifest.json').read_text())
unknown = sorted(p.stem for p in args.directory.glob('*.csv') if p.stem not in manifest)
if unknown:
    raise SystemExit(f'Unknown CSV tables: {unknown}')
count = 0
for table, spec in manifest.items():
    path = args.directory / f'{table}.csv'
    if not path.exists():
        print(f'{table}: missing CSV; structure from archive')
        continue
    # MySQL export uses backslash-escaped embedded quotes. It has no header row.
    # This tool only validates shape; it does not decode SQL NULL or import data.
    with path.open(encoding='utf-8-sig', newline='') as stream:
        widths = Counter(len(row) for row in csv.reader(stream, escapechar='\\', strict=True))
    if any(width != len(spec['columns']) for width in widths):
        raise SystemExit(f'{table}: unexpected field counts {dict(widths)}')
    rows = sum(widths.values())
    count += rows
    print(f'{table}: {rows} rows, {len(spec["columns"])} columns')
print(f'PASS: {count} rows; column counts match recovery manifest.')
