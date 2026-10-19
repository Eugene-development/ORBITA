"""Audit portable production Compose without starting services or contacting databases."""
from pathlib import Path
import argparse
import json
import os
import subprocess
import tempfile
import shutil
from urllib.parse import urlsplit

ROOT = Path(__file__).resolve().parents[1]
NODE_ENV_KEYS = {
    'NODE_ENV', 'APP_ENV', 'ORIGIN', 'ORBITA_PUBLIC_URL', 'ORBITA_IMAGE_URL', 'ORBITA_DEMO',
    'ORBITA_API_ORIGIN_INTERNAL', 'ORBITA_API_URL_INTERNAL', 'ORBITA_ONLINE_PAYMENT_ENABLED',
    'ORBITA_ANALYTICS_ENABLED', 'ADDRESS_HEADER',
}


def resolve(path: Path, service: str = 'api') -> dict:
    if service not in ['api', 'front', 'admin']:
        raise ValueError('Unknown deployment bundle.')
    path = path.resolve()
    environment = os.environ.copy()
    environment['ORBITA_PROD_ENV_FILE'] = str(path)
    # Resolve only the supplied settings. A private .env.migrate next to the
    # real bundle must never be implicitly pulled into a dummy-settings audit.
    with tempfile.TemporaryDirectory(prefix='orbita-compose-audit-') as directory:
        compose = Path(directory) / 'docker-compose.yml'
        shutil.copy2(ROOT / f'prod/orbita-{service}/docker-compose.yml', compose)
        result = subprocess.run([
            'docker', 'compose', '--env-file', str(path), '-f', str(compose),
            '--profile', 'tools', 'config', '--format', 'json',
        ], env=environment, text=True, capture_output=True)
    if result.returncode:
        raise ValueError('Production Compose resolution failed; check paths and required settings.')
    return json.loads(result.stdout)


def validate(config: dict) -> None:
    name = config.get('name', '')
    if name not in ['orbita-api', 'orbita-front', 'orbita-admin']:
        raise ValueError('Unknown portable production profile.')
    bundle = name.removeprefix('orbita-')
    services = config.get('services', {})
    expected = {'app', 'nginx', 'redis', 'worker', 'scheduler', 'db'} if bundle == 'api' else {'app', 'nginx'}
    if set(services) != expected:
        raise ValueError('Unexpected production services.')
    port = {'front': 4151, 'admin': 4152, 'api': 7151}[bundle]
    for name, service in services.items():
        ports = service.get('ports', [])
        if name != 'nginx' and ports:
            raise ValueError(f'Only nginx may publish a host port: {name}.')
        if name == 'nginx' and (len(ports) != 1 or ports[0].get('host_ip') != '127.0.0.1' or ports[0].get('target') != 80 or int(ports[0]['published']) != port):
            raise ValueError('Nginx must bind the assigned loopback HTTP port.')
    runtime = ['app', 'worker', 'scheduler', 'db'] if bundle == 'api' else ['app']
    for name in runtime:
        service = services[name]
        expected_image = f'larux/orbita-{bundle if name != "db" else "db"}:latest'
        if service['image'] != expected_image:
            raise ValueError('Runtime image differs from the fixed build name.')
        if not service.get('read_only') or 'ALL' not in service.get('cap_drop', []) or 'no-new-privileges:true' not in service.get('security_opt', []):
            raise ValueError(f'Production hardening missing: {name}.')
        if bundle == 'api':
            for directory in ['storage/framework', 'storage/logs', 'bootstrap/cache']:
                mounts = [m for m in service.get('tmpfs', []) if m.startswith('/var/www/' + directory + ':')]
                if len(mounts) != 1 or not all(option in mounts[0].split(':', 1)[1].split(',') for option in ['uid=82', 'gid=82', 'mode=0770']):
                    raise ValueError('PHP writable tmpfs must belong to runtime uid82.')
        else:
            env = service.get('environment', {})
            if set(env) - NODE_ENV_KEYS or any(env.get(k) != v for k, v in [('NODE_ENV', 'production'), ('APP_ENV', 'production'), ('ORBITA_DEMO', 'false')]):
                raise ValueError('Invalid or overprivileged Node environment.')
            origin = env.get('ORBITA_API_ORIGIN_INTERNAL', '')
            if urlsplit(env.get('ORIGIN', '')).scheme != 'https' or urlsplit(origin).scheme != 'https':
                raise ValueError('Production frontend origins must use HTTPS.')
    if bundle == 'api':
        if not services['app'].get('healthcheck'):
            raise ValueError('API readiness check is missing.')
        if not config['networks']['database'].get('external'):
            raise ValueError('Production MySQL must remain external.')
        if services['db']['command'] != ['php', 'artisan', 'migrate', '--path=/migrations', '--realpath', '--force']:
            raise ValueError('Only canonical service migrations may run.')
        if services['worker']['command'] != ['php', 'artisan', 'orbita:outbox-work', '--sleep=3'] or services['scheduler']['command'] != ['php', 'artisan', 'schedule:work']:
            raise ValueError('Outbox/scheduler commands differ from the application contract.')


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('--env-file', type=Path, default=ROOT / 'prod/orbita-api/.env')
    parser.add_argument('--service', choices=['api', 'front', 'admin'], default='api')
    args = parser.parse_args()
    try:
        validate(resolve(args.env_file, args.service))
    except (ValueError, OSError) as error:
        raise SystemExit(str(error)) from None
    print('Portable production Compose: fixed images, secret isolation, loopback ports and runtime hardening verified.')


if __name__ == '__main__':
    main()
