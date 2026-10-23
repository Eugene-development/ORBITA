"""Infrastructure regressions using temporary dummy settings; no containers are started."""
from pathlib import Path
import base64
import importlib.util
import json
import os
import re
import shutil
import subprocess
import tempfile
import unittest
from env_config import read_env

ROOT = Path(__file__).resolve().parents[1]


def module(name: str):
    specification = importlib.util.spec_from_file_location(name.replace("-", "_"), ROOT / "scripts" / (name + ".py"))
    imported = importlib.util.module_from_spec(specification)
    specification.loader.exec_module(imported)
    return imported


production = module("check-production")
infrastructure = module("check-infra")
schema_export = module("check-schema-export")
schema_report = module("validate-schema-report")
local_setup = module("setup-env")
migration_guard = module("check-migrations")


def fixture_env() -> dict[str, str]:
    values = read_env(ROOT / "prod/orbita-api/.env.example")
    values.update({
        "APP_KEY": "base64:" + base64.b64encode(b"f" * 32).decode(),
        "DB_HOST": "external-db.example.test", "DB_DATABASE": "fixture_database", "DB_USERNAME": "fixture_user",
        "DB_PASSWORD": "db-only-fixture-secret", "MAIL_PASSWORD": "mail-only-fixture-secret",
        "AWS_SECRET_ACCESS_KEY": "aws-only-fixture-secret", "YOOKASSA_SECRET_KEY": "payment-only-fixture-secret",
    })
    return values


def write_fixture(path: Path) -> None:
    path.write_text("\n".join(f"{key}={value}" for key, value in fixture_env().items()) + "\n", encoding="utf-8")
    path.chmod(0o600)


