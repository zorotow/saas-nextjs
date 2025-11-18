"""
Configuration file for SaaS Python Application
Copy this to config_local.py and update with your settings
"""

import os

# Application
APP_NAME = 'SaaS Starter'
APP_URL = 'http://localhost:5000'
APP_ENV = 'development'  # 'production' or 'development'
DEBUG = APP_ENV == 'development'

# Secret key for sessions and JWT
AUTH_SECRET = 'change-this-secret-key-min-32-characters-long'

# Database
DB_HOST = 'localhost'
DB_NAME = 'saas_app'
DB_USER = 'root'
DB_PASS = ''

# JWT
JWT_EXPIRY = 86400  # 24 hours

# Stripe (Optional)
STRIPE_SECRET_KEY = ''
STRIPE_PUBLISHABLE_KEY = ''
STRIPE_WEBHOOK_SECRET = ''

# Load local config if exists
try:
    from config_local import *
except ImportError:
    pass
