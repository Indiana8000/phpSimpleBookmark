# Screenshot Server

Example of a simple solution. I run this python script `server.py` with LibreWolf on Alpine Linux inside a LXC container on my ProxMox home lab. Using Debian/Ubuntu should be similar.

More complex solution see `server.mjs` which use nodejs + puppeteer + chromium


## Alpine Linux

### Required Packages
```
apk add --no-cache \
  librewolf \
  python3 \
  xvfb \
  dbus \
  ttf-freefont \
  font-noto \
  mesa-dri-gallium \
  mesa-gl \
  libxcomposite \
  libxdamage \
  libxrandr \
  libxtst \
  libxinerama \
  libxcursor \
  libxfixes
```

### Autostart

Add to crontab:
```
@reboot /usr/bin/python3 /root/server.py >&- 2>&-
```

### Manual Testing

Starting the X server:
```
Xvfb :99 -screen 0 1920x1080x24 &
export DISPLAY=:99
```

Options if no GPU available
```
export MOZ_DISABLE_RDD_SANDBOX=1
export MOZ_WEBRENDER=0
export LIBGL_ALWAYS_SOFTWARE=1
```

Example with extra profile
```
mkdir /tmp/profile-tmp1
librewolf --headless -no-remote --profile /tmp/profile-tmp1 --window-size=1920,1080 --screenshot /var/www/google.png https://www.google.com/
rm -rf /tmp/profile-tmp1
```
The --headless is optional as the XServer is exlusivly for librewolf.

You can change the --window-size=1920,1080 e.g. to --window-size=1920,5000 to capture longer webpages.


Example of how to create a screen shot of the howl XServer (Require ImageMagic)
```
DISPLAY=:99 import -window root screenshot.png
```
