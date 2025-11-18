"""
SaaS Python/Flask Application
Designed for shared hosting environments
"""

from flask import Flask, render_template, request, redirect, url_for, flash, session, jsonify, make_response
from functools import wraps
import os
import hashlib
import hmac
import json
import base64
import time
import secrets
import mysql.connector
from mysql.connector import pooling
from datetime import datetime, timedelta

app = Flask(__name__)
app.secret_key = os.environ.get('AUTH_SECRET', 'change-this-secret-key-in-production')

# Configuration
config = {
    'APP_NAME': os.environ.get('APP_NAME', 'SaaS Starter'),
    'APP_URL': os.environ.get('APP_URL', 'http://localhost:5000'),
    'DB_HOST': os.environ.get('DB_HOST', 'localhost'),
    'DB_NAME': os.environ.get('DB_NAME', 'saas_app'),
    'DB_USER': os.environ.get('DB_USER', 'root'),
    'DB_PASS': os.environ.get('DB_PASS', ''),
    'JWT_EXPIRY': 86400,  # 24 hours
    'STRIPE_SECRET_KEY': os.environ.get('STRIPE_SECRET_KEY', ''),
    'STRIPE_WEBHOOK_SECRET': os.environ.get('STRIPE_WEBHOOK_SECRET', ''),
}

# Database connection pool
db_config = {
    'host': config['DB_HOST'],
    'database': config['DB_NAME'],
    'user': config['DB_USER'],
    'password': config['DB_PASS'],
    'charset': 'utf8mb4',
    'collation': 'utf8mb4_unicode_ci',
}

try:
    connection_pool = pooling.MySQLConnectionPool(
        pool_name="saas_pool",
        pool_size=5,
        **db_config
    )
except:
    connection_pool = None

def get_db():
    """Get database connection from pool"""
    if connection_pool:
        return connection_pool.get_connection()
    return mysql.connector.connect(**db_config)

# JWT Implementation
def base64_url_encode(data):
    return base64.urlsafe_b64encode(data).rstrip(b'=').decode('utf-8')

def base64_url_decode(data):
    padding = 4 - len(data) % 4
    if padding != 4:
        data += '=' * padding
    return base64.urlsafe_b64decode(data)

def create_jwt(payload):
    """Create JWT token"""
    header = {'typ': 'JWT', 'alg': 'HS256'}
    payload['iat'] = int(time.time())
    payload['exp'] = int(time.time()) + config['JWT_EXPIRY']

    header_encoded = base64_url_encode(json.dumps(header).encode())
    payload_encoded = base64_url_encode(json.dumps(payload).encode())

    signature = hmac.new(
        app.secret_key.encode(),
        f"{header_encoded}.{payload_encoded}".encode(),
        hashlib.sha256
    ).digest()
    signature_encoded = base64_url_encode(signature)

    return f"{header_encoded}.{payload_encoded}.{signature_encoded}"

def verify_jwt(token):
    """Verify and decode JWT token"""
    try:
        parts = token.split('.')
        if len(parts) != 3:
            return None

        header_encoded, payload_encoded, signature_encoded = parts

        # Verify signature
        expected_signature = hmac.new(
            app.secret_key.encode(),
            f"{header_encoded}.{payload_encoded}".encode(),
            hashlib.sha256
        ).digest()

        actual_signature = base64_url_decode(signature_encoded)

        if not hmac.compare_digest(expected_signature, actual_signature):
            return None

        payload = json.loads(base64_url_decode(payload_encoded))

        # Check expiration
        if payload.get('exp', 0) < time.time():
            return None

        return payload
    except:
        return None

# Password hashing
def hash_password(password):
    """Hash password using SHA256 with salt"""
    salt = secrets.token_hex(16)
    hash_obj = hashlib.pbkdf2_hmac('sha256', password.encode(), salt.encode(), 100000)
    return f"{salt}${hash_obj.hex()}"

