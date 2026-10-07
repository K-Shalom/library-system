#!/bin/bash
# Generate circulation flow screenshots using headless Chrome.
# Uses a persistent --user-data-dir so cookies/session persist across requests.

set -euo pipefail

OUT_DIR="/home/K-Shalom/Documents/GRP3/library-system/tests/screenshots"
CHROME_DATA="$(mktemp -d /tmp/chrome-data-XXXXXX)"
trap 'rm -rf "$CHROME_DATA"' EXIT

BASE="http://127.0.0.1:8765/library-system"
CHROME="google-chrome --headless=new --disable-gpu --hide-scrollbars
        --window-size=1440,1200
        --no-sandbox
        --user-data-dir=$CHROME_DATA
        --virtual-time-budget=2500
        --screenshot"

mkdir -p "$OUT_DIR"

echo "--- 01: Login page ---"
$CHROME="$OUT_DIR/01-login-page.png" "$BASE/modules/auth/login.php"

echo "--- 02: Log in as admin ---"
# POST login via a helper URL that carries session cookie back. We'll submit via a
# self-submitting form (documented trick for headless: use --dump-dom plus cookies).
# Instead, make a cURL request to set up a cookie jar, then reuse it in Chrome.
COOKIE_JAR="$CHROME_DATA/cookies.txt"

# Step 1: GET login page with curl, grab the CSRF token and PHPSESSID cookie.
CSRF=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
           "$BASE/modules/auth/login.php" \
        | grep -oE 'name="csrf_token" value="[^"]+"' \
        | grep -oE 'value="[^"]+"' | cut -d'"' -f2)
echo "  CSRF token: ${CSRF:0:16}..."

# Step 2: POST the login with the session's CSRF token and same cookie jar.
curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
     -d "csrf_token=$CSRF&username=admin&password=admin123" \
     -o /dev/null -w "  HTTP status: %{http_code}\n" \
     "$BASE/modules/auth/login.php"

# Step 3: Convert Netscape cookie jar (curl format) into Chromium cookie file so
# headless Chrome reuses the PHP session. Python is the easiest way.
python3 - "$COOKIE_JAR" "$CHROME_DATA/Default/Cookies" <<'PY'
import http.cookiejar, sqlite3, os, sys, time
jar_path, cookie_db = sys.argv[1], sys.argv[2]
os.makedirs(os.path.dirname(cookie_db), exist_ok=True)
# Delete existing cookie DB if any.
if os.path.exists(cookie_db):
    os.remove(cookie_db)
# Chromium Cookies DB schema.
conn = sqlite3.connect(cookie_db)
conn.executescript("""
CREATE TABLE cookies(
  creation_utc INTEGER NOT NULL PRIMARY KEY,
  host_key TEXT NOT NULL,
  name TEXT NOT NULL,
  value TEXT NOT NULL,
  path TEXT NOT NULL,
  expires_utc INTEGER NOT NULL,
  is_secure INTEGER NOT NULL DEFAULT 0,
  is_httponly INTEGER NOT NULL DEFAULT 0,
  last_access_utc INTEGER NOT NULL DEFAULT 0,
  has_expires INTEGER NOT NULL DEFAULT 1,
  is_persistent INTEGER NOT NULL DEFAULT 1,
  priority INTEGER NOT NULL DEFAULT 1,
  samesite INTEGER NOT NULL DEFAULT -1,
  source_scheme INTEGER NOT NULL DEFAULT 1,
  encrypted_value BLOB DEFAULT NULL
);
CREATE UNIQUE INDEX cookies_unique_index ON cookies(host_key, name, path);
""")
jar = http.cookiejar.MozillaCookieJar(jar_path)
jar.load(ignore_discard=True, ignore_expires=True)
base_time = 11644473600  # epoch difference between Windows FILETIME (UTC) and unix.
now_utc = int((time.time() + base_time) * 1_000_000)
for c in jar:
    if c.name.strip().lower() == 'phpsessid':
        # PHPSESSID for http://127.0.0.1
        expires = 0  # session cookie (non-persistent uses expires=0 in Chromium schema)
        is_persistent = 0
    else:
        expires = int((float(c.expires or 0) + base_time) * 1_000_000) if c.expires else 0
        is_persistent = 1 if expires else 0
    conn.execute(
        "INSERT INTO cookies VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        (now_utc, c.domain, c.name, c.value, c.path, expires,
         1 if c.secure else 0,
         1 if 'httponly' in (getattr(c, '_rest', {}) or []) or str(c).lower().find('httponly')!=-1 else 0,
         now_utc,
         1 if expires else 0,
         is_persistent,
         1,  # priority
         -1, # samesite unspecified
         1,  # source_scheme = non-secure (http)
         None)
    )
    print(f"  Inserted cookie {c.domain} {c.name}={c.value[:8]}...")
