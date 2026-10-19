"""Create local secrets once. Never source/evaluate .env and never print secrets."""
from pathlib import Path
import base64
import re
import secrets
from env_config import read_env


def prepare(path: Path, template: Path) -> None:
    source = path if path.exists() else template
    values = read_env(source)
    if values.get('APP_ENV') != 'local' or values.get('DB_HOST') != 'mysql' or not values.get('DB_DATABASE', '').endswith('_local'):
        raise ValueError('Refusing setup: local profile must target mysql and a database ending _local.')
    lines = source.read_text().splitlines()
    defaults = {
        'APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
        'DB_PASSWORD': secrets.token_urlsafe(24),
        'MYSQL_ROOT_PASSWORD': secrets.token_urlsafe(32),
    }
    for key, value in defaults.items():
        if values.get(key):
            continue
        found = False
        for index, line in enumerate(lines):
            if re.match(r'(?:export\s+)?' + re.escape(key) + r'\s*=', line.strip()):
                lines[index] = key + '=' + value
                found = True
                break
        if not found:
            lines.append(key + '=' + value)
    path.write_text('\n'.join(lines) + '\n')
    path.chmod(0o600)


if __name__ == '__main__':
    root = Path(__file__).resolve().parents[1]
    try:
        prepare(root / '.env', root / '.env.example')
    except ValueError as error:
        raise SystemExit(str(error)) from None
    print('Local .env prepared; existing keys preserved.')