def verify_password(password, hash_string):
    """Verify password against hash"""
    try:
        salt, stored_hash = hash_string.split('$')
        hash_obj = hashlib.pbkdf2_hmac('sha256', password.encode(), salt.encode(), 100000)
        return hmac.compare_digest(hash_obj.hex(), stored_hash)
    except:
        return False

# Session management
def set_session(user_id):
    """Set user session cookie"""
    token = create_jwt({'user': {'id': user_id}})
    response = make_response(redirect(url_for('dashboard')))
    response.set_cookie('session', token, httponly=True, samesite='Lax', max_age=config['JWT_EXPIRY'])
    return response

def get_current_user():
    """Get current user from session"""
    token = request.cookies.get('session')
    if not token:
        return None

    payload = verify_jwt(token)
    if not payload or 'user' not in payload:
        return None

    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM users WHERE id = %s AND deleted_at IS NULL", (payload['user']['id'],))
    user = cursor.fetchone()
    cursor.close()
    conn.close()

    return user

def get_user_with_team(user_id):
    """Get user with team info"""
    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT u.*, tm.team_id, tm.role as team_role
        FROM users u
        LEFT JOIN team_members tm ON u.id = tm.user_id
        WHERE u.id = %s
        LIMIT 1
    """, (user_id,))
    user = cursor.fetchone()
    cursor.close()
    conn.close()
    return user

# Decorators
def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        user = get_current_user()
        if not user:
            return redirect(url_for('sign_in'))
        return f(*args, **kwargs)
    return decorated_function

def guest_only(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        user = get_current_user()
        if user:
            return redirect(url_for('dashboard'))
        return f(*args, **kwargs)
    return decorated_function

# Activity logging
def log_activity(team_id, user_id, action, ip_address=None):
    """Log user activity"""
    if not team_id:
        return

    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("""
        INSERT INTO activity_logs (team_id, user_id, action, ip_address)
        VALUES (%s, %s, %s, %s)
    """, (team_id, user_id, action, ip_address or request.remote_addr))
    conn.commit()
    cursor.close()
    conn.close()

# Context processor
@app.context_processor
def inject_globals():
    return {
        'app_name': config['APP_NAME'],
        'current_user': get_current_user()
    }

# Routes
@app.route('/')
def index():
    if get_current_user():
        return redirect(url_for('dashboard'))
    return redirect(url_for('sign_in'))

@app.route('/sign-in', methods=['GET', 'POST'])
@guest_only
def sign_in():
    if request.method == 'POST':
        email = request.form.get('email', '').strip()
        password = request.form.get('password', '')

        conn = get_db()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT u.*, tm.team_id
            FROM users u
            LEFT JOIN team_members tm ON u.id = tm.user_id
            WHERE u.email = %s AND u.deleted_at IS NULL
            LIMIT 1
        """, (email,))
        user = cursor.fetchone()
        cursor.close()
        conn.close()

        if not user or not verify_password(password, user['password_hash']):
            flash('Invalid email or password. Please try again.', 'error')
            return render_template('auth/sign_in.html', email=email)

        log_activity(user.get('team_id'), user['id'], 'SIGN_IN')
        return set_session(user['id'])

    return render_template('auth/sign_in.html')

