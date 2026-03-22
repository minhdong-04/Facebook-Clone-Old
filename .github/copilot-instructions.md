# Copilot Instructions for AI Agents

## Project Overview
This is a PHP-based social networking web application with a modular structure. The codebase is organized by feature and responsibility, with clear separation between authentication, core pages, API endpoints, and real-time features.

## Key Components & Structure
- **Root PHP files**: Entry points and utility scripts (e.g., `index.php`, `b.php`, `c.php`).
- **pages/**: Main user-facing pages (e.g., `home.php`, `profile.php`, `chat.php`).
- **auth/**: Authentication and registration flows (e.g., `login.php`, `register.php`, `verify.php`).
- **actions/**: AJAX endpoints for dynamic UI actions (e.g., `post_create.php`, `like.php`, `send_message.php`).
- **api/**: API endpoints for user and data search.
- **includes/**: Shared PHP includes for DB access, headers, footers, cookies, and language support.
- **assets/**: Static files (CSS, JS, images) for frontend styling and interactivity.
- **socket/**: Node.js server for real-time features (e.g., chat), with its own `package.json` and `server.js`.
- **vendor/PHPMailer-master/**: Third-party library for sending emails.

## Data Flow & Integration
- **PHP pages** render HTML and interact with the database via `includes/db.php`.
- **AJAX**: Frontend JS calls `actions/` endpoints for dynamic updates (likes, posts, messages).
- **Authentication**: Managed in `auth/`, with session/cookie logic in `includes/`.
- **Real-time**: Chat and notifications use the Node.js server in `socket/`, likely communicating via WebSockets.
- **Email**: Outbound email uses PHPMailer from `vendor/`.

## Conventions & Patterns
- Use `includes/header.php` and `includes/footer.php` for consistent page layout.
- Database access is centralized in `includes/db.php`.
- AJAX endpoints in `actions/` expect POST requests and return JSON.
- User authentication state is checked in most `pages/` and `auth/` scripts.
- Static assets are referenced via the `assets/` directory.
- Real-time features are isolated in the `socket/` folder and do not mix with PHP logic.

## Developer Workflows
- **No build step** for PHP; changes are live.
- **Node.js server**: Start with `npm install` and `node server.js` in `socket/` for real-time features.
- **Database**: Schema in `mysql/database.sql`.
- **Email**: Configure PHPMailer in `includes/` as needed.
- **Debugging**: Use browser dev tools for frontend, and PHP error logs for backend.

## Examples
- To add a new AJAX action: create a PHP file in `actions/`, handle POST, and return JSON.
- To add a new page: create a PHP file in `pages/`, include `header.php` and `footer.php`.
- To add a real-time event: update `socket/server.js` and coordinate with frontend JS.

## References
- See `includes/` for shared logic and configuration.
- See `auth/` for authentication flows.
- See `socket/` for real-time server logic.
- See `mysql/database.sql` for DB schema.

---
For more details, review the structure and comments in each directory. Update this file as the architecture evolves.