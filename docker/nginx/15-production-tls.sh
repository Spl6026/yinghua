#!/bin/sh
set -eu
# 重用共用的路由設定，只在正式環境的 image 改為 TLS。容器內的 8082 只用於 liveness 檢查。
template=/etc/nginx/templates/default.conf.template
mkdir -p /etc/nginx/templates
cp /etc/nginx/yinghua-base.template "$template"
test -s /etc/nginx/tls/fullchain.pem
test -s /etc/nginx/tls/privkey.pem
grep -q '^    listen 80;$' "$template"
sed -i 's@^    listen 80;$@    listen 443 ssl;\n    ssl_certificate /etc/nginx/tls/fullchain.pem;\n    ssl_certificate_key /etc/nginx/tls/privkey.pem;\n    ssl_protocols TLSv1.2 TLSv1.3;\n    add_header Strict-Transport-Security "max-age=31536000" always;\n    error_log /dev/null crit;@' "$template"
cat >> "$template" <<'NGINX'

server {
    listen 80;
    server_name _;
    access_log off;
    return 308 ${CANONICAL_ORIGIN}$request_uri;
}
server {
    listen 8082;
    server_name _;
    access_log off;
    location = /_alive { return 200 "alive\n"; }
    location / { return 404; }
}
NGINX