@app.route('/sign-up', methods=['GET', 'POST'])
@guest_only
def sign_up():
    invite_id = request.args.get('inviteId')

    if request.method == 'POST':
        email = request.form.get('email', '').strip()
        password = request.form.get('password', '')
        invite_id = request.form.get('inviteId')

        if len(password) < 8:
            flash('Password must be at least 8 characters.', 'error')
            return render_template('auth/sign_up.html', email=email, invite_id=invite_id)

        conn = get_db()
        cursor = conn.cursor(dictionary=True)

        # Check if email exists
        cursor.execute("SELECT id FROM users WHERE email = %s AND deleted_at IS NULL", (email,))
        if cursor.fetchone():
            flash('Failed to create user. Please try again.', 'error')
            cursor.close()
            conn.close()
            return render_template('auth/sign_up.html', email=email, invite_id=invite_id)

        try:
            # Create user
            password_hash = hash_password(password)
            cursor.execute("""
                INSERT INTO users (email, password_hash, role)
                VALUES (%s, %s, 'owner')
            """, (email, password_hash))
            user_id = cursor.lastrowid

            team_id = None
            user_role = 'owner'

            # Handle invitation
            if invite_id:
                cursor.execute("""
                    SELECT * FROM invitations
                    WHERE id = %s AND email = %s AND status = 'pending'
                """, (invite_id, email))
                invitation = cursor.fetchone()

                if invitation:
                    team_id = invitation['team_id']
                    user_role = invitation['role']
                    cursor.execute("UPDATE invitations SET status = 'accepted' WHERE id = %s", (invitation['id'],))
                    log_activity(team_id, user_id, 'ACCEPT_INVITATION')
                else:
                    conn.rollback()
                    flash('Invalid or expired invitation.', 'error')
                    cursor.close()
                    conn.close()
                    return render_template('auth/sign_up.html', email=email, invite_id=invite_id)
            else:
                # Create team
                cursor.execute("INSERT INTO teams (name) VALUES (%s)", (f"{email}'s Team",))
                team_id = cursor.lastrowid
                log_activity(team_id, user_id, 'CREATE_TEAM')

            # Add user to team
            cursor.execute("""
                INSERT INTO team_members (user_id, team_id, role)
                VALUES (%s, %s, %s)
            """, (user_id, team_id, user_role))

            log_activity(team_id, user_id, 'SIGN_UP')
            conn.commit()

            cursor.close()
            conn.close()

            return set_session(user_id)

        except Exception as e:
            conn.rollback()
            flash('Failed to create account. Please try again.', 'error')
            cursor.close()
            conn.close()
            return render_template('auth/sign_up.html', email=email, invite_id=invite_id)

    return render_template('auth/sign_up.html', invite_id=invite_id)

@app.route('/sign-out')
def sign_out():
    user = get_current_user()
    if user:
        user_with_team = get_user_with_team(user['id'])
        log_activity(user_with_team.get('team_id'), user['id'], 'SIGN_OUT')

    response = make_response(redirect(url_for('sign_in')))
    response.delete_cookie('session')
    return response

@app.route('/dashboard')
@login_required
def dashboard():
    user = get_current_user()

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    # Get team
    cursor.execute("""
        SELECT t.* FROM teams t
        JOIN team_members tm ON t.id = tm.team_id
        WHERE tm.user_id = %s
        LIMIT 1
    """, (user['id'],))
    team = cursor.fetchone()

    cursor.close()
    conn.close()

    return render_template('dashboard/index.html', user=user, team=team)

@app.route('/dashboard/general', methods=['GET', 'POST'])
@login_required
def dashboard_general():
    user = get_current_user()

    if request.method == 'POST':
        name = request.form.get('name', '').strip()
        email = request.form.get('email', '').strip()

        conn = get_db()
        cursor = conn.cursor(dictionary=True)

        # Check if email is taken
        if email != user['email']:
            cursor.execute("SELECT id FROM users WHERE email = %s AND id != %s AND deleted_at IS NULL", (email, user['id']))
            if cursor.fetchone():
                flash('Email is already in use.', 'error')
                cursor.close()
                conn.close()
                return redirect(url_for('dashboard_general'))

        cursor.execute("UPDATE users SET name = %s, email = %s WHERE id = %s", (name, email, user['id']))
        conn.commit()

        user_with_team = get_user_with_team(user['id'])
        log_activity(user_with_team.get('team_id'), user['id'], 'UPDATE_ACCOUNT')

        cursor.close()
        conn.close()

        flash('Account updated successfully.', 'success')
        return redirect(url_for('dashboard_general'))

    return render_template('dashboard/general.html', user=user)

