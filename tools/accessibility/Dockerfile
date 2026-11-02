# 用完即丟的無障礙測試桌面環境，不會被應用程式或正式發布的 image 使用。
FROM debian:trixie-slim@sha256:62bd423aeee912639ca8ee681c2b762cb3e25137e0a92b20be455898a9f034ad

RUN apt-get update && apt-get install -y --no-install-recommends \
    at-spi2-core ca-certificates dbus-x11 espeak-ng firefox-esr fonts-noto-cjk \
    gsettings-desktop-schemas libatk-adaptor novnc openbox orca pulseaudio \
    python3 speech-dispatcher speech-dispatcher-espeak-ng socat tigervnc-tools \
    websockify x11-utils x11vnc xauth xvfb \
    && rm -rf /var/lib/apt/lists/* \
    && useradd --uid 1000 --create-home spl \
    && mkdir /state && chown spl:spl /state
COPY --chmod=755 entrypoint.sh session.sh /usr/local/bin/
USER 1000:1000
ENV DISPLAY=:99 HOME=/home/spl LANG=C.UTF-8 XAUTHORITY=/home/spl/.Xauthority \
    XDG_RUNTIME_DIR=/run/user/1000 NO_AT_BRIDGE=0 GTK_MODULES=gail:atk-bridge
WORKDIR /home/spl
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
