require 'sinatra'
require 'sinatra/reloader' if development?
require 'mysql2'
require 'json'
require 'securerandom'
require 'openssl'
require 'base64'
require 'erb'

# Configuration
configure do
  set :app_name, ENV['APP_NAME'] || 'SaaS Starter'
  set :app_url, ENV['APP_URL'] || 'http://localhost:4567'
  set :auth_secret, ENV['AUTH_SECRET'] || 'change-this-secret-key-min-32-characters'
  set :jwt_expiry, 86400

  enable :sessions
  set :session_secret, settings.auth_secret
  set :views, File.dirname(__FILE__) + '/views'
  set :public_folder, File.dirname(__FILE__) + '/public'
end

# Database connection
def db
  @db ||= Mysql2::Client.new(
    host: ENV['DB_HOST'] || 'localhost',
    username: ENV['DB_USER'] || 'root',
    password: ENV['DB_PASS'] || '',
    database: ENV['DB_NAME'] || 'saas_app',
    encoding: 'utf8mb4'
  )
end

# JWT Implementation
def base64_url_encode(data)
  Base64.urlsafe_encode64(data).tr('=', '')
end

def base64_url_decode(data)
  data += '=' * (4 - data.length % 4) % 4
  Base64.urlsafe_decode64(data)
end

def create_jwt(payload)
  header = { typ: 'JWT', alg: 'HS256' }
  payload[:iat] = Time.now.to_i
  payload[:exp] = Time.now.to_i + settings.jwt_expiry

  header_encoded = base64_url_encode(header.to_json)
  payload_encoded = base64_url_encode(payload.to_json)

  signature = OpenSSL::HMAC.digest('sha256', settings.auth_secret, "#{header_encoded}.#{payload_encoded}")
  signature_encoded = base64_url_encode(signature)

  "#{header_encoded}.#{payload_encoded}.#{signature_encoded}"
end

def verify_jwt(token)
  return nil unless token

  parts = token.split('.')
  return nil unless parts.length == 3

  header_encoded, payload_encoded, signature_encoded = parts

  expected_signature = OpenSSL::HMAC.digest('sha256', settings.auth_secret, "#{header_encoded}.#{payload_encoded}")
  actual_signature = base64_url_decode(signature_encoded)

  return nil unless secure_compare(expected_signature, actual_signature)

  payload = JSON.parse(base64_url_decode(payload_encoded), symbolize_names: true)
  return nil if payload[:exp] < Time.now.to_i

  payload
rescue
  nil
end

def secure_compare(a, b)
  return false if a.bytesize != b.bytesize
  l = a.unpack("C*")
  r = b.unpack("C*")
  result = 0
  l.zip(r) { |x, y| result |= x ^ y }
  result == 0
end

# Password hashing
def hash_password(password)
  salt = SecureRandom.hex(16)
  hash = OpenSSL::PKCS5.pbkdf2_hmac(password, salt, 100000, 32, 'sha256')
  "#{salt}$#{hash.unpack1('H*')}"
end

def verify_password(password, hash_string)
  salt, stored_hash = hash_string.split('$')
  hash = OpenSSL::PKCS5.pbkdf2_hmac(password, salt, 100000, 32, 'sha256')
  secure_compare(hash.unpack1('H*'), stored_hash)
rescue
  false
end

# Session helpers
def set_user_session(user_id)
  token = create_jwt({ user: { id: user_id } })
  response.set_cookie('session', {
    value: token,
    httponly: true,
    path: '/',
    max_age: settings.jwt_expiry
  })
end

def current_user
  return @current_user if defined?(@current_user)

  token = request.cookies['session']
  payload = verify_jwt(token)
  return nil unless payload && payload[:user]

  result = db.query("SELECT * FROM users WHERE id = #{payload[:user][:id].to_i} AND deleted_at IS NULL LIMIT 1")
  @current_user = result.first
end

def get_user_with_team(user_id)
  result = db.query(<<-SQL)
    SELECT u.*, tm.team_id, tm.role as team_role
    FROM users u
    LEFT JOIN team_members tm ON u.id = tm.user_id
    WHERE u.id = #{user_id.to_i}
    LIMIT 1
  SQL
  result.first
end