@app.route('/dashboard/security', methods=['GET', 'POST'])
@login_required
def dashboard_security():
    user = get_current_user()

    if request.method == 'POST':
        current_password = request.form.get('currentPassword', '')
        new_password = request.form.get('newPassword', '')
        confirm_password = request.form.get('confirmPassword', '')

        if not verify_password(current_password, user['password_hash']):
            flash('Current password is incorrect.', 'error')
            return redirect(url_for('dashboard_security'))

        if current_password == new_password:
            flash('New password must be different from the current password.', 'error')
            return redirect(url_for('dashboard_security'))

        if new_password != confirm_password:
            flash('New password and confirmation password do not match.', 'error')
            return redirect(url_for('dashboard_security'))

        if len(new_password) < 8:
            flash('Password must be at least 8 characters.', 'error')
            return redirect(url_for('dashboard_security'))

        conn = get_db()
        cursor = conn.cursor()
        cursor.execute("UPDATE users SET password_hash = %s WHERE id = %s", (hash_password(new_password), user['id']))
        conn.commit()

        user_with_team = get_user_with_team(user['id'])
        log_activity(user_with_team.get('team_id'), user['id'], 'UPDATE_PASSWORD')

        cursor.close()
        conn.close()

        flash('Password updated successfully.', 'success')
        return redirect(url_for('dashboard_security'))

    return render_template('dashboard/security.html', user=user)

@app.route('/dashboard/delete-account', methods=['POST'])
@login_required
def delete_account():
    user = get_current_user()
    password = request.form.get('password', '')

    if not verify_password(password, user['password_hash']):
        flash('Incorrect password. Account deletion failed.', 'error')
        return redirect(url_for('dashboard_security'))

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    user_with_team = get_user_with_team(user['id'])
    log_activity(user_with_team.get('team_id'), user['id'], 'DELETE_ACCOUNT')

    # Soft delete
    cursor.execute("""
        UPDATE users SET deleted_at = NOW(), email = CONCAT(email, '-', id, '-deleted')
        WHERE id = %s
    """, (user['id'],))

    # Remove from team
    if user_with_team.get('team_id'):
        cursor.execute("DELETE FROM team_members WHERE user_id = %s AND team_id = %s",
                      (user['id'], user_with_team['team_id']))

    conn.commit()
    cursor.close()
    conn.close()

    response = make_response(redirect(url_for('sign_in')))
    response.delete_cookie('session')
    return response

