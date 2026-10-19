"""Validate machine-readable catalog inspection, optionally returning only its hash."""
import argparse
import json
import re
import sys


def validate(report: object) -> str:
    if not isinstance(report, dict) or not isinstance(report.get("issues"), list) or not all(isinstance(issue, str) for issue in report["issues"]):
        raise ValueError("Invalid schema report structure; release stopped.")
    if report["issues"]:
        raise ValueError("Schema compatibility checks failed; inspect the read-only report before release.")
    fingerprint = report.get("fingerprint")
    if not isinstance(fingerprint, str) or not re.fullmatch(r"[a-f0-9]{64}", fingerprint):
        raise ValueError("Valid schema fingerprint missing; release stopped.")
    if report.get("driver") not in ["mysql", "sqlite"] or not isinstance(report.get("schema"), dict) or not report["schema"] or not isinstance(report.get("max_ids"), dict):
        raise ValueError("Schema metadata missing; release stopped.")
    return fingerprint


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--fingerprint", action="store_true")
    args = parser.parse_args()
    try:
        fingerprint = validate(json.load(sys.stdin))
    except (ValueError, json.JSONDecodeError) as error:
        raise SystemExit(str(error)) from None
    print(fingerprint if args.fingerprint else "Read-only catalogue compatibility check passed.")


if __name__ == "__main__":
    main()
