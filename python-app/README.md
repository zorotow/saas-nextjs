# SaaS Python/Flask Application

A Python/Flask version of the SaaS Starter, designed for easy deployment.

## Features

- User authentication with JWT tokens
- Team management with roles
- Activity logging
- Invitations system
- Stripe integration for subscriptions
- MySQL database

## Requirements

- Python 3.8+
- MySQL 5.7+ or MariaDB 10.2+

## Installation

1. Install dependencies:
   ```bash
   pip install -r requirements.txt
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
   export APP_URL='http://localhost:5000'
   ```

4. Run the application:
   ```bash
   python app.py
   ```

## Production Deployment

### Using Gunicorn
```bash
pip install gunicorn
gunicorn -w 4 -b 0.0.0.0:5000 wsgi:app
```

### Using uWSGI
```bash
pip install uwsgi
uwsgi --http :5000 --wsgi-file wsgi.py --callable app
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
| APP_URL | Application URL | http://localhost:5000 |
| STRIPE_SECRET_KEY | Stripe secret key | (empty) |
| STRIPE_WEBHOOK_SECRET | Stripe webhook secret | (empty) |

## License

MIT License
