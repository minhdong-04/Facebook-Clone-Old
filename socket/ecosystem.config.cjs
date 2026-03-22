module.exports = {
  apps: [
    {
      name: 'fb-socket',
      cwd: __dirname,
      script: 'server.js',
      env: {
        PORT: 3000,
        // Recommended on Azure VM when using Nginx reverse proxy:
        // SOCKET_LISTEN_HOST: '127.0.0.1',
        // If you expose port 3000 directly:
        // SOCKET_LISTEN_HOST: '0.0.0.0',
        SOCKET_LISTEN_HOST: '127.0.0.1',

        // Must match PHP env SOCKET_EMIT_TOKEN
        SOCKET_EMIT_TOKEN: 'change-me-long-random',

        // PHP app base URL for server-side persistence calls
        PHP_BASE_URL: 'http://127.0.0.1'
      }
    }
  ]
};
