#!/bin/bash
set -euo pipefail
umask 077
# 在這個測試桌面內，Firefox 使用的 localhost 來源維持不變。
for port in 8080 8081; do
    socat "TCP-LISTEN:$port,bind=127.0.0.1,reuseaddr,fork" "TCP:host.docker.internal:$port" > "/state/proxy-$port.log" 2>&1 &
done
python3 -c 'from gi.repository import Gio; assert Gio.Settings.new("org.gnome.desktop.interface").set_boolean("toolkit-accessibility", True)'
openbox --sm-disable > /state/openbox.log 2>&1 &
pulseaudio --start --exit-idle-time=-1 --log-target=file:/state/pulse.log
speech-dispatcher --spawn
cat > "$HOME/firefox-profile/user.js" <<'PROFILE'
user_pref("browser.shell.checkDefaultBrowser", false);
user_pref("browser.startup.homepage_override.mstone", "ignore");
user_pref("browser.newtabpage.enabled", false);
user_pref("datareporting.healthreport.uploadEnabled", false);
user_pref("datareporting.policy.dataSubmissionEnabled", false);
user_pref("toolkit.telemetry.enabled", false);
user_pref("accessibility.force_disabled", 0);
PROFILE
orca --enable=speech --disable=braille --debug-file=/state/orca-debug.log > /state/orca.log 2>&1 &
firefox-esr --no-remote --profile "$HOME/firefox-profile" --width 1280 --height 760 http://localhost:8081/index.html > /state/firefox.log 2>&1 &
x11vnc -display "$DISPLAY" -auth "$XAUTHORITY" -rfbauth "$HOME/.vnc/passwd" -listen 127.0.0.1 -rfbport 5900 -forever -shared > /state/vnc.log 2>&1 &
exec websockify --web=/usr/share/novnc 0.0.0.0:6080 127.0.0.1:5900
