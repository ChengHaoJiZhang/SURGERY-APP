"""Import the repository's demo SQL into an empty local MariaDB database."""
from pathlib import Path
import os
import subprocess
import sys

root = Path(__file__).resolve().parents[1]
runtime = root.parent / "SURGERY-APP-preview-runtime"
client = runtime / "mariadb" / "mariadb-11.8.9-winx64" / "bin" / "mariadb.exe"
dump = root / "database" / "imtm2025_grupd.sql"
if not client.is_file() or not dump.is_file():
    raise SystemExit("MariaDB client or repository SQL dump is missing. See preview_tools/README.md")

env = os.environ.copy()
env["MYSQL_PWD"] = "preview_local_only"  # Isolated localhost demo user only.
base = [str(client), "--host=127.0.0.1", "--port=3308", "--user=preview_user", "imtm2025_grupd"]
check = subprocess.run(
    base + ["--batch", "--skip-column-names", "--execute=SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"],
    env=env, capture_output=True, text=True, check=True
)
if int(check.stdout.strip()) != 0:
    raise SystemExit("Demo database already contains tables. Import only into a new empty database.")

sql = dump.read_bytes().replace(b"utf8mb4_0900_ai_ci", b"utf8mb4_unicode_ci")
result = subprocess.run(base, env=env, input=sql, capture_output=True)
if result.returncode:
    sys.stderr.buffer.write(result.stderr)
    raise SystemExit(result.returncode)
verify = subprocess.run(
    base + ["--batch", "--skip-column-names", "--execute=SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"],
    env=env, capture_output=True, text=True, check=True
)
print(f"Imported {int(verify.stdout.strip())} demo tables into imtm2025_grupd.")
