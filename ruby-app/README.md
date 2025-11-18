# SaaS Ruby/Sinatra Application

A Ruby/Sinatra version of the SaaS Starter.

## Features

- User authentication with JWT tokens
- Team management with roles
- Activity logging
- Invitations system
- MySQL database

## Requirements

- Ruby 2.7+
- MySQL 5.7+ or MariaDB 10.2+

## Installation

1. Install dependencies:
   ```bash
   bundle install
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
   export APP_URL='http://localhost:4567'
   ```

4. Run the application:
   ```bash
   ruby app.rb
   ```

## Production Deployment

```bash
bundle exec puma -C config/puma.rb
```

Or with Rack:
```bash
bundle exec rackup config.ru -p 4567
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
| APP_URL | Application URL | http://localhost:4567 |

## License

MIT License
