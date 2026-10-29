#!/bin/bash
# [E-30] Private X display and authentication; no host X11/audio/input mounts.
set -euo pipefail
umask 077
mkdir -p "$HOME/.vnc" "$XDG_RUNTIME_DIR" "$HOME/firefox-profile"
python3 -c 'import secrets; print(secrets.token_hex(16))' > "$HOME/.x-cookie"
touch "$XAUTHORITY"
xauth add "$DISPLAY" . "$(cat "$HOME/.x-cookie")"
tigervncpasswd -f < /run/secrets/vnc_password > "$HOME/.vnc/passwd"
Xvfb "$DISPLAY" -screen 0 1280x800x24 -s 0 -nolisten tcp -auth "$XAUTHORITY" > /state/xvfb.log 2>&1 &
for attempt in {1..50}; do
    if xdpyinfo >/dev/null 2>&1; then
        exec dbus-run-session -- /usr/local/bin/session.sh
    fi
    sleep 0.1
done
echo 'Private X display failed to start' >&2
exit 1
