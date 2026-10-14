"""Exercise JSON errors without real credentials or external API calls."""
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

with tempfile.TemporaryDirectory() as directory:
    root = Path(directory)
    (root / 'review').mkdir()
    shutil.copy(Path(__file__).resolve().parents[1] / 'review/api.php', root / 'review/api.php')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    server = subprocess.Popen([os.environ.get('PHP_BINARY', 'php'), '-n', '-S', f'127.0.0.1:{port}', '-t', str(root)], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    def request():
        req = urllib.request.Request(f'http://127.0.0.1:{port}/review/api.php', data=b'{"action":"queue"}', headers={'Content-Type': 'application/json'})
        try:
            response = urllib.request.urlopen(req, timeout=3)
        except urllib.error.HTTPError as error:
            response = error
        body = response.read().decode()
        assert 'no-store' in response.headers['Cache-Control']
        return response.status, json.loads(body), body
    try:
        for _ in range(30):
            try:
                status, data, body = request()
                break
            except urllib.error.URLError:
                time.sleep(.1)
        assert status == 503
        config = root / 'config.local.php'
        config.write_text("<?php echo 'DO_NOT_EXPOSE'; trigger_error('PRIVATE_WARNING'); return ['review_token'=>'" + 'a'*64 + "'];")
        status, data, body = request()
        assert status == 401
        assert 'DO_NOT_EXPOSE' not in body and 'PRIVATE_WARNING' not in body
        assert data['stage'] == 'configuration' and len(data['request_id']) == 16
        log = root / 'errors.log'
        config.write_text("<?php ini_set('log_errors','1'); ini_set('error_log', __DIR__.'/errors.log'); throw new RuntimeException('SYNTHETIC_PRIVATE_MESSAGE', 401);")
        status, data, body = request()
        assert status == 422 and data['stage'] == 'configuration'
        assert data['error'] == 'SYNTHETIC_PRIVATE_MESSAGE'
        logged = log.read_text()
        assert data['request_id'] in logged and 'code 401' in logged
        assert 'SYNTHETIC_PRIVATE_MESSAGE' not in logged
        config.write_text('<?php invalid syntax here;')
        status, data, body = request()
        assert status == 500 and data['stage'] == 'configuration'
        assert 'invalid syntax' not in body
        print('Review HTTP JSON isolation, auth and config-error checks passed.')
    finally:
        server.terminate()
        server.wait(timeout=5)
