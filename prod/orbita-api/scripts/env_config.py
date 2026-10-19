"""Read dotenv values as data. Never source files or execute substitutions."""
from pathlib import Path
import re


def read_env(path: Path) -> dict[str, str]:
    values: dict[str, str] = {}
    for number, raw in enumerate(path.read_text(encoding="utf-8").splitlines(), 1):
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        match = re.fullmatch(r"(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)", line)
        if not match:
            raise ValueError(f"Invalid dotenv assignment at line {number}.")
        key, value = match.groups()
        if value.startswith(("'", '"')):
            quote = value[0]
            end = 1
            while end < len(value):
                if value[end] == quote:
                    preceding = end - 1
                    while preceding >= 0 and value[preceding] == "\\":
                        preceding -= 1
                    if (end - preceding - 1) % 2 == 0:
                        break
                end += 1
            tail = value[end + 1:].strip()
            if end == len(value) or (tail and not tail.startswith("#")):
                raise ValueError(f"Invalid quoted dotenv value at line {number}.")
            value = value[1:end]
            if quote == '"':
                escapes = {"n": "\n", "r": "\r", "t": "\t", '"': '"', "\\": "\\", "$": "$"}
                value = re.sub(r"\\([nrt\"\\$])", lambda item: escapes[item.group(1)], value)
        else:
            value = re.split(r"\s+#", value, maxsplit=1)[0].strip()
        if key in values:
            raise ValueError(f"Duplicate dotenv setting: {key}.")
        values[key] = value
    return values