@app.route('/dashboard/activity')
@login_required
def dashboard_activity():
    user = get_current_user()

    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT al.*, u.name as user_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.user_id = %s
        ORDER BY al.timestamp DESC
        LIMIT 10
    """, (user['id'],))
    logs = cursor.fetchall()
    cursor.close()
    conn.close()

    return render_template('dashboard/activity.html', user=user, logs=logs)

@app.route('/dashboard/team', methods=['GET'])
@login_required
def dashboard_team():
    user = get_current_user()

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    # Get team with members
    cursor.execute("""
        SELECT t.* FROM teams t
        JOIN team_members tm ON t.id = tm.team_id
        WHERE tm.user_id = %s
        LIMIT 1
    """, (user['id'],))
    team = cursor.fetchone()

    if not team:
        flash('Team not found.', 'error')
        cursor.close()
        conn.close()
        return redirect(url_for('dashboard'))

    # Get members
    cursor.execute("""
        SELECT tm.*, u.id as user_id, u.name, u.email
        FROM team_members tm
        JOIN users u ON tm.user_id = u.id
        WHERE tm.team_id = %s AND u.deleted_at IS NULL
        ORDER BY tm.joined_at ASC
    """, (team['id'],))
    team['members'] = cursor.fetchall()

    # Get invitations
    cursor.execute("""
        SELECT * FROM invitations WHERE team_id = %s ORDER BY invited_at DESC
    """, (team['id'],))
    invitations = cursor.fetchall()

    cursor.close()
    conn.close()

    return render_template('team/index.html', user=user, team=team, invitations=invitations)

@app.route('/dashboard/team/invite', methods=['POST'])
@login_required
def team_invite():
    user = get_current_user()
    user_with_team = get_user_with_team(user['id'])

    if not user_with_team.get('team_id'):
        flash('User is not part of a team.', 'error')
        return redirect(url_for('dashboard'))

    email = request.form.get('email', '').strip()
    role = request.form.get('role', 'member')

    if role not in ['member', 'owner']:
        role = 'member'

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    # Check if already a member
    cursor.execute("""
        SELECT u.id FROM users u
        JOIN team_members tm ON u.id = tm.user_id
        WHERE u.email = %s AND tm.team_id = %s
    """, (email, user_with_team['team_id']))

    if cursor.fetchone():
        flash('User is already a member of this team.', 'error')
        cursor.close()
        conn.close()
        return redirect(url_for('dashboard_team'))

    # Check if invitation exists
    cursor.execute("""
        SELECT id FROM invitations
        WHERE email = %s AND team_id = %s AND status = 'pending'
    """, (email, user_with_team['team_id']))

    if cursor.fetchone():
        flash('An invitation has already been sent to this email.', 'error')
        cursor.close()
        conn.close()
        return redirect(url_for('dashboard_team'))

    # Create invitation
    cursor.execute("""
        INSERT INTO invitations (team_id, email, role, invited_by, status)
        VALUES (%s, %s, %s, %s, 'pending')
    """, (user_with_team['team_id'], email, role, user['id']))

    conn.commit()
    log_activity(user_with_team['team_id'], user['id'], 'INVITE_TEAM_MEMBER')

    cursor.close()
    conn.close()

    flash('Invitation sent successfully.', 'success')
    return redirect(url_for('dashboard_team'))

@app.route('/dashboard/team/remove-member', methods=['POST'])
@login_required
def team_remove_member():
    user = get_current_user()
    user_with_team = get_user_with_team(user['id'])

    if not user_with_team.get('team_id'):
        flash('User is not part of a team.', 'error')
        return redirect(url_for('dashboard'))

    member_id = request.form.get('memberId', type=int)

    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("DELETE FROM team_members WHERE id = %s AND team_id = %s",
                  (member_id, user_with_team['team_id']))
    conn.commit()

    log_activity(user_with_team['team_id'], user['id'], 'REMOVE_TEAM_MEMBER')

    cursor.close()
    conn.close()

    flash('Team member removed successfully.', 'success')
    return redirect(url_for('dashboard_team'))

@app.route('/pricing')
def pricing():
    return render_template('dashboard/pricing.html')

# API Routes
@app.route('/api/user')
def api_user():
    user = get_current_user()
    if not user:
        return jsonify({'error': 'Unauthorized'}), 401

    user_data = {k: v for k, v in user.items() if k != 'password_hash'}
    return jsonify({'user': user_data})

@app.route('/api/team')
def api_team():
    user = get_current_user()
    if not user:
        return jsonify({'error': 'Unauthorized'}), 401

    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT t.* FROM teams t
        JOIN team_members tm ON t.id = tm.team_id
        WHERE tm.user_id = %s
        LIMIT 1
    """, (user['id'],))
    team = cursor.fetchone()
    cursor.close()
    conn.close()

    return jsonify({'team': team})

# Error handlers
@app.errorhandler(404)
def not_found(e):
    return render_template('errors/404.html'), 404

# Activity type labels
ACTIVITY_LABELS = {
    'SIGN_UP': 'Signed up',
    'SIGN_IN': 'Signed in',
    'SIGN_OUT': 'Signed out',
    'UPDATE_PASSWORD': 'Updated password',
    'DELETE_ACCOUNT': 'Deleted account',
    'UPDATE_ACCOUNT': 'Updated account',
    'CREATE_TEAM': 'Created team',
    'REMOVE_TEAM_MEMBER': 'Removed team member',
    'INVITE_TEAM_MEMBER': 'Invited team member',
    'ACCEPT_INVITATION': 'Accepted invitation',
}

@app.template_filter('activity_label')
def activity_label(action):
    return ACTIVITY_LABELS.get(action, action)

if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)