def require_login
  redirect '/sign-in' unless current_user
end

def require_guest
  redirect '/dashboard' if current_user
end

# Activity logging
def log_activity(team_id, user_id, action)
  return unless team_id

  ip = request.ip
  db.query("INSERT INTO activity_logs (team_id, user_id, action, ip_address) VALUES (#{team_id.to_i}, #{user_id.to_i}, '#{db.escape(action)}', '#{db.escape(ip)}')")
end

# Activity labels
ACTIVITY_LABELS = {
  'SIGN_UP' => 'Signed up',
  'SIGN_IN' => 'Signed in',
  'SIGN_OUT' => 'Signed out',
  'UPDATE_PASSWORD' => 'Updated password',
  'DELETE_ACCOUNT' => 'Deleted account',
  'UPDATE_ACCOUNT' => 'Updated account',
  'CREATE_TEAM' => 'Created team',
  'REMOVE_TEAM_MEMBER' => 'Removed team member',
  'INVITE_TEAM_MEMBER' => 'Invited team member',
  'ACCEPT_INVITATION' => 'Accepted invitation'
}.freeze

def activity_label(action)
  ACTIVITY_LABELS[action] || action
end

# Helpers
helpers do
  def h(text)
    ERB::Util.html_escape(text)
  end

  def app_name
    settings.app_name
  end

  def logged_in?
    !current_user.nil?
  end
end

# Routes
get '/' do
  redirect current_user ? '/dashboard' : '/sign-in'
end

get '/sign-in' do
  require_guest
  erb :'auth/sign_in', layout: :layout
end

post '/sign-in' do
  email = params[:email].to_s.strip
  password = params[:password].to_s

  result = db.query(<<-SQL)
    SELECT u.*, tm.team_id
    FROM users u
    LEFT JOIN team_members tm ON u.id = tm.user_id
    WHERE u.email = '#{db.escape(email)}' AND u.deleted_at IS NULL
    LIMIT 1
  SQL
  user = result.first

  if user && verify_password(password, user['password_hash'])
    set_user_session(user['id'])
    log_activity(user['team_id'], user['id'], 'SIGN_IN')
    redirect '/dashboard'
  else
    session[:flash_error] = 'Invalid email or password. Please try again.'
    erb :'auth/sign_in', layout: :layout, locals: { email: email }
  end
end

get '/sign-up' do
  require_guest
  erb :'auth/sign_up', layout: :layout, locals: { invite_id: params[:inviteId] }
end

post '/sign-up' do
  email = params[:email].to_s.strip
  password = params[:password].to_s
  invite_id = params[:inviteId]

  if password.length < 8
    session[:flash_error] = 'Password must be at least 8 characters.'
    return erb :'auth/sign_up', layout: :layout, locals: { email: email, invite_id: invite_id }
  end

  # Check if email exists
  result = db.query("SELECT id FROM users WHERE email = '#{db.escape(email)}' AND deleted_at IS NULL")
  if result.first
    session[:flash_error] = 'Failed to create user. Please try again.'
    return erb :'auth/sign_up', layout: :layout, locals: { email: email, invite_id: invite_id }
  end

  # Create user
  password_hash = hash_password(password)
  db.query("INSERT INTO users (email, password_hash, role) VALUES ('#{db.escape(email)}', '#{db.escape(password_hash)}', 'owner')")
  user_id = db.last_id

  team_id = nil
  user_role = 'owner'

  if invite_id && !invite_id.empty?
    result = db.query("SELECT * FROM invitations WHERE id = #{invite_id.to_i} AND email = '#{db.escape(email)}' AND status = 'pending' LIMIT 1")
    invitation = result.first

    if invitation
      team_id = invitation['team_id']
      user_role = invitation['role']
      db.query("UPDATE invitations SET status = 'accepted' WHERE id = #{invitation['id']}")
      log_activity(team_id, user_id, 'ACCEPT_INVITATION')
    else
      session[:flash_error] = 'Invalid or expired invitation.'
      return erb :'auth/sign_up', layout: :layout, locals: { email: email, invite_id: invite_id }
    end
  else
    # Create team
    db.query("INSERT INTO teams (name) VALUES ('#{db.escape(email)}\\'s Team')")
    team_id = db.last_id
    log_activity(team_id, user_id, 'CREATE_TEAM')
  end

  # Add to team
  db.query("INSERT INTO team_members (user_id, team_id, role) VALUES (#{user_id}, #{team_id}, '#{db.escape(user_role)}')")
  log_activity(team_id, user_id, 'SIGN_UP')

  set_user_session(user_id)
  redirect '/dashboard'
