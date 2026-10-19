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
            shutil.copytree(ROOT / directory, project / directory, ignore=shutil.ignore_patterns('.env', '.env.migrate', 'cert', '__pycache__', 'node_modules', 'playwright-report', 'test-results'))
        api = project / 'prod/orbita-api'
        settings = (api / '.env.example').read_text().replace('APP_KEY=\n', 'APP_KEY=base64:' + base64.b64encode(b'f' * 32).decode() + '\n')
        for key, value in [('DB_HOST', 'external-db.example.test'), ('DB_DATABASE', 'fixture_database'), ('DB_USERNAME', 'fixture_runtime'), ('DB_PASSWORD', 'runtime-fixture-secret')]:
            settings = settings.replace(key + '=\n', key + '=' + value + '\n')
        (api / '.env').write_text(settings)
        (api / '.env.migrate').write_text('DB_USERNAME=fixture_migrator\nDB_PASSWORD=migrator-fixture-secret\n')
        (api / 'cert').mkdir()
        (api / 'cert/mysql-ca.pem').write_text('test-only-not-a-real-certificate\n')
        for name in ['front', 'admin']:
            folder = project / f'prod/orbita-{name}'
            (folder / '.env').write_text((folder / '.env.example').read_text())
        env = {k: v for k, v in os.environ.items() if not k.startswith(('COMPOSE_', 'ORBITA_', 'DB_', 'APP_'))}
        command = ['docker', 'compose', '--project-directory', str(api), '--env-file', str(api / '.env'), '--profile', 'tools', 'config', '--format', 'json']
        config = json.loads(subprocess.check_output(command, cwd=base, env=env, text=True))
        for name in ['app', 'worker', 'scheduler']:
            assert config['services'][name]['environment']['DB_USERNAME'] == 'fixture_runtime'
            assert config['services'][name]['environment']['DB_PASSWORD'] == 'runtime-fixture-secret'
        assert config['services']['db']['environment']['DB_PASSWORD'] == 'migrator-fixture-secret'
        for name in ['app', 'worker', 'scheduler', 'db']:
            assert config['services'][name]['environment']['MYSQL_ATTR_SSL_CA'] == '/run/certs/mysql-ca.pem'
            assert any(v['target'] == '/run/certs/mysql-ca.pem' and v['read_only'] for v in config['services'][name]['volumes'])
        # Changing the public domain must also change default SSR destinations.
        for service in ['front', 'admin']:
            folder = project / f'prod/orbita-{service}'
            (folder / '.env').write_text('ORIGIN=https://other.example.test\nAPP_URL=https://other.example.test\n')
            changed = json.loads(subprocess.check_output(['docker', 'compose', '--project-directory', str(folder), '--env-file', str(folder / '.env'), 'config', '--format', 'json'], cwd=base, env=env, text=True))
            assert changed['services']['app']['environment']['ORBITA_API_ORIGIN_INTERNAL'] == 'https://other.example.test'
        print('PASS independent runtime/migration credentials; CA mounts; SSR follows configured domain')

        binary = base / 'bin'
        binary.mkdir()
        log, state = base / 'commands.jsonl', base / 'applied'
        stub = binary / 'docker'
        stub.write_text('''#!/usr/bin/env python3
import json,os,sys
from pathlib import Path
args=sys.argv[1:]
with open(os.environ['COMMAND_LOG'],'a') as f: f.write(json.dumps({'args':args,'cwd':str(Path.cwd()),'profiles':os.environ.get('COMPOSE_PROFILES'),'env_file':os.environ.get('ORBITA_PROD_ENV_FILE')})+'\\n')
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
            return [json.loads(line) for line in log.read_text().splitlines()]

        calls = run('deploy/db.sh')
        assert [c['args'][0] for c in calls] == ['build', 'build', 'push']
        assert calls[-1]['args'] == ['push', 'larux/orbita-db:latest']
        assert len(run('deploy/db.sh', '--no-push')) == 2
        builds = run('scripts/build.sh', '--no-push')
        assert len(builds) == 4
        for call in builds:
            args = call['args']
            context = args[-1]
            assert context.startswith('ms/orbita-'), args
            assert args[args.index('-f') + 1] == context + '/Dockerfile.production', args
        assert len(run('deploy/db.sh', fail='build')) == 1
        print('PASS db build uses current API prerequisite; only selected db pushed; build failure stops chain')

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
        calls = run('prod/orbita-api/scripts/start.sh', fail='migrate:status')
        assert not any('up' in c['args'] and 'redis' not in c['args'] for c in calls)
        calls = run('prod/orbita-api/scripts/migrate.sh')
        assert sum('orbita:catalog-inspect' in c['args'] for c in calls) == 2
        run('prod/orbita-api/scripts/migrate.sh', fail='fingerprint-change')
        valid_migration_settings = (api / '.env.migrate').read_text()
        (api / '.env.migrate').write_text(valid_migration_settings + 'DB_HOST=unrelated-host\n')
        calls = run('prod/orbita-api/scripts/start.sh', fail='invalid-migration-settings')
        assert not any('pull' in c['args'] or 'up' in c['args'] or 'run' in c['args'] for c in calls)
        (api / '.env.migrate').write_text(valid_migration_settings)
        (api / '.env.migrate').unlink()
        calls = run('prod/orbita-api/scripts/migrate.sh', fail='missing-migration-settings')
        assert not any('pull' in c['args'] or 'up' in c['args'] or 'run' in c['args'] for c in calls)
        print('PASS fixed Compose scope; pull failure stops recreate; pending/fingerprint guards preserved; missing migrator credentials stop before Docker mutation')


if __name__ == '__main__':
    main()
