"""Exercise the three roles against an isolated runnable demo."""
import http.cookiejar
import pathlib
import re
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

root = pathlib.Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory() as folder:
    demo = pathlib.Path(folder) / 'demo'
    output = subprocess.check_output(['php', str(root / 'setup.php'), 'demo', str(demo)], text=True)
    passwords = dict(re.findall(r'^(demo-[\w-]+) \(\w+\)  (\w+)$', output, re.M))
    server = subprocess.Popen(['php', '-S', '127.0.0.1:8099', '-t', folder], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(50):
            try:
                urllib.request.urlopen('http://127.0.0.1:8099/demo/login.php')
                break
            except urllib.error.URLError:
                time.sleep(.1)
        for name, role in [('demo-super','Super admin'),('demo-trades','Business'),('demo-customer','Customer')]:
            client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
            base = 'http://127.0.0.1:8099/demo/'
            page = client.open(base+'login.php').read().decode()
            token = re.search(r'name="csrf" value="([a-f0-9]+)"',page)[1]
            data = urllib.parse.urlencode(dict(username=name,password=passwords[name],csrf=token)).encode()
            page = client.open(base+'login.php',data).read().decode()
            assert role+' dashboard' in page, role
            if role == 'Business':
                editor = client.open(base+'admin.php').read().decode()
                assert 'Demo Faithful Home Services' in editor and 'Demo Good Neighbor Woodworking' not in editor
                token = re.search(r'name="csrf" value="([a-f0-9]+)"', editor)[1]
                listing = dict(action='save', csrf=token, name='Upload fixture', testimony='Fixture', skills='plumbing')
                parts = [f'--KCBOUND\r\nContent-Disposition: form-data; name="{field}"\r\n\r\n{value}\r\n'.encode() for field,value in listing.items()]
                parts.append(b'--KCBOUND\r\nContent-Disposition: form-data; name="pictureFile"; filename="test.gif"\r\nContent-Type: image/png\r\n\r\n')
                import struct, zlib
                def chunk(kind, raw):
                    return struct.pack('>I',len(raw))+kind+raw+struct.pack('>I',zlib.crc32(kind+raw))
                png = b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR',struct.pack('>IIBBBBB',1,1,8,2,0,0,0)) + chunk(b'IDAT',zlib.compress(bytes([0,30,80,120]))) + chunk(b'IEND',b'')
                request = urllib.request.Request(base+'admin.php',b''.join(parts)+png+b'\r\n--KCBOUND--\r\n',{'Content-Type':'multipart/form-data; boundary=KCBOUND'})
                client.open(request).read()
                assert 'data:image/jpeg;base64,' in client.open(base).read().decode()
            if role == 'Customer':
                assert 'Pending verification' in page and 'Payment verified' in page
                assert page.count('Publish review') == 1
                try:
                    client.open(base+'admin.php')
                    raise AssertionError('Customer accessed business editor')
                except urllib.error.HTTPError as exc:
                    assert exc.code == 403
    finally:
        server.terminate()
        server.wait()
print('Standalone demo login and role checks passed.')
