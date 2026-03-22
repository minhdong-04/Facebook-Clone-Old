const http = require("http");
const express = require("express");
const { Server } = require("socket.io");
const axios = require("axios");

const PORT = process.env.PORT ? Number(process.env.PORT) : 3000;
// If you're reverse-proxying via Nginx/Apache, keep this bound to 127.0.0.1 and proxy /socket.io.
// If you want to expose the socket directly, use 0.0.0.0.
const HOST = process.env.SOCKET_LISTEN_HOST || '';
const EMIT_TOKEN = process.env.SOCKET_EMIT_TOKEN || "dev-token-change-me";

// PHP app base URL (used for best-effort persistence when socket server receives sendMessage).
// On Azure VM: typically http://127.0.0.1 (or http://127.0.0.1/fb if project is under /fb).
const PHP_BASE_URL = (process.env.PHP_BASE_URL || 'http://127.0.0.1').replace(/\/+$/, '');

const app = express();
app.set('trust proxy', true);
app.use(express.json({ limit: "256kb" }));
app.use(express.urlencoded({ extended: true }));

const server = http.createServer(app);

const io = new Server(server, {
  // Cloudflare Tunnel / reverse proxies:
  // - If you use polling, Socket.IO may send CORS headers.
  // - "credentials: true" cannot be paired with origin: "*".
  // Use origin reflection instead.
  cors: { origin: true, credentials: true }
});

app.get('/healthz', (_req, res) => res.status(200).send('ok'));

// userId => Set(socketId)
const onlineUsers = new Map();