class InfrastructureTest(unittest.TestCase):
    def test_dotenv_is_data_and_handles_quotes_comments_and_equals(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "fixture.env"
            path.write_text("NAME='text # equals=ok'\nTOKEN='$(never-run) `never-run`'\nFLAG=false # comment\n")
            env = read_env(path)
            self.assertEqual(env["NAME"], "text # equals=ok")
            self.assertEqual(env["TOKEN"], "$(never-run) `never-run`")
            self.assertEqual(env["FLAG"], "false")
            path.write_text("FLAG=true\nFLAG=false\n")
            with self.assertRaises(ValueError):
                read_env(path)

    def test_local_setup_preserves_keys_and_refuses_production_file_before_mutating(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / ".env"
            template = Path(directory) / "template"
            template.write_text("APP_ENV='local'\nDB_HOST=mysql\nDB_DATABASE=fixture_local\nAPP_KEY=\nDB_PASSWORD=\nMYSQL_ROOT_PASSWORD=\n")
            local_setup.prepare(path, template)
            initial = read_env(path)
            self.assertTrue(initial["APP_KEY"].startswith("base64:"))
            local_setup.prepare(path, template)
            self.assertEqual(read_env(path), initial)
            production_text = "APP_ENV=production\nDB_HOST=external\nDB_DATABASE=production\nAPP_KEY=\n"
            path.write_text(production_text)
            with self.assertRaises(ValueError):
                local_setup.prepare(path, template)
            self.assertEqual(path.read_text(), production_text)

    def test_production_preflight_rejects_local_db_demo_debug_dummy_and_unverified_payments(self):
        production.validate(fixture_env())
        production.validate(fixture_env() | {"DB_HOST": "mysql"})
        for changed in [{"DB_HOST": "mysql", "DB_NETWORK": ""}, {"DB_HOST": "localhost"}, {"MYSQL_ATTR_SSL_CA": ""}, {"DB_URL": "sqlite:///tmp/other"}, {"APP_DEBUG": "true"}, {"ORBITA_DEMO": "true"}, {"PAYMENT_DRIVER": "dummy"}, {"PAYMENTS_ENABLED": "true"}, {"SESSION_SECURE_COOKIE": "false"}]:
            with self.subTest(changed=tuple(changed)):
                with self.assertRaises(ValueError):
                    production.validate(fixture_env() | changed)

    def test_report_requires_complete_verified_metadata(self):
        valid = {"fingerprint": "a" * 64, "issues": [], "driver": "mysql", "schema": {"product": {}}, "max_ids": {"product": "10"}}
        self.assertEqual(schema_report.validate(valid), "a" * 64)
        for invalid in [{"fingerprint": "a" * 64}, valid | {"issues": ["duplicate price"]}, valid | {"fingerprint": "bad"}]:
            with self.assertRaises(ValueError):
                schema_report.validate(invalid)

    def test_migration_guard_blocks_legacy_ddl_data_and_rename_targets(self):
        migration_guard.validate("Schema::create('orbita_orders', function($table) {});")
        for source in ["Schema::table('product', function($table) {});", "Schema::rename('orbita_temp', 'product');", "DB::statement('CREATE TABLE product(id int)');", "DB::table('price')->delete();", "Schema::dropIfExists($unknown);"]:
            with self.subTest(source=source):
                with self.assertRaises(ValueError):
                    migration_guard.validate(source)

    def test_schema_export_accepts_fk_on_delete_timestamp_on_update_and_literals(self):
        schema_export.validate("""
            -- DELETE words in documentation are not operations
            /*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
            /*!40101 SET NAMES utf8mb4 */;
            DROP TABLE IF EXISTS `child`;
            CREATE TABLE `child` (`id` bigint PRIMARY KEY, `updated_at` timestamp ON UPDATE CURRENT_TIMESTAMP,
              `parent_id` bigint, `text` text COMMENT 'DELETE; INSERT words in text',
              CONSTRAINT `child_parent` FOREIGN KEY (`parent_id`) REFERENCES `parent` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT;
        """)

    def test_schema_export_rejects_dml_executable_comments_cross_db_and_account_sql(self):
        base = "CREATE TABLE `product` (`id` bigint);"
        for added in ["INSERT INTO product VALUES(1);", "/*!50003 DELETE FROM product */;", "USE production;", "CREATE USER stranger;", "SET GLOBAL sql_mode='';", "DROP DATABASE production;", "CREATE TABLE production.evil (id int);", "CREATE TABLE copied AS SELECT * FROM product;", "CREATE TABLE orbita_orders (id int);", "CREATE TABLE remote (id int) ENGINE=FEDERATED;"]:
            with self.subTest(statement=added):
                with self.assertRaises(ValueError):
                    schema_export.validate(base + added)

    def test_backup_env_contains_only_decoded_db_fields_with_private_permissions(self):
        with tempfile.TemporaryDirectory() as directory:
            source, output = Path(directory) / "source.env", Path(directory) / "db.env"
            source.write_text("DB_HOST=external.test\nDB_PORT=3306\nDB_DATABASE=fixture\nDB_USERNAME=fixture\nDB_PASSWORD='p#a=ss'\nAWS_SECRET_ACCESS_KEY=unrelated\n")
            subprocess.run(["python3", str(ROOT / "scripts/write-db-env.py"), str(source), str(output)], check=True, capture_output=True)
            self.assertEqual(set(read_env(output)), {"DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD"})
            self.assertIn("DB_PASSWORD=p#a=ss", output.read_text())
            self.assertEqual(output.stat().st_mode & 0o777, 0o600)

    def test_php_entrypoint_initialization_does_not_pollute_machine_readable_command_stdout(self):
        source = (ROOT / "ms/orbita-api/entrypoint.sh").read_text()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            entrypoint = root / "entrypoint.sh"
            entrypoint.write_text(source.replace("/var/www", str(root)))
            fake_bin = root / "bin"
            fake_bin.mkdir()
            fake_php = fake_bin / "php"
            fake_php.write_text('#!/bin/sh\n[ "$1" = docker/runtime-check.php ] && exit 0\ncase "$2" in package:discover|lighthouse:cache) echo initialization-log ;; *) echo \'{"fingerprint":"fixture"}\' ;; esac\n')
            fake_php.chmod(0o755)
            environment = {"PATH": str(fake_bin) + ":" + os.environ["PATH"], "APP_ENV": "production"}
            result = subprocess.run(["sh", str(entrypoint), "php", "artisan", "orbita:catalog-inspect"], cwd=root, env=environment, capture_output=True, text=True)
            self.assertEqual(result.returncode, 0)
            self.assertEqual(json.loads(result.stdout), {"fingerprint": "fixture"})
            self.assertIn("initialization-log", result.stderr)
            self.assertTrue((root / "storage/framework/views").is_dir())

    @unittest.skipUnless(shutil.which("node") and (ROOT / "ms/orbita-front/build/index.js").exists() and (ROOT / "ms/orbita-admin/build/index.js").exists(), "Build both Node apps before testing production-only runtime")
    def test_built_apps_start_with_only_service_production_dependencies(self):
        for name, endpoint in [("orbita-front", "/health"), ("orbita-admin", "/admin/health")]:
            source_app = ROOT / "ms" / name
            lock = json.loads((source_app / "package-lock.json").read_text())
            with tempfile.TemporaryDirectory() as directory:
                runtime = Path(directory)
                shutil.copy2(source_app / "package.json", runtime / "package.json")
                shutil.copytree(source_app / "packages", runtime / "packages", ignore=shutil.ignore_patterns("node_modules"))
                shutil.copytree(source_app / "build", runtime / "build")
                for relative, package in sorted(lock["packages"].items(), key=lambda item: len(item[0])):
                    if "node_modules/" not in relative or package.get("dev"):
                        continue
                    source, destination = source_app / relative, runtime / relative
                    destination.parent.mkdir(parents=True, exist_ok=True)
                    if package.get("link"):
                        destination.symlink_to(os.readlink(source))
                    elif source.is_dir():
                        shutil.copytree(source, destination, dirs_exist_ok=True, ignore=shutil.ignore_patterns("node_modules"))
                    else:
                        self.fail("Installed production dependency missing: " + relative)
                origin = "http://orbita-runtime.example.test"
                environment = {"PATH": os.environ["PATH"], "NODE_ENV": "production", "APP_ENV": "local", "ORIGIN": origin, "ORBITA_ANALYTICS_ENABLED": "false", "ORBITA_DEMO": "false"}
                script = """
                    import { pathToFileURL } from 'node:url';
                    globalThis.fetch = () => { throw new Error('Runtime health must not contact external services'); };
                    const { server } = await import(pathToFileURL(process.argv[1]).href);
                    await server.init({ env: process.env });
                    const response = await server.respond(new Request(process.argv[2]), { getClientAddress: () => '127.0.0.1' });
                    if (response.status !== 200 || (await response.json()).status !== 'ok') process.exit(1);
                """
                result = subprocess.run(["node", "--input-type=module", "-e", script, str(runtime / "build/server/server.js"), origin + endpoint], cwd=runtime, env=environment, text=True, capture_output=True, timeout=10)
                self.assertEqual(result.returncode, 0, "Production-only Node runtime failed: " + name + result.stderr)

    @unittest.skipUnless(shutil.which("docker"), "Docker CLI absent")
    def test_production_bundles_resolve_independently_without_repository_or_secrets(self):
        for service in ['api', 'front', 'admin']:
            with self.subTest(service=service), tempfile.TemporaryDirectory() as directory:
                folder = Path(directory) / ('orbita-' + service)
                shutil.copytree(ROOT / 'prod' / folder.name, folder, ignore=shutil.ignore_patterns('.env', '.env.migrate'))
                write_fixture(folder / '.env')
                environment = os.environ.copy()
                environment.pop('ORBITA_PROD_ENV_FILE', None)
                result = subprocess.run(['docker', 'compose', '--project-directory', str(folder), '--profile', 'tools', 'config', '--format', 'json'], env=environment, capture_output=True, text=True)
                self.assertEqual(result.returncode, 0, 'Bundle must resolve after extraction outside repository')
                config = json.loads(result.stdout)
                infrastructure.validate(config)
                self.assertNotIn('mysql', config['services'])
                self.assertNotIn('gateway', config['services'])
                if service == 'api':
                    self.assertEqual(config['services']['app']['environment']['DB_PASSWORD'], 'db-only-fixture-secret')
                else:
                    settings = json.dumps(config['services']['app'].get('environment', {}))
                    for secret in ['db-only-fixture-secret', 'mail-only-fixture-secret', 'aws-only-fixture-secret', 'payment-only-fixture-secret']:
                        self.assertNotIn(secret, settings)
                for script in (folder / 'scripts').glob('*.sh'):
                    subprocess.run(['bash', '-n', str(script)], check=True)

    @unittest.skipUnless(shutil.which('docker'), 'Docker CLI absent')
    def test_compose_preflight_resolves_explicit_env_file(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'dummy.env'
            write_fixture(path)
            for service in ['api', 'front', 'admin']:
                infrastructure.validate(infrastructure.resolve(path, service))


if __name__ == "__main__":
    unittest.main()
