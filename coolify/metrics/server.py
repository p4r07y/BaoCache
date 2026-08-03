import hmac, json, os, secrets, sqlite3, threading, time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

DATA_DIR = "/data"
LOG_FILE = f"{DATA_DIR}/fastcgi-cache.log"
DB_FILE = f"{DATA_DIR}/metrics.sqlite3"
TOKEN_FILE = os.environ.get("BAOCACHE_METRICS_TOKEN_FILE", "")

def token():
    try:
        with open(TOKEN_FILE, "r", encoding="utf-8") as handle:
            return handle.read(8192).strip()
    except OSError:
        value = secrets.token_urlsafe(48)
        try:
            descriptor = os.open(TOKEN_FILE, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o444)
            with os.fdopen(descriptor, "w", encoding="utf-8") as handle:
                handle.write(value)
            return value
        except FileExistsError:
            with open(TOKEN_FILE, "r", encoding="utf-8") as handle:
                return handle.read(8192).strip()

def database():
    connection = sqlite3.connect(DB_FILE, timeout=5)
    connection.execute("PRAGMA journal_mode=WAL")
    connection.execute("CREATE TABLE IF NOT EXISTS events (ts REAL NOT NULL, status TEXT NOT NULL, reason TEXT NOT NULL DEFAULT '')")
    columns = {row[1] for row in connection.execute("PRAGMA table_info(events)")}
    if "reason" not in columns:
        connection.execute("ALTER TABLE events ADD COLUMN reason TEXT NOT NULL DEFAULT ''")
    connection.execute("CREATE INDEX IF NOT EXISTS events_ts ON events(ts)")
    connection.execute("CREATE TABLE IF NOT EXISTS state (name TEXT PRIMARY KEY, value TEXT NOT NULL)")
    return connection

def ingest():
    connection = database()
    row = connection.execute("SELECT value FROM state WHERE name='offset'").fetchone()
    offset = int(row[0]) if row else 0
    try:
        size = os.path.getsize(LOG_FILE)
        if size < offset: offset = 0
        with open(LOG_FILE, "r", encoding="utf-8", errors="ignore") as handle:
            handle.seek(offset)
            rows = []
            for line in handle:
                parts = line.strip().split()
                if len(parts) >= 2 and parts[1] in {"HIT", "MISS", "BYPASS", "EXPIRED", "STALE", "UPDATING", "REVALIDATED"}:
                    reason = parts[2] if len(parts) >= 3 and parts[2] in {"eligible", "method", "query", "path", "cookie", "authorization"} else ""
                    rows.append((float(parts[0]), parts[1], reason if parts[1] == "BYPASS" else ""))
            offset = handle.tell()
        if rows: connection.executemany("INSERT INTO events(ts,status,reason) VALUES(?,?,?)", rows)
        connection.execute("INSERT INTO state(name,value) VALUES('offset',?) ON CONFLICT(name) DO UPDATE SET value=excluded.value", (str(offset),))
        connection.execute("DELETE FROM events WHERE ts < ?", (time.time() - 86400,))
        connection.commit()
        if offset >= 8 * 1024 * 1024:
            with open(LOG_FILE, "w", encoding="utf-8"):
                pass
            connection.execute("UPDATE state SET value='0' WHERE name='offset'")
            connection.commit()
    except OSError:
        pass
    return connection

class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path == "/healthz": return self.respond(200, {"status": "ok"})
        if self.path != "/v1/cache" or not hmac.compare_digest(self.headers.get("X-BaoCache-Metrics", ""), token()): return self.respond(403, {"error": "forbidden"})
        connection = database()
        counts = dict(connection.execute("SELECT status, COUNT(*) FROM events WHERE ts >= ? GROUP BY status", (time.time() - 86400,)).fetchall())
        bypass_reasons = dict(connection.execute("SELECT reason, COUNT(*) FROM events WHERE ts >= ? AND status='BYPASS' AND reason != '' GROUP BY reason", (time.time() - 86400,)).fetchall())
        total = sum(counts.values()); cacheable = counts.get("HIT", 0) + counts.get("MISS", 0)
        return self.respond(200, {"window_seconds": 86400, "total": total, "hit_ratio": round((counts.get("HIT", 0) / cacheable) * 100, 1) if cacheable else None, "statuses": counts, "bypass_reasons": bypass_reasons})
    def respond(self, code, payload):
        body = json.dumps(payload).encode(); self.send_response(code); self.send_header("Content-Type", "application/json"); self.send_header("Content-Length", str(len(body))); self.end_headers(); self.wfile.write(body)
    def log_message(self, *_): pass

def collect_forever():
    while True:
        connection = ingest(); connection.close(); time.sleep(5)

threading.Thread(target=collect_forever, daemon=True).start()
ThreadingHTTPServer(("0.0.0.0", 8080), Handler).serve_forever()
