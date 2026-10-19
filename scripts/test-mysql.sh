#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
python3 - <<'PY'
from pathlib import Path
import secrets
p=Path('runtime/mysql-test.env'); p.parent.mkdir(exist_ok=True)
if not p.exists():
    p.write_text('MYSQL_DATABASE=orbita_test_allocator\nMYSQL_USER=orbita_tests\nMYSQL_PASSWORD='+secrets.token_urlsafe(24)+'\nMYSQL_ROOT_PASSWORD='+secrets.token_urlsafe(24)+'\n')
    p.chmod(0o600)
settings=dict(line.split('=',1) for line in p.read_text().splitlines() if '=' in line)
if not settings.get('MYSQL_DATABASE','').startswith('orbita_test_'):
    raise SystemExit('Isolated MySQL tests require a database named orbita_test_*')
PY
trap 'docker compose -f tests/mysql/compose.yaml down >/dev/null' EXIT
docker compose -f tests/mysql/compose.yaml up -d --wait
python3 - <<'PY'
from pathlib import Path
import os,subprocess
env=os.environ.copy()
settings=dict(line.split('=',1) for line in Path('runtime/mysql-test.env').read_text().splitlines() if '=' in line)
env.update(ORBITA_MYSQL_TESTS='1',DB_CONNECTION='mysql',DB_URL='',DB_HOST='127.0.0.1',DB_PORT='4155',DB_DATABASE=settings['MYSQL_DATABASE'],DB_USERNAME=settings['MYSQL_USER'],DB_PASSWORD=settings['MYSQL_PASSWORD'],APP_ENV='testing',CACHE_STORE='array',SESSION_DRIVER='array',QUEUE_CONNECTION='sync',MAIL_MAILER='array')
raise SystemExit(subprocess.call(['php','vendor/bin/phpunit'],cwd='ms/orbita-api',env=env))
PY
