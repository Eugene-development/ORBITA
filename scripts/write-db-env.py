"""Create a private, DB-only Docker env file for backup tooling; prints no secrets."""
from pathlib import Path
import sys
from env_config import read_env

if len(sys.argv) != 3:
    raise SystemExit("Usage: write-db-env.py source.env destination.env")
values = read_env(Path(sys.argv[1]))
keys = ["DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD"]
filtered = {key: values.get(key, "3306" if key == "DB_PORT" else "") for key in keys}
if any(not value or any(character in value for character in "\n\r\0") for value in filtered.values()):
    raise SystemExit("Backup requires nonempty, single-line database settings.")
destination = Path(sys.argv[2])
destination.write_text("\n".join(f"{key}={value}" for key, value in filtered.items()) + "\n", encoding="utf-8")
destination.chmod(0o600)
