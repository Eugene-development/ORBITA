"""Validate isolated migration credentials without exposing them or connecting to MySQL."""
from pathlib import Path
from env_config import read_env

runtime = read_env(Path('.env'))
path = Path('.env.migrate')
if not path.is_file():
    raise SystemExit('Create .env.migrate from .env.migrate.example with a separate migration account.')
settings = read_env(path)
if set(settings) != {'DB_USERNAME', 'DB_PASSWORD'} or any(not value or any(c in value for c in '\r\n\0') for value in settings.values()):
    raise SystemExit('.env.migrate must contain only nonempty DB_USERNAME and DB_PASSWORD.')
if settings['DB_USERNAME'] == runtime.get('DB_USERNAME'):
    raise SystemExit('Use a separate migration account; runtime must not have DDL privileges.')
print('Separate migration credentials configured; values are not displayed.')