end

get '/sign-out' do
  if current_user
    user_with_team = get_user_with_team(current_user['id'])
    log_activity(user_with_team&.dig('team_id'), current_user['id'], 'SIGN_OUT')
  end
  response.delete_cookie('session', path: '/')
  redirect '/sign-in'
end

get '/dashboard' do
  require_login

  result = db.query(<<-SQL)
    SELECT t.* FROM teams t
    JOIN team_members tm ON t.id = tm.team_id
    WHERE tm.user_id = #{current_user['id']}
    LIMIT 1
  SQL
  team = result.first

  erb :'dashboard/index', layout: :layout, locals: { user: current_user, team: team }
end

get '/dashboard/general' do
  require_login
  erb :'dashboard/general', layout: :layout, locals: { user: current_user }
end

post '/dashboard/general' do
  require_login

  name = params[:name].to_s.strip
  email = params[:email].to_s.strip

  if email != current_user['email']
    result = db.query("SELECT id FROM users WHERE email = '#{db.escape(email)}' AND id != #{current_user['id']} AND deleted_at IS NULL")
    if result.first
      session[:flash_error] = 'Email is already in use.'
      redirect '/dashboard/general'
      return
    end
  end

  db.query("UPDATE users SET name = '#{db.escape(name)}', email = '#{db.escape(email)}' WHERE id = #{current_user['id']}")

  user_with_team = get_user_with_team(current_user['id'])
  log_activity(user_with_team&.dig('team_id'), current_user['id'], 'UPDATE_ACCOUNT')

  session[:flash_success] = 'Account updated successfully.'
  redirect '/dashboard/general'
end

get '/dashboard/security' do
  require_login
  erb :'dashboard/security', layout: :layout, locals: { user: current_user }
end

post '/dashboard/security' do
  require_login

  current_password = params[:currentPassword].to_s
  new_password = params[:newPassword].to_s
  confirm_password = params[:confirmPassword].to_s

  unless verify_password(current_password, current_user['password_hash'])
    session[:flash_error] = 'Current password is incorrect.'
    redirect '/dashboard/security'
    return
  end

  if current_password == new_password
    session[:flash_error] = 'New password must be different from the current password.'
    redirect '/dashboard/security'
    return
  end

  if new_password != confirm_password
    session[:flash_error] = 'New password and confirmation password do not match.'
    redirect '/dashboard/security'
    return
  end

  if new_password.length < 8
    session[:flash_error] = 'Password must be at least 8 characters.'
    redirect '/dashboard/security'
    return
  end

  db.query("UPDATE users SET password_hash = '#{db.escape(hash_password(new_password))}' WHERE id = #{current_user['id']}")

  user_with_team = get_user_with_team(current_user['id'])
  log_activity(user_with_team&.dig('team_id'), current_user['id'], 'UPDATE_PASSWORD')

  session[:flash_success] = 'Password updated successfully.'
  redirect '/dashboard/security'
end

post '/dashboard/delete-account' do
  require_login

  password = params[:password].to_s

  unless verify_password(password, current_user['password_hash'])
    session[:flash_error] = 'Incorrect password. Account deletion failed.'
    redirect '/dashboard/security'
    return
  end

  user_with_team = get_user_with_team(current_user['id'])
  log_activity(user_with_team&.dig('team_id'), current_user['id'], 'DELETE_ACCOUNT')

  db.query("UPDATE users SET deleted_at = NOW(), email = CONCAT(email, '-', id, '-deleted') WHERE id = #{current_user['id']}")

  if user_with_team&.dig('team_id')
    db.query("DELETE FROM team_members WHERE user_id = #{current_user['id']} AND team_id = #{user_with_team['team_id']}")
  end

  response.delete_cookie('session', path: '/')
  redirect '/sign-in'
end

