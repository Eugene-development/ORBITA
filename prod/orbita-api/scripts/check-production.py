"""Static production preflight. Does not connect to services or display secrets."""
from pathlib import Path
import argparse
import base64
import re
from urllib.parse import urlsplit
from env_config import read_env


def https_origin(value: str) -> bool:
    try:
        parsed = urlsplit(value)
        return parsed.scheme == "https" and bool(parsed.hostname) and not parsed.username and not parsed.password and parsed.path in ("", "/") and not parsed.query and not parsed.fragment
    except ValueError:
        return False


def validate(env: dict[str, str]) -> None:
    for key in ["APP_KEY", "DB_HOST", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD"]:
        if not env.get(key):
            raise ValueError(f"Missing required production setting: {key}.")
    for key, expected in [("APP_ENV", "production"), ("APP_DEBUG", "false"), ("ORBITA_DEMO", "false"), ("SESSION_SECURE_COOKIE", "true"), ("SESSION_DRIVER", "redis"), ("DB_CONNECTION", "mysql")]:
        if env.get(key) != expected:
            raise ValueError(f"Production requires {key}={expected}.")
    try:
        key = base64.b64decode(env["APP_KEY"].removeprefix("base64:"), validate=True)
    except ValueError:
        key = b""
    if not env["APP_KEY"].startswith("base64:") or len(key) != 32:
        raise ValueError("APP_KEY must be a valid 32-byte Laravel key.")
    if env["DB_HOST"].lower() in ["db", "localhost", "127.0.0.1", "::1"]:
        raise ValueError("Production DB must be the configured external database.")
    if env["DB_HOST"].lower() == "mysql" and not env.get("DB_NETWORK", "").strip():
        raise ValueError("The shared mysql alias requires an explicitly configured external DB_NETWORK.")
    if env.get("DB_URL"):
        raise ValueError("Use explicit DB_* settings; DB_URL overrides are not accepted in this production profile.")
    if not https_origin(env.get("APP_URL", "")) or not https_origin(env.get("ORIGIN", "")):
        raise ValueError("Production APP_URL and ORIGIN must be HTTPS origins.")
    if env["APP_URL"].rstrip("/") != env["ORIGIN"].rstrip("/"):
        raise ValueError("APP_URL and ORIGIN must describe the same public site.")
    for field in ["DB_PORT", "WEB_PORT"]:
        if field in env and (not env[field].isdigit() or not 1 <= int(env[field]) <= 65535):
            raise ValueError(f"Invalid port setting: {field}.")
    for field in ["APP_KEY", "DB_HOST", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD"]:
        if any(character in env[field] for character in "\r\n\0"):
            raise ValueError(f"Multiline/zero-byte setting is unsupported: {field}.")
    if env.get("MYSQL_ATTR_SSL_CA") != "/run/certs/mysql-ca.pem":
        raise ValueError("Production requires the mounted MySQL CA at /run/certs/mysql-ca.pem.")
    fingerprint = env.get("ORBITA_SCHEMA_FINGERPRINT", "")
    if fingerprint and not re.fullmatch(r"[a-f0-9]{64}", fingerprint):
        raise ValueError("Invalid approved catalog fingerprint.")
    if env.get("ORBITA_CATALOG_WRITES_ENABLED") == "true" and (env.get("ORBITA_WRITERS_COORDINATED") != "true" or not fingerprint):
        raise ValueError("Catalog writes require schema approval and coordinated writers.")
    if env.get("PAYMENT_DRIVER", "yookassa") != "yookassa":
        raise ValueError("Production payment driver must be yookassa; dummy gateways are local only.")
    if env.get("PAYMENTS_ENABLED") == "true":
        if not fingerprint or not env.get("YOOKASSA_SHOP_ID") or not env.get("YOOKASSA_SECRET_KEY") or env.get("YOOKASSA_RECEIPT_MODE") not in ["external", "yookassa"]:
            raise ValueError("Payments require approved catalog, shop credentials and verified receipt mode.")
        return_url = urlsplit(env.get("YOOKASSA_RETURN_URL", ""))
        if return_url.scheme != "https" or return_url.netloc != urlsplit(env["APP_URL"]).netloc:
            raise ValueError("Payment return URL must use the site's HTTPS origin.")
        if env["YOOKASSA_RECEIPT_MODE"] == "yookassa" and any(not env.get(key) for key in ["YOOKASSA_RECEIPT_VAT_CODE", "YOOKASSA_RECEIPT_PAYMENT_MODE", "YOOKASSA_RECEIPT_PAYMENT_SUBJECT"]):
            raise ValueError("API receipts require explicitly configured VAT, payment mode and subject.")
    if env.get("MEDIA_UPLOADS_ENABLED") == "true" and any(not env.get(key) for key in ["AWS_ACCESS_KEY_ID", "AWS_SECRET_ACCESS_KEY", "AWS_BUCKET"]):
        raise ValueError("Production media uploads require configured bucket credentials.")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--env-file", type=Path, default=Path(__file__).resolve().parents[1] / ".env")
    args = parser.parse_args()
    if not args.env_file.is_file():
        raise SystemExit("Create prod/orbita-api/.env from .env.example and configure it first.")
    try:
        validate(read_env(args.env_file))
    except ValueError as error:
        raise SystemExit(str(error)) from None
    print("Production configuration passes static checks. Secrets are not displayed.")


if __name__ == "__main__":
    main()
