# SaaS Go Application

A Go version of the SaaS Starter. Compiles to a single binary for easy deployment.

## Features

- User authentication with JWT tokens
- Team management with roles
- Activity logging
- Invitations system
- MySQL database
- Single binary deployment

## Requirements

- Go 1.21+
- MySQL 5.7+ or MariaDB 10.2+

## Installation

1. Install dependencies:
   ```bash
   go mod download
   ```

2. Create MySQL database and import schema:
   ```sql
   CREATE DATABASE saas_app;
   source ../php-app/install/schema.sql
   ```

3. Set environment variables:
   ```bash
   export AUTH_SECRET='your-secret-key-min-32-characters'
   export DB_HOST='localhost'
   export DB_NAME='saas_app'
   export DB_USER='root'
   export DB_PASS=''
   export APP_NAME='My SaaS'
   export APP_URL='http://localhost:8080'
   ```

4. Run the application:
   ```bash
   go run main.go
   ```

## Build for Production

```bash
go build -o saas-app main.go
./saas-app
```

### Cross-compilation

```bash
# Linux
GOOS=linux GOARCH=amd64 go build -o saas-app-linux main.go

# Windows
GOOS=windows GOARCH=amd64 go build -o saas-app.exe main.go

# macOS
GOOS=darwin GOARCH=amd64 go build -o saas-app-darwin main.go
```

## Configuration

Environment variables:

| Variable | Description | Default |
|----------|-------------|---------|
| AUTH_SECRET | JWT signing key | change-this-secret |
| DB_HOST | Database host | localhost |
| DB_NAME | Database name | saas_app |
| DB_USER | Database user | root |
| DB_PASS | Database password | (empty) |
| APP_NAME | Application name | SaaS Starter |
| APP_URL | Application URL | http://localhost:8080 |
| PORT | Server port | 8080 |

## License

MIT License