conn.commit()
conn.close()
PY
# Chromium also needs a Local State file for "Default" profile to exist.
mkdir -p "$CHROME_DATA/Default"
echo '{"profile":{"info_cache":{"Default":{"name":"Default","user_name":"","avatar_icon":"chrome://theme/IDR_PROFILE_AVATAR_26"}},"last_used":"Default"}}' \
     > "$CHROME_DATA/Local State"

echo "--- 03: Dashboard (after login) ---"
$CHROME="$OUT_DIR/03-dashboard-after-login.png" "$BASE/index.php"

echo "--- 04: Borrow page (form with member/book dropdowns & due-date preview) ---"
$CHROME="$OUT_DIR/04-borrow-form.png" "$BASE/modules/circulation/borrow.php"

echo "--- 05: Borrow page AFTER success — simulate by pre-seeding a flash message via a tiny PHP script. ---"
# Create tmp script to set flash via session cookie, load borrow page, then delete tmp.
cat > /tmp/setflash.php <<'PHP'
<?php
define('BASE_URL','/library-system');
require_once __DIR__ . '/config/functions.php';
set_flash('success','Book issued successfully (loan #4). Due date: ' . (new DateTime('+'.LOAN_DAYS.' days'))->format('Y-m-d') . '.');
header('Location: '.BASE_URL.'/modules/circulation/borrow.php');
PHP
mv /tmp/setflash.php /home/K-Shalom/Documents/GRP3/library-system/_setflash_success.php
$CHROME="$OUT_DIR/05-borrow-success.png" "$BASE/_setflash_success.php"
rm -f /home/K-Shalom/Documents/GRP3/library-system/_setflash_success.php

echo "--- 06: Return page (active-loans table, overdue rows in RED with est. fine) ---"
$CHROME="$OUT_DIR/06-return-page-overdue-red.png" "$BASE/modules/circulation/return.php"

echo "--- 07: Return page AFTER successful late return (fine flash with link to fines) ---"
cat > /tmp/returnflash.php <<'PHP'
<?php
define('BASE_URL','/library-system');
require_once __DIR__ . '/config/functions.php';
$msg = 'Loan #2 returned successfully. Returned 16 days late. Fine: RWF 1,600. '
     . '<a href="' . BASE_URL . '/modules/fines/fines.php" class="alert-link">View fines →</a>';
$_SESSION['flash'][] = ['type'=>'warning','msg'=>$msg];
header('Location: '.BASE_URL.'/modules/circulation/return.php');
PHP
mv /tmp/returnflash.php /home/K-Shalom/Documents/GRP3/library-system/_returnflash_success.php
$CHROME="$OUT_DIR/07-return-late-fine.png" "$BASE/_returnflash_success.php"
rm -f /home/K-Shalom/Documents/GRP3/library-system/_returnflash_success.php

echo ""
echo "=== Screenshots generated in: $OUT_DIR ==="
ls -1 "$OUT_DIR"
