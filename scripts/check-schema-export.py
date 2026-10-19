"""Validate a schema-only MySQL export without interpreting SQL or modifying a DB."""
from pathlib import Path
import re
import sys


def sql_statements(source: str) -> list[str]:
    # MySQL version comments execute SQL; inspect their body instead of ignoring it.
    source = re.sub(r"/\*!\d*\s?(.*?)\*/", lambda match: match.group(1), source, flags=re.S)
    statements = []
    current = []
    index = 0
    while index < len(source):
        char = source[index]
        if source.startswith("/*", index):
            end = source.find("*/", index + 2)
            if end < 0:
                raise ValueError("Unterminated SQL comment.")
            current.append(" ")
            index = end + 2
            continue
        if char == "#" or (source.startswith("--", index) and (index + 2 == len(source) or source[index + 2].isspace())):
            end = source.find("\n", index)
            index = len(source) if end < 0 else end + 1
            current.append(" ")
            continue
        if char in ("'", '"', "`"):
            quote = char
            start = index
            index += 1
            while index < len(source):
                if source[index] == "\\" and quote != "`":
                    index += 2
                    continue
                if source[index] == quote:
                    if index + 1 < len(source) and source[index + 1] == quote:
                        index += 2
                        continue
                    index += 1
                    break
                index += 1
            else:
                raise ValueError("Unterminated SQL literal/identifier.")
            current.append(source[start:index] if quote == "`" else "'LITERAL'")
            continue
        if char == ";":
            statement = "".join(current).strip()
            if statement:
                statements.append(statement)
            current = []
        else:
            current.append(char)
        index += 1
    if "".join(current).strip():
        statements.append("".join(current).strip())
    return statements


def validate(source: str) -> None:
    statements = sql_statements(source)
    created = 0
    identifier = r"(?:`(?:[^`]|``)+`|[A-Za-z_][A-Za-z0-9_$]*)"
    forbidden = r"\b(?:SELECT|OUTFILE|DUMPFILE|DIRECTORY|LOAD_FILE|SONAME|TABLESPACE|FEDERATED|CONNECT|SPIDER)\b"
    for statement in statements:
        if re.search(forbidden, re.sub(r"`(?:[^`]|``)+`", "IDENTIFIER", statement), re.I):
            raise ValueError("External I/O, query-derived schema or plugin SQL is not accepted.")
        create = re.match(r"^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(" + identifier + r")\s*\(", statement, re.I)
        alter = re.match(r"^ALTER\s+TABLE\s+(" + identifier + r")\s+", statement, re.I)
        drop = re.fullmatch(r"DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?(" + identifier + r")", statement, re.I)
        if create or alter or drop:
            match = create or alter or drop
            table = match.group(1).strip("`")
            if table.lower().startswith("orbita_"):
                raise ValueError("Service tables must be created only through canonical migrations.")
            # Qualified table/reference names could escape the selected isolated database.
            if re.search(identifier + r"\s*\.\s*" + identifier, statement):
                raise ValueError("Remove database-qualified table names from the export.")
            if create:
                created += 1
            continue
        if re.match(r"^SET\s+", statement, re.I):
            # Permit mysqldump session bookkeeping, never globals/accounts/server settings.
            if re.search(r"\b(?:GLOBAL|PERSIST|PASSWORD|SQL_LOG_BIN|GTID_PURGED)\b|@@\s*GLOBAL", statement, re.I):
                raise ValueError("Global server/account settings are not accepted.")
            assignments = re.sub(r"^SET\s+(?:SESSION\s+)?", "", statement, flags=re.I).split(",")
            for assignment in assignments:
                if re.fullmatch(r"NAMES\s+(?:[A-Za-z0-9_]+|'LITERAL')(?:\s+COLLATE\s+[A-Za-z0-9_]+)?", assignment.strip(), re.I):
                    continue
                if not re.fullmatch(r"(?:@OLD_[A-Za-z0-9_]+|(?:@@SESSION\.)?(?:CHARACTER_SET_CLIENT|CHARACTER_SET_RESULTS|COLLATION_CONNECTION|TIME_ZONE|UNIQUE_CHECKS|FOREIGN_KEY_CHECKS|SQL_MODE|SQL_NOTES))\s*=\s*(?:@@(?:SESSION\.)?[A-Za-z_]+|@OLD_[A-Za-z0-9_]+|'LITERAL'|[01])", assignment.strip(), re.I):
                    raise ValueError("Unsupported SET statement; keep schema and session bookkeeping only.")
            continue
        raise ValueError("Expected schema-only table DDL; data, database-selection and account statements are refused.")
    if created == 0:
        raise ValueError("No CREATE TABLE statements found.")


if __name__ == "__main__":
    if len(sys.argv) != 2:
        raise SystemExit("Usage: check-schema-export.py /path/schema.sql")
    try:
        validate(Path(sys.argv[1]).read_text(encoding="utf-8"))
    except (ValueError, OSError) as error:
        raise SystemExit(str(error)) from None
    print("Schema-only input accepted for local import.")
