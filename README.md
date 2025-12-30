# YAML/MD Editor

A simple web-based editor for YAML and Markdown files with user authentication.

## Features

- Split-view layout (file list left, editor right)
- CodeMirror editor with syntax highlighting
- YAML syntax validation before save
- User authentication with SQLite database
- CRUD operations for files and users
- Admin panel for user management
- User import/export functionality
- Docker support

## Quick Start

### Using Docker

```bash
# Clone the repository
git clone https://github.com/walleralexander/yamleditor.git
cd yamleditor

# Copy environment file and configure
cp .env.example .env
# Edit .env with your settings

# Start with Docker Compose
docker-compose up -d
```

Access the editor at `http://localhost:8080`

### Manual Installation

Requirements:
- PHP 8.0+
- SQLite3 extension

```bash
# Clone and enter directory
git clone https://github.com/walleralexander/yamleditor.git
cd yamleditor

# Copy environment file
cp .env.example .env

# Start PHP development server
php -S localhost:8080 -t public
```

## Configuration

Copy `.env.example` to `.env` and adjust the settings:

| Variable | Description | Default |
|----------|-------------|---------|
| `ADMIN_USERNAME` | Initial admin username | `admin` |
| `ADMIN_PASSWORD` | Initial admin password | `changeme` |
| `FILES_DIR` | Directory for editable files | `data/files` |
| `DB_PATH` | SQLite database path | `database/users.db` |
| `SESSION_LIFETIME` | Session timeout in seconds | `3600` |
| `PASSWORD_MIN_LENGTH` | Minimum password length | `8` |
| `MAX_BACKUPS` | Number of backups to keep per file | `15` |

## Docker Configuration

Additional environment variables for Docker:

| Variable | Description | Default |
|----------|-------------|---------|
| `FILES_HOST_DIR` | Host directory to mount | `./data/files` |
| `PUID` | User ID for file permissions | `1000` |
| `PGID` | Group ID for file permissions | `1000` |

## Project Structure

```
yamleditor/
├── config/           # Configuration files
├── data/files/       # Editable YAML/MD files
├── database/         # SQLite database
├── public/           # Web root
│   ├── api/          # REST API endpoints
│   ├── js/           # Frontend JavaScript
│   ├── index.php     # Main editor
│   ├── login.php     # Login page
│   └── admin.php     # User management
├── src/              # PHP classes
├── .env.example      # Environment template
├── Dockerfile        # Docker image
└── docker-compose.yml
```

## API Endpoints

### Files API (`/api/files.php`)

- `GET /api/files.php` - List all files
- `GET /api/files.php?file=name.yaml` - Read file content
- `POST /api/files.php` - Create new file
- `PUT /api/files.php` - Update file
- `DELETE /api/files.php?file=name.yaml` - Delete file

### Users API (`/api/users.php`)

- `GET /api/users.php` - List all users (admin only)
- `GET /api/users.php?export=1` - Export users as JSON
- `POST /api/users.php` - Create user or import users
- `PUT /api/users.php` - Update user
- `DELETE /api/users.php?id=1` - Delete user

## License

This project is licensed under the Apache License 2.0 - see the [LICENSE](LICENSE) file for details.
