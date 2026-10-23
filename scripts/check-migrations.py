"""Static service-table allowlist; integration tests independently verify legacy DDL."""
from pathlib import Path
import re
import json
import sys


def validate(source: str) -> None:
    # Raw SQL bypasses both the table allowlist and the framework's schema grammar.
    if re.search(r'\bDB::(?:unprepared|statement|affectingStatement|insert|update|delete)\s*\(', source):
        raise ValueError('Raw SQL in service migrations requires independent review.')
    if re.search(r'\b(?:CREATE|ALTER|DROP|TRUNCATE)\s+TABLE\b', source, re.I):
        raise ValueError('Raw DDL in service migrations requires independent review.')
    loop_tables = {}
    for tables, variable in re.findall(r'foreach\s*\(\s*\[([^\]]+)\]\s+as\s+\$(\w+)\s*\)', source, re.S):
        names = re.findall(r"['\"]([^'\"]+)['\"]", tables)
        if names and all(name.startswith('orbita_') for name in names):
            loop_tables[variable] = names
    pattern = r'Schema::(create|table|drop|dropIfExists|rename)\s*\(\s*(?:([\'\"])([^\'\"]+)\2|\$(\w+))'
    for match in re.finditer(pattern, source):
        method, _, table, variable = match.groups()
        if not re.match(r'\s*[,)]', source[match.end():]):
            raise ValueError('Computed migration table expressions require review.')
        if table is not None and not table.startswith('orbita_'):
            raise ValueError(f'Refused {method} on a non-service table.')
        if variable and variable not in loop_tables:
            raise ValueError('Dynamic migration table target requires independent review.')
        if method == 'rename':
            target = re.match(r'\s*,\s*([\'\"])([^\'\"]+)\1', source[match.end():])
            if not target or not target.group(2).startswith('orbita_'):
                raise ValueError('Migration rename target must remain in the orbita_ namespace.')
            if not re.match(r'\s*\)', source[match.end():][target.end():]):
                raise ValueError('Computed migration rename targets require review.')
    # Disallow an unparsed first table expression instead of silently accepting it.
    calls = re.findall(r'Schema::(?:create|table|drop|dropIfExists|rename)\s*\(', source)
    if len(calls) != len(list(re.finditer(pattern, source))):
        raise ValueError('Unrecognized migration table expression requires review.')
    for table in re.findall(r'DB::table\s*\(\s*[\'\"]([^\'\"]+)', source):
        if not table.startswith('orbita_'):
            raise ValueError('Service migrations cannot modify legacy records.')


if __name__ == '__main__':
    root = Path(__file__).resolve().parents[1]
    paths = sorted((root / 'ms/orbita-db/database/migrations').glob('*.php'))
    for path in paths:
        try:
            validate(path.read_text())
        except ValueError as error:
            raise SystemExit(f'{path.name}: {error}') from None
    manifest = root / 'ms/orbita-api/resources/required-migrations.json'
    expected = [path.stem for path in paths]
    if '--write-api-manifest' in sys.argv:
        manifest.parent.mkdir(parents=True, exist_ok=True)
        manifest.write_text(json.dumps(expected, indent=2) + '\n')
    if not manifest.is_file() or json.loads(manifest.read_text()) != expected:
        raise SystemExit('API migration readiness manifest is stale. Run python3 scripts/check-migrations.py --write-api-manifest.')
    print('All canonical migrations target orbita_ service tables; API readiness manifest matches.')
