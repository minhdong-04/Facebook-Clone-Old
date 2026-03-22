# Deploy Socket.IO on Azure VM (Linux)

This repo contains:
- PHP app under `/fb`
- Node Socket.IO server under `/fb/socket` (default port 3000)

## Recommended topology (HTTPS/WSS)
- Nginx serves the PHP site on `:80/:443`
- Node listens on `127.0.0.1:3000`
- Nginx reverse-proxies `/socket.io/` to Node
- Azure NSG only exposes `22`, `80`, `443` (no public `3000`)

This avoids mixed-content issues and keeps the socket port private.

---

## 1) Azure NSG inbound rules
Open:
- `22/tcp` (SSH) — restrict **Source IP** to your IP
- `80/tcp` (HTTP)
- `443/tcp` (HTTPS)

Do **not** open `3000` when using reverse proxy.

---

## 2) Install packages on the VM
Ubuntu/Debian example:

```bash
sudo apt update
sudo apt install -y nginx
# Install Node 18+ (pick one method). Example using NodeSource:
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
sudo npm i -g pm2
```

---

## 3) Run the socket server with PM2
From your project folder:

```bash
cd /var/www/html/fb/socket
npm install
pm2 start ecosystem.config.cjs
pm2 save
pm2 startup
```

Edit env values in `socket/ecosystem.config.cjs`:
- `SOCKET_EMIT_TOKEN`: set a long random string
- `PHP_BASE_URL`: normally `http://127.0.0.1`

---

## 4) Configure PHP -> Node emit token
On the VM, set the same token for PHP (Apache or php-fpm env).
Simplest (Apache) approach: set env in your vhost or `/etc/apache2/envvars`.
If you prefer per-app `.env`, you can export env vars before starting Apache.

Required:
- `SOCKET_EMIT_URL=http://127.0.0.1:3000/emit`
- `SOCKET_EMIT_TOKEN=<same as Node>`

---

## 5) Nginx reverse proxy for Socket.IO
In your Nginx site config (server block), add:

```nginx
location /socket.io/ {
  proxy_pass http://127.0.0.1:3000/socket.io/;
  proxy_http_version 1.1;
  proxy_set_header Upgrade $http_upgrade;
  proxy_set_header Connection "upgrade";
  proxy_set_header Host $host;
  proxy_set_header X-Real-IP $remote_addr;
  proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
  proxy_set_header X-Forwarded-Proto $scheme;
}
```

Then:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## 6) Health check
From the VM:

```bash
curl -sS http://127.0.0.1:3000/healthz
```

From your browser (same domain as the PHP site):
- open the app and check console for Socket.IO connection

---

## Notes
- If you run the website as plain HTTP and want to expose port 3000 publicly, you can:
  - set `SOCKET_LISTEN_HOST=0.0.0.0`
  - open `3000/tcp` in NSG + UFW
  - BUT this is not recommended if you later enable HTTPS.