get '/dashboard/activity' do
  require_login

  result = db.query(<<-SQL)
    SELECT al.*, u.name as user_name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.user_id = #{current_user['id']}
    ORDER BY al.timestamp DESC
    LIMIT 10
  SQL
  logs = result.to_a

  erb :'dashboard/activity', layout: :layout, locals: { user: current_user, logs: logs }
end

get '/dashboard/team' do
  require_login

  result = db.query(<<-SQL)
    SELECT t.* FROM teams t
    JOIN team_members tm ON t.id = tm.team_id
    WHERE tm.user_id = #{current_user['id']}
    LIMIT 1
  SQL
  team = result.first

  unless team
    session[:flash_error] = 'Team not found.'
    redirect '/dashboard'
    return
  end

  members_result = db.query(<<-SQL)
    SELECT tm.*, u.id as user_id, u.name, u.email
    FROM team_members tm
    JOIN users u ON tm.user_id = u.id
    WHERE tm.team_id = #{team['id']} AND u.deleted_at IS NULL
    ORDER BY tm.joined_at ASC
  SQL
  team['members'] = members_result.to_a

  invitations_result = db.query("SELECT * FROM invitations WHERE team_id = #{team['id']} ORDER BY invited_at DESC")
  invitations = invitations_result.to_a

  erb :'team/index', layout: :layout, locals: { user: current_user, team: team, invitations: invitations }
end

post '/dashboard/team/invite' do
  require_login

  user_with_team = get_user_with_team(current_user['id'])
  unless user_with_team&.dig('team_id')
    session[:flash_error] = 'User is not part of a team.'
    redirect '/dashboard'
    return
  end

  email = params[:email].to_s.strip
  role = %w[member owner].include?(params[:role]) ? params[:role] : 'member'

  # Check if already a member
  result = db.query(<<-SQL)
    SELECT u.id FROM users u
    JOIN team_members tm ON u.id = tm.user_id
    WHERE u.email = '#{db.escape(email)}' AND tm.team_id = #{user_with_team['team_id']}
  SQL
  if result.first
    session[:flash_error] = 'User is already a member of this team.'
    redirect '/dashboard/team'
    return
  end

  # Check if invitation exists
  result = db.query("SELECT id FROM invitations WHERE email = '#{db.escape(email)}' AND team_id = #{user_with_team['team_id']} AND status = 'pending'")
  if result.first
    session[:flash_error] = 'An invitation has already been sent to this email.'
    redirect '/dashboard/team'
    return
  end

  db.query("INSERT INTO invitations (team_id, email, role, invited_by, status) VALUES (#{user_with_team['team_id']}, '#{db.escape(email)}', '#{db.escape(role)}', #{current_user['id']}, 'pending')")
  log_activity(user_with_team['team_id'], current_user['id'], 'INVITE_TEAM_MEMBER')

  session[:flash_success] = 'Invitation sent successfully.'
  redirect '/dashboard/team'
end

post '/dashboard/team/remove-member' do
  require_login

  user_with_team = get_user_with_team(current_user['id'])
  unless user_with_team&.dig('team_id')
    session[:flash_error] = 'User is not part of a team.'
    redirect '/dashboard'
    return
  end

  member_id = params[:memberId].to_i
  db.query("DELETE FROM team_members WHERE id = #{member_id} AND team_id = #{user_with_team['team_id']}")
  log_activity(user_with_team['team_id'], current_user['id'], 'REMOVE_TEAM_MEMBER')

  session[:flash_success] = 'Team member removed successfully.'
  redirect '/dashboard/team'
end

get '/pricing' do
  erb :'dashboard/pricing', layout: :layout
end

# API routes
get '/api/user' do
  content_type :json
  return { error: 'Unauthorized' }.to_json unless current_user

  user_data = current_user.reject { |k, _| k == 'password_hash' }
  { user: user_data }.to_json
end

get '/api/team' do
  content_type :json
  return { error: 'Unauthorized' }.to_json unless current_user

  result = db.query(<<-SQL)
    SELECT t.* FROM teams t
    JOIN team_members tm ON t.id = tm.team_id
    WHERE tm.user_id = #{current_user['id']}
    LIMIT 1
  SQL
  team = result.first
  { team: team }.to_json
end

not_found do
  erb :'errors/404', layout: :layout
end
