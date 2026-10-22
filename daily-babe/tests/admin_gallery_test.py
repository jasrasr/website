"""End-to-end authorization and photo removal check with disposable PHP storage."""

import base64
import http.cookiejar
import json
import re
import shutil
import socket
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path


SOURCE = Path(__file__).resolve().parents[1]
PNG = base64.b64decode(
    "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg=="
)


def browser():
    return urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())
    )


def request(opener, url, data=None, csrf=None, multipart=False):
    headers = {}
    if data is not None:
        if multipart:
            boundary = "baby-daily-test-boundary"
            fields = b"".join(
                b"--" + boundary.encode() + b"\r\n"
                + f'Content-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode()
                for key, value in data.items()
            )
            body = (
                fields
                + b"--" + boundary.encode() + b"\r\n"
                + b'Content-Disposition: form-data; name="photo"; filename="tiny.png"\r\n'
                + b"Content-Type: image/png\r\n\r\n"
                + PNG
                + b"\r\n--"
                + boundary.encode()
                + b"--\r\n"
            )
            headers["Content-Type"] = f"multipart/form-data; boundary={boundary}"
        else:
            body = urllib.parse.urlencode(data).encode()
        if csrf:
            headers["X-CSRF-Token"] = csrf
    else:
        body = None
    try:
        with opener.open(urllib.request.Request(url, body, headers), timeout=5) as response:
            return response.status, response.read()
    except urllib.error.HTTPError as error:
        return error.code, error.read()


def token(opener, base):
    status, page = request(opener, base)
    assert status == 200
    return re.search(rb'window.BABY_TRACKER = \{csrf: "([a-f0-9]+)"\}', page).group(1).decode()


def api(opener, base, action, data=None, csrf=None, multipart=False):
    status, raw = request(opener, f"{base}/api.php?action={action}", data, csrf, multipart)
    return status, json.loads(raw)


def main():
    php = shutil.which("php")
    assert php, "PHP must be installed"
    root = SOURCE / "storage" / f"baby-daily-test-{uuid.uuid4().hex}"
    root.mkdir()
    try:
        for filename in ("bootstrap.php", "api.php", "index.php", "image.php"):
            shutil.copy2(SOURCE / filename, root / filename)
        shutil.copytree(SOURCE / "config", root / "config")
        with socket.socket() as listener:
            listener.bind(("127.0.0.1", 0))
            port = listener.getsockname()[1]
        base = f"http://127.0.0.1:{port}"
        php_args = [php]
        fileinfo = Path(php).parent / "ext" / "php_fileinfo.dll"
        if fileinfo.is_file():
            php_args += ["-d", f"extension_dir={fileinfo.parent}", "-d", "extension=php_fileinfo.dll"]
        server = subprocess.Popen(
            php_args + ["-S", f"127.0.0.1:{port}", "-t", str(root)],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        try:
            for _ in range(50):
                if server.poll() is not None:
                    raise RuntimeError("PHP test server stopped unexpectedly")
                try:
                    with socket.create_connection(("127.0.0.1", port), timeout=0.1):
                        break
                except OSError:
                    time.sleep(0.1)
            admin = browser()
            admin_csrf = token(admin, base)
            status, _ = api(admin, base, "setup", {
                "username": "Alice", "password": "admin-password-123",
                "confirmPassword": "admin-password-123",
            }, admin_csrf)
            assert status == 200
            status, result = api(admin, base, "list")
            assert status == 200 and result["data"]["account"]["role"] == "super_admin"
            status, _ = api(admin, base, "add_baby", {
                "babyUsername": "Bob", "babyPassword": "user-password-123",
                "confirmBabyPassword": "user-password-123",
            }, admin_csrf)
            assert status == 201
            status, page = request(admin, base)
            assert status == 200 and b'Viewing baby' in page and b'>Bob</option>' in page
            auth = json.loads((root / "storage/data/auth.json").read_text())
            bob_id = auth["accounts"]["bob"]["babyId"]
            alice_id = auth["accounts"]["alice"]["babyId"]
            assert auth["accounts"]["alice"]["role"] == "super_admin"
            assert auth["accounts"]["bob"]["role"] == "user"

            user = browser()
            user_csrf = token(user, base)
            status, _ = api(user, base, "login", {
                "username": "Bob", "password": "user-password-123"
            }, user_csrf)
            assert status == 200
            status, _ = api(user, base, "select_baby", {"babyId": alice_id}, user_csrf)
            assert status == 403
            status, result = api(user, base, "upload", {
                "photoDate": "2026-08-23", "note": "Uploaded by Bob"
            }, user_csrf, multipart=True)
            assert status == 201, result
            photo_id = result["data"]["id"]
            filename = result["data"]["filename"]
            photo_path = root / "storage/babies" / bob_id / "uploads" / filename
            assert photo_path.is_file()
            status, _ = request(user, f"{base}/image.php?id={photo_id}")
            assert status == 200
            status, _ = request(admin, f"{base}/image.php?id={photo_id}")
            assert status == 200  # Admin is still viewing Bob after creation.
            status, _ = api(admin, base, "select_baby", {"babyId": alice_id}, admin_csrf)
            assert status == 200
            status, _ = request(admin, f"{base}/image.php?id={photo_id}")
            assert status == 404
            status, result = api(admin, base, "upload", {
                "photoDate": "2026-08-24", "note": "Alice photo"
            }, admin_csrf, multipart=True)
            assert status == 201, result
            alice_photo_id = result["data"]["id"]
            status, _ = api(user, base, "delete_photo", {"id": alice_photo_id}, user_csrf)
            assert status == 404
            status, _ = request(user, f"{base}/image.php?id={alice_photo_id}")
            assert status == 404
            status, _ = api(admin, base, "select_baby", {"babyId": bob_id}, admin_csrf)
            assert status == 200
            status, result = api(admin, base, "list")
            assert status == 200 and result["data"]["records"][0]["id"] == photo_id
            status, _ = api(admin, base, "delete_photo", {"id": photo_id}, admin_csrf)
            assert status == 200 and not photo_path.exists()
            status, _ = request(user, f"{base}/image.php?id={photo_id}")
            assert status == 404
            status, result = api(user, base, "list")
            assert status == 200 and result["data"]["records"] == []
            status, result = api(user, base, "upload", {
                "photoDate": "2026-08-23", "note": "Replacement"
            }, user_csrf, multipart=True)
            assert status == 201, result
            replacement_id = result["data"]["id"]
            status, _ = api(user, base, "delete_photo", {"id": replacement_id}, user_csrf)
            assert status == 200
            status, _ = api(user, base, "delete_photo", {"id": replacement_id}, user_csrf)
            assert status == 404
            auth["schemaVersion"] = 2
            for account in auth["accounts"].values():
                account.pop("role")
            (root / "storage/data/auth.json").write_text(json.dumps(auth))
            status, _ = request(admin, base)
            assert status == 200
            migrated = json.loads((root / "storage/data/auth.json").read_text())
            assert migrated["schemaVersion"] == 3
            assert migrated["accounts"]["alice"]["role"] == "super_admin"
            assert migrated["accounts"]["bob"]["role"] == "user"
            print("PASS: admin gallery access, regular account isolation, and photo removal")
        finally:
            server.terminate()
            server.wait(timeout=5)
    finally:
        shutil.rmtree(root)


if __name__ == "__main__":
    main()
