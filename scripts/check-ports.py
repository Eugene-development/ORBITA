from pathlib import Path
import socket
from env_config import read_env

root = Path(__file__).resolve().parents[1]
path = root / 'docker/local/.env'
values = read_env(path) if path.exists() else {}
for key, default in [('WEB_PORT', 4151), ('MYSQL_PORT', 4153), ('MAILPIT_PORT', 4154)]:
    try:
        port = int(values.get(key, default))
        if not 1 <= port <= 65535:
            raise ValueError()
    except ValueError:
        raise SystemExit(f'{key}: configure a valid port in docker/local/.env.') from None
    try:
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', port))
    except PermissionError:
        raise SystemExit('Port checks need permission to bind host loopback; rerun setup in a local terminal.') from None
    except OSError:
        raise SystemExit(f'{key}: port {port} is occupied; choose another port in docker/local/.env.') from None
print('Configured local ports are available.')
