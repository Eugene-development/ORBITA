#!/usr/bin/env python3
"""Deployment workflows with inert Docker commands and synthetic configuration only."""
from pathlib import Path
import base64
import json
import os
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]


def main():
    with tempfile.TemporaryDirectory(prefix='orbita-deploy-check-') as tmp:
        base = Path(tmp).resolve()
        project = base / 'project with spaces'
        project.mkdir()
        for directory in ['scripts', 'deploy', 'prod', 'ms/orbita-db']:
            shutil.copytree(ROOT / directory, project / directory, ignore=shutil.ignore_patterns('.env', '.env.migrate', 'cert', '__pycache__', 'node_modules', 'playwright-report', 'test-results', 'vendor', 'storage', '.env.local'))
        manifest = project / 'ms/orbita-api/resources/required-migrations.json'
        manifest.parent.mkdir(parents=True)
        shutil.copy2(ROOT / 'ms/orbita-api/resources/required-migrations.json', manifest)
        api = project / 'prod/orbita-api'
        settings = (api / '.env.example').read_text().replace('APP_KEY=\n', 'APP_KEY=base64:' + base64.b64encode(b'f' * 32).decode() + '\n')
        for key, value in [('DB_HOST', 'external-db.example.test'), ('DB_DATABASE', 'fixture_database'), ('DB_USERNAME', 'fixture_runtime'), ('DB_PASSWORD', 'runtime-fixture-secret')]:
            settings = settings.replace(key + '=\n', key + '=' + value + '\n')
        (api / '.env').write_text(settings)
        (api / '.env.migrate').write_text('DB_USERNAME=fixture_migrator\nDB_PASSWORD=migrator-fixture-secret\n')
        for name in ['front', 'admin']:
            folder = project / f'prod/orbita-{name}'
            (folder / '.env').write_text((folder / '.env.example').read_text())
        env = {k: v for k, v in os.environ.items() if not k.startswith(('COMPOSE_', 'ORBITA_', 'DB_', 'APP_'))}
        command = ['docker', 'compose', '--project-directory', str(api), '--env-file', str(api / '.env'), '--profile', 'tools', 'config', '--format', 'json']
        config = json.loads(subprocess.check_output(command, cwd=base, env=env, text=True))
        for name in ['app', 'worker', 'scheduler']:
            assert config['services'][name]['environment']['DB_USERNAME'] == 'fixture_runtime'
            assert config['services'][name]['environment']['DB_PASSWORD'] == 'runtime-fixture-secret'
        assert 'db' not in config['services']
        assert 'migrator-fixture-secret' not in json.dumps(config)
        for name in ['app', 'worker', 'scheduler']:
            assert config['services'][name]['environment']['MYSQL_ATTR_SSL_CA'] == 'cert/mysql-ca.pem'
            assert not any('mysql-ca.pem' in v['target'] for v in config['services'][name].get('volumes', []))
        # Changing the public domain must also change default SSR destinations.
        for service in ['front', 'admin']:
            folder = project / f'prod/orbita-{service}'
            (folder / '.env').write_text('ORIGIN=https://other.example.test\nAPP_URL=https://other.example.test\n')
            changed = json.loads(subprocess.check_output(['docker', 'compose', '--project-directory', str(folder), '--env-file', str(folder / '.env'), 'config', '--format', 'json'], cwd=base, env=env, text=True))
            assert changed['services']['app']['environment']['ORBITA_API_ORIGIN_INTERNAL'] == 'https://other.example.test'
            assert changed['services']['app']['tmpfs'] == ['/tmp:rw,noexec,nosuid,nodev,mode=1777']
        print('PASS no migrator in production Compose; CA in image; SSR follows configured domain')

        binary = base / 'bin'
        binary.mkdir()
        log, state = base / 'commands.jsonl', base / 'applied'
        stub = binary / 'docker'
        stub.write_text('''#!/usr/bin/env python3
import json,os,sys
from pathlib import Path
args=sys.argv[1:]
with open(os.environ['COMMAND_LOG'],'a') as f: f.write(json.dumps({'args':args,'cwd':str(Path.cwd()),'profiles':os.environ.get('COMPOSE_PROFILES'),'env_file':os.environ.get('ORBITA_PROD_ENV_FILE')})+'\\n')
if args == ['context', 'show']:
    print('desktop-linux')
    sys.exit(0)
mode=os.environ.get('FAIL_COMMAND')
if mode in args: sys.exit(17)
if 'orbita:catalog-inspect' in args:
    fingerprint=('b' if mode=='fingerprint-change' and Path(os.environ['APPLIED']).exists() else 'a')*64
    print(json.dumps({'fingerprint':fingerprint,'issues':[],'driver':'mysql','schema':{'product':{}},'max_ids':{'product':'1'}}))
if args[-1:] == ['db'] and 'run' in args: Path(os.environ['APPLIED']).touch()
''')
        stub.chmod(0o755)
        env.update(PATH=str(binary) + os.pathsep + env['PATH'], COMMAND_LOG=str(log), APPLIED=str(state), COMPOSE_PROFILES='tools', COMPOSE_FILE='unrelated.yml', ORBITA_PROD_ENV_FILE='/unrelated/.env')

        def run(path, *args, fail=None):
            log.write_text('')
            state.unlink(missing_ok=True)
            active = dict(env)
            if fail: active['FAIL_COMMAND'] = fail
            result = subprocess.run(['bash', str(project / path), *args], cwd=base, env=active, capture_output=True, text=True)
            assert (result.returncode != 0) if fail else (result.returncode == 0), result.stderr
            return [call for line in log.read_text().splitlines() if (call := json.loads(line))['args'] != ['context', 'show']]

        front_calls = run('deploy/front.sh')
        assert front_calls[-1]['args'] == ['push', 'larux/orbita-front:latest']
        assert front_calls[0]['args'][-1] == 'ms/orbita-front'
        assert len(run('deploy/front.sh', '--no-push')) == 1
        assert len(run('deploy/front.sh', fail='build')) == 1

        assert run('deploy/db.sh', fail='local-only') == []
        builds = run('scripts/build.sh', '--no-push')
        assert len(builds) == 3
        for call in builds:
            args = call['args']
            assert args[args.index('--builder') + 1] == 'desktop-linux', args
            assert '--load' in args, args
            context = args[-1]
            assert context in ['ms/orbita-api', 'ms/orbita-front', 'ms/orbita-admin'], args
            assert args[args.index('-f') + 1] == context + '/Dockerfile.production', args
        print('PASS only API/front/admin images build; db Docker entrypoint is disabled')

        for service in ['front', 'admin', 'api']:
            for action in ['start', 'update', 'stop']:
                calls = run(f'prod/orbita-{service}/scripts/{action}.sh')
                folder = project / f'prod/orbita-{service}'
                assert all(c['cwd'] == str(folder) and not c['profiles'] for c in calls)
                for c in calls[1:]:
                    assert c['args'][1:3] == ['--project-name', f'orbita-{service}']
                    if service == 'api': assert c['env_file'] == str(api / '.env')
                assert not any(a in ['--rmi', '-v', '--volumes', 'build'] for c in calls for a in c['args'])
            calls = run(f'prod/orbita-{service}/scripts/update.sh', fail='pull')
            assert not any('up' in c['args'] for c in calls)
        calls = run('prod/orbita-api/scripts/start.sh', fail='orbita:migrations-check')
        assert not any('up' in c['args'] and 'redis' not in c['args'] for c in calls)
        assert run('prod/orbita-api/scripts/migrate.sh', fail='local-only') == []
        (api / '.env.migrate').write_text('DB_HOST=unrelated-host\n')
        run('prod/orbita-api/scripts/start.sh')  # obsolete migrator settings are never consumed
        runner = project / 'ms/orbita-db'
        (runner / 'vendor').mkdir()
        (runner / 'vendor/autoload.php').touch()
        (runner / '.env').write_text('APP_ENV=production\n')
        (runner / '.env.local').write_text('APP_ENV=local\n')
        php = binary / 'php'
        php.write_text(stub.read_text())
        php.chmod(0o755)
        calls = run('scripts/migrate-services.sh', 'production', '--pretend')
        assert len(calls) == 1 and calls[0]['cwd'] == str(runner)
        assert calls[0]['args'] == ['artisan', 'orbita:service-migrate', '--pretend']
        calls = run('scripts/migrate-services.sh', 'local', '--force')
        assert calls[0]['args'] == ['artisan', '--env=local', 'orbita:service-migrate', '--force']
        print('PASS local PHP migration runner; no Docker migration calls; server start checks readiness only')



if __name__ == '__main__':
    main()
