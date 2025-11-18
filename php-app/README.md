# SaaS PHP Application

A PHP version of the Next.js SaaS Starter, designed to run on shared hosting environments without SSH or Composer access.

## Features

- User authentication with JWT tokens
- Team management with roles
- Activity logging
- Invitations system
- Stripe integration for subscriptions
- No dependencies (no Composer required)
- MySQL database
- Web-based installer

## Requirements

- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.2+
- Apache with mod_rewrite (or nginx)
- PDO PHP extension
- cURL PHP extension

## Installation

### Method 1: Web Installer (Recommended for Shared Hosting)

1. Upload all files to your web server
2. Navigate to `http://yourdomain.com/install` in your browser
3. Follow the installation wizard

### Method 2: Manual Installation

1. Create a MySQL database

2. Import the schema:
   ```sql
   source install/schema.sql
   ```

3. Copy the config file:
   ```bash
   cp config/config.php config/config.local.php
   ```

4. Edit `config/config.local.php` with your settings:
   - Database credentials
   - Application URL
   - Authentication secret (generate a random 32+ character string)
   - Stripe keys (optional)

5. Point your web server to the `public` directory

6. Create your first user account at `/sign-up`

## Directory Structure

```
php-app/
├── config/          # Configuration files
├── controllers/     # Request handlers
├── install/         # Installation files
├── lib/             # Core libraries
├── models/          # Database models
├── public/          # Web root (point your server here)
│   ├── css/         # Stylesheets
│   ├── js/          # JavaScript
│   └── index.php    # Entry point
└── views/           # Templates
```

## Configuration

### Environment Variables

All configuration is stored in `config/config.local.php`:

```php
// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'saas_app');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application
define('APP_NAME', 'My SaaS');
define('APP_URL', 'https://yourdomain.com');
define('APP_ENV', 'production');
define('APP_DEBUG', false);

// Authentication
define('AUTH_SECRET', 'your-secret-key-min-32-characters');

// Stripe
define('STRIPE_SECRET_KEY', 'sk_live_...');
define('STRIPE_PUBLISHABLE_KEY', 'pk_live_...');
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');
```

### Web Server Configuration

#### Apache

The included `.htaccess` files handle URL rewriting. Make sure `mod_rewrite` is enabled.

#### Nginx

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/php-app/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

## Stripe Setup

1. Create products and prices in your Stripe dashboard
2. Update price IDs in `views/dashboard/pricing.php`
3. Update plan names mapping in `lib/Stripe.php`
4. Set up webhooks in Stripe dashboard pointing to:
   ```
   https://yourdomain.com/api/stripe/webhook
   ```

   Enable these events:
   - `checkout.session.completed`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`

## Security

- All passwords are hashed using bcrypt
- CSRF protection on all forms
- JWT tokens for authentication
- Prepared statements for all database queries
- XSS protection via output escaping
- Security headers via .htaccess

## Customization

### Adding New Routes

Edit `public/index.php`:

```php
$router->get('/my-page', function() {
    echo View::render('my-page', ['title' => 'My Page']);
}, [$authMiddleware]);
```

### Adding New Controllers

Create a new file in `controllers/`:

```php
<?php
class MyController {
    public function index() {
        // Your logic here
        echo View::render('my-view', ['data' => $data]);
    }
}
```

### Adding New Models

Create a new file in `models/`:

```php
<?php
class MyModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findAll() {
        return $this->db->fetchAll("SELECT * FROM my_table");
    }
}
```

## License

MIT License

## Support

For issues and feature requests, please open an issue on GitHub.
