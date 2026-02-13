#!/usr/bin/env python3
#
# Simple screenshot server using LibreWolf and Xvfb
#
# Usage:
#   python3 server.py
#
# Example request:
#   http://localhost:8080/?url=https://example.com
#
# Alpine Linux setup example:
#   apk add librewolf python3 xvfb dbus ttf-freefont font-noto mesa-dri-gallium mesa-gl libxcomposite libxdamage libxrandr libxtst libxinerama libxcursor libxfixes
#
# Crontab:
#   @reboot /usr/bin/python3 /root/server.py >&- 2>&-
#

from http.server import BaseHTTPRequestHandler, HTTPServer
from urllib.parse import urlparse, parse_qs
import subprocess
import tempfile
import time
import os

HOST = ""
PORT = 8080
TIMEOUT = 20

LIBREWOLF = "/usr/lib/librewolf/librewolf"

class ScreenshotHandler(BaseHTTPRequestHandler):

    def do_GET(self):
        qs = parse_qs(urlparse(self.path).query)
        url = qs.get("url", [None])[0]

        if not url or not url.startswith(("http://", "https://")):
            self.send_error(400, "Missing or invalid url")
            return

        display = f":{1000 + os.getpid()}"
        profile = tempfile.mkdtemp(prefix="lw-profile-")
        outfile = tempfile.NamedTemporaryFile(
            suffix=".png", delete=False
        ).name

        xvfb = None
        lw = None

        try:
            # 1. Xvfb starten
            xvfb = subprocess.Popen(
                ["Xvfb", display, "-screen", "0", "1920x1080x24"],
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL
            )

            time.sleep(0.5)

            env = os.environ.copy()
            env["DISPLAY"] = display

            # 2. LibreWolf starten (kein --headless!)
            lw = subprocess.Popen(
                [
                    LIBREWOLF,
                    "--no-remote",
                    "--window-size=1920,1080",
                    "--profile", profile,
                    "--screenshot", outfile,
                    url
                ],
                env=env,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL
            )

            lw.wait(timeout=TIMEOUT)

            if not os.path.exists(outfile):
                raise RuntimeError("Screenshot not created")

            # 3. PNG zurückgeben
            self.send_response(200)
            self.send_header("Content-Type", "image/png")
            self.send_header("Cache-Control", "no-store")
            self.end_headers()

            with open(outfile, "rb") as f:
                self.wfile.write(f.read())

        except Exception as e:
            self.send_error(500, str(e))

        finally:
            # Cleanup
            if lw and lw.poll() is None:
                lw.kill()

            if xvfb:
                xvfb.terminate()
                try:
                    xvfb.wait(timeout=2)
                except subprocess.TimeoutExpired:
                    xvfb.kill()

            os.system(f"rm -rf {profile}")
            if os.path.exists(outfile):
                os.unlink(outfile)

    def log_message(self, *args):
        # Logging unterdrücken
        pass


if __name__ == "__main__":
    print(f"Listening on http://{HOST}:{PORT}")
    HTTPServer((HOST, PORT), ScreenshotHandler).serve_forever()