io.on("connection", socket => {
  console.log("Connected:", socket.id);

  // JOIN
  socket.on("join", userId => {
    const uid = String(userId || '').trim();
    if (!uid) return;

    socket.userId = uid;

    const wasOnline = onlineUsers.has(uid) && onlineUsers.get(uid) && onlineUsers.get(uid).size > 0;

    // join room
    socket.join(`user_${uid}`);

    // lưu socket
    if (!onlineUsers.has(uid)) {
      onlineUsers.set(uid, new Set());
    }
    onlineUsers.get(uid).add(socket.id);

    // Send snapshot to this client (best-effort) so UI can mark dots instantly.
    try {
      socket.emit('presence:snapshot', {
        online_user_ids: Array.from(onlineUsers.keys()).map((x) => String(x))
      });
    } catch (_e) {}

    // Broadcast online only when user transitions offline -> online
    if (!wasOnline) {
      io.to('feed').emit('presence:update', { user_id: uid, online: true, time: Date.now() });
    }

    console.log("User online:", uid);
  });

  // Optional: join shared feed room
  socket.on("joinFeed", () => {
    socket.join("feed");
  });

  // SEND MESSAGE
  socket.on("sendMessage", async (data, ack) => {
    const reply = typeof ack === 'function' ? ack : null;
    try {
      const payload = {
        id: data && data.id ? data.id : undefined,
        from_user: data.from_user,
        to_user: data.to_user,
        message: data.message,
        content: data.content, // optional compatibility
        created_at: data.created_at,
        time: Date.now()
      };

      // If client already persisted via browser POST, skip DB write here.
      if (!data || !data.no_persist) {
        // best-effort persist via PHP if a Cookie is provided
        await axios.post(
          `${PHP_BASE_URL}/fb/actions/send_message.php`,
          new URLSearchParams({
            message: data.message,
            to_user: data.to_user
          }),
          {
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
              Cookie: (data && data.cookie) ? data.cookie : ''
            },
            timeout: 5000,
            validateStatus: () => true
          }
        ).then((res) => {
          if (res && res.status >= 200 && res.status < 300 && res.data && res.data.message) {
            payload.id = res.data.message.id;
            payload.created_at = res.data.message.created_at;
          }
          if (res && (res.status < 200 || res.status >= 300)) {
            console.error('Persist failed:', res.status, typeof res.data === 'string' ? res.data : JSON.stringify(res.data));
          }
        }).catch((e) => {
          console.error('Persist error:', e && e.message ? e.message : e);
        });
      }

      // Realtime broadcast
      io.to(`user_${data.to_user}`).emit("newMessage", payload);
      io.to(`user_${data.from_user}`).emit("newMessage", payload);

      if (reply) reply({ ok: true, relayed: true });
    } catch (err) {
      console.error("Socket error:", err && err.message ? err.message : err);
      if (reply) reply({ ok: false });
    }
  });

  // READ RECEIPT: client tells server it has read up to message_id
  // payload: { from_user, to_user, last_read_message_id }
  socket.on('readReceipt', (data, ack) => {
    const reply = typeof ack === 'function' ? ack : null;
    try {
      const from = data && data.from_user;
      const to = data && data.to_user;
      const lastRead = data && data.last_read_message_id;
      if (!from || !to || !lastRead) {
        if (reply) reply({ ok: false });
        return;
      }

      const payload = {
        from_user: from,
        to_user: to,
        last_read_message_id: lastRead,
        time: Date.now()
      };

      io.to(`user_${to}`).emit('readReceipt', payload);
      io.to(`user_${from}`).emit('readReceipt', payload);
      if (reply) reply({ ok: true });
    } catch (e) {
      console.error('readReceipt error', e && e.message ? e.message : e);
      if (reply) reply({ ok: false });
    }
  });

  // ===== WEBRTC SIGNALING (audio/video call) =====
  // payloads must include: from_user, to_user
  function relayToUserRoom(toUserId, event, payload) {
    try {
      if (!toUserId) return;
      io.to(`user_${toUserId}`).emit(event, payload);
    } catch (e) {
      console.error('relay error', event, e && e.message ? e.message : e);
    }
  }

  socket.on('webrtc:offer', (data) => {
    const to = data && data.to_user;
    relayToUserRoom(to, 'webrtc:offer', {
      from_user: data && data.from_user,
      to_user: to,
      kind: data && data.kind,
      sdp: data && data.sdp,
      time: Date.now()
    });
  });

  socket.on('webrtc:answer', (data) => {
    const to = data && data.to_user;
    relayToUserRoom(to, 'webrtc:answer', {
      from_user: data && data.from_user,
      to_user: to,
      kind: data && data.kind,
      sdp: data && data.sdp,
      time: Date.now()
    });
  });

  socket.on('webrtc:ice', (data) => {
    const to = data && data.to_user;
    relayToUserRoom(to, 'webrtc:ice', {
      from_user: data && data.from_user,
      to_user: to,
      candidate: data && data.candidate,
      time: Date.now()
    });
  });

  socket.on('webrtc:hangup', (data) => {
    const to = data && data.to_user;
    relayToUserRoom(to, 'webrtc:hangup', {
      from_user: data && data.from_user,
      to_user: to,
      time: Date.now()
    });
  });

  socket.on('webrtc:reject', (data) => {
    const to = data && data.to_user;
    relayToUserRoom(to, 'webrtc:reject', {
      from_user: data && data.from_user,
      to_user: to,
      time: Date.now()
    });
  });

  // Call state events (Messenger-like)
  socket.on('webrtc:ringing', (data) => {
    const to = data && data.to_user;
    relayToUserRoom(to, 'webrtc:ringing', {
      from_user: data && data.from_user,
      to_user: to,
      kind: data && data.kind,
      time: Date.now()
    });
  });

  socket.on('webrtc:accepted', (data) => {
    const to = data && data.to_user;
    relayToUserRoom(to, 'webrtc:accepted', {
      from_user: data && data.from_user,
      to_user: to,
      kind: data && data.kind,
      time: Date.now()
    });
  });

  socket.on('webrtc:busy', (data) => {
    const to = data && data.to_user;
    relayToUserRoom(to, 'webrtc:busy', {
      from_user: data && data.from_user,
      to_user: to,
      kind: data && data.kind,
      time: Date.now()
    });
  });

  socket.on('webrtc:cancel', (data) => {
    const to = data && data.to_user;
    relayToUserRoom(to, 'webrtc:cancel', {
      from_user: data && data.from_user,
      to_user: to,
      kind: data && data.kind,
      time: Date.now()
    });
  });

  socket.on("disconnect", () => {
    const userId = socket.userId;
    if (!userId) return;

    const sockets = onlineUsers.get(userId);
    if (sockets) {
      sockets.delete(socket.id);
      if (sockets.size === 0) {
        onlineUsers.delete(userId);

        // Broadcast offline only when last socket closes
        io.to('feed').emit('presence:update', { user_id: String(userId), online: false, time: Date.now() });
      }
    }
    console.log("Disconnected:", socket.id);
  });
});

// Authenticated emit endpoint for PHP -> Node bridge
// Body: { token, event, room?: string, payload?: any }
app.post("/emit", (req, res) => {
  try {
    const token = req.body && req.body.token;
    if (!token || token !== EMIT_TOKEN) {
      return res.status(401).json({ ok: false });
    }

    const event = req.body && req.body.event;
    const room = (req.body && req.body.room) || "feed";
    const payload = (req.body && req.body.payload) || {};

    if (!event || typeof event !== "string") {
      return res.status(400).json({ ok: false });
    }

    io.to(room).emit(event, payload);
    return res.json({ ok: true });
  } catch (e) {
    console.error("/emit error:", e);
    return res.status(500).json({ ok: false });
  }
});

server.listen(PORT, HOST || undefined, () => {
  const bind = HOST ? `${HOST}:${PORT}` : `:${PORT}`;
  console.log(`Socket server listening on ${bind}`);
  console.log(`PHP_BASE_URL=${PHP_BASE_URL}`);
});
