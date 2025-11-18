package main

import (
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"database/sql"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"html/template"
	"log"
	"net/http"
	"os"
	"strconv"
	"strings"
	"time"

	_ "github.com/go-sql-driver/mysql"
	"golang.org/x/crypto/pbkdf2"
)

// Config holds application configuration
type Config struct {
	AppName      string
	AppURL       string
	AuthSecret   string
	JWTExpiry    int64
	DBHost       string
	DBName       string
	DBUser       string
	DBPass       string
}

var (
	config    Config
	db        *sql.DB
	templates *template.Template
)

// User represents a user
type User struct {
	ID           int
	Name         sql.NullString
	Email        string
	PasswordHash string
	Role         string
	TeamID       sql.NullInt64
	TeamRole     sql.NullString
}

// Team represents a team
type Team struct {
	ID                   int
	Name                 string
	PlanName             sql.NullString
	SubscriptionStatus   sql.NullString
	Members              []TeamMember
}

// TeamMember represents a team member
type TeamMember struct {
	ID      int
	UserID  int
	Name    sql.NullString
	Email   string
	Role    string
}

// ActivityLog represents an activity log entry
type ActivityLog struct {
	ID        int
	Action    string
	IPAddress sql.NullString
	Timestamp time.Time
	UserName  sql.NullString
}

// Invitation represents an invitation
type Invitation struct {
	ID        int
	Email     string
	Role      string
	Status    string
	InvitedAt time.Time
}

func main() {
	// Load configuration
	config = Config{
		AppName:    getEnv("APP_NAME", "SaaS Starter"),
		AppURL:     getEnv("APP_URL", "http://localhost:8080"),
		AuthSecret: getEnv("AUTH_SECRET", "change-this-secret-key-min-32-characters"),
		JWTExpiry:  86400,
		DBHost:     getEnv("DB_HOST", "localhost"),
		DBName:     getEnv("DB_NAME", "saas_app"),
		DBUser:     getEnv("DB_USER", "root"),
		DBPass:     getEnv("DB_PASS", ""),
	}

	// Connect to database
	var err error
	dsn := fmt.Sprintf("%s:%s@tcp(%s:3306)/%s?parseTime=true", config.DBUser, config.DBPass, config.DBHost, config.DBName)
	db, err = sql.Open("mysql", dsn)
	if err != nil {
		log.Fatal("Database connection failed:", err)
	}
	defer db.Close()

	// Load templates
	templates = template.Must(template.New("").Funcs(template.FuncMap{
		"activityLabel": activityLabel,
		"upper":         strings.ToUpper,
		"capitalize":    capitalize,
	}).ParseGlob("templates/**/*.html"))

	// Static files
	fs := http.FileServer(http.Dir("static"))
	http.Handle("/css/", fs)
	http.Handle("/js/", fs)

	// Routes
	http.HandleFunc("/", indexHandler)
	http.HandleFunc("/sign-in", signInHandler)
	http.HandleFunc("/sign-up", signUpHandler)
	http.HandleFunc("/sign-out", signOutHandler)
	http.HandleFunc("/dashboard", authRequired(dashboardHandler))
	http.HandleFunc("/dashboard/general", authRequired(generalHandler))
	http.HandleFunc("/dashboard/security", authRequired(securityHandler))
	http.HandleFunc("/dashboard/delete-account", authRequired(deleteAccountHandler))
	http.HandleFunc("/dashboard/activity", authRequired(activityHandler))
	http.HandleFunc("/dashboard/team", authRequired(teamHandler))
	http.HandleFunc("/dashboard/team/invite", authRequired(inviteHandler))
	http.HandleFunc("/dashboard/team/remove-member", authRequired(removeMemberHandler))
	http.HandleFunc("/pricing", pricingHandler)
	http.HandleFunc("/api/user", apiUserHandler)
	http.HandleFunc("/api/team", apiTeamHandler)

	port := getEnv("PORT", "8080")
	log.Printf("Server starting on :%s", port)
	log.Fatal(http.ListenAndServe(":"+port, nil))
}

func getEnv(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}

// JWT functions
func createJWT(userID int) string {
	header := map[string]string{"typ": "JWT", "alg": "HS256"}
	payload := map[string]interface{}{
		"user": map[string]int{"id": userID},
		"iat":  time.Now().Unix(),
		"exp":  time.Now().Unix() + config.JWTExpiry,
	}

	headerJSON, _ := json.Marshal(header)
	payloadJSON, _ := json.Marshal(payload)

	headerEncoded := base64URLEncode(headerJSON)
	payloadEncoded := base64URLEncode(payloadJSON)

	signature := hmacSHA256(headerEncoded+"."+payloadEncoded, config.AuthSecret)
	signatureEncoded := base64URLEncode(signature)

	return headerEncoded + "." + payloadEncoded + "." + signatureEncoded
}

func verifyJWT(token string) (int, bool) {
	parts := strings.Split(token, ".")
	if len(parts) != 3 {
		return 0, false
	}

	headerEncoded, payloadEncoded, signatureEncoded := parts[0], parts[1], parts[2]

	expectedSignature := hmacSHA256(headerEncoded+"."+payloadEncoded, config.AuthSecret)
	actualSignature, err := base64URLDecode(signatureEncoded)
	if err != nil || !hmac.Equal(expectedSignature, actualSignature) {
		return 0, false
	}

	payloadJSON, err := base64URLDecode(payloadEncoded)
	if err != nil {
		return 0, false
	}

	var payload map[string]interface{}
	if err := json.Unmarshal(payloadJSON, &payload); err != nil {
		return 0, false
	}

	exp, ok := payload["exp"].(float64)
	if !ok || int64(exp) < time.Now().Unix() {
		return 0, false
	}

	user, ok := payload["user"].(map[string]interface{})
	if !ok {
		return 0, false
	}

	id, ok := user["id"].(float64)
	if !ok {
		return 0, false
	}

	return int(id), true
}

func base64URLEncode(data []byte) string {
	return strings.TrimRight(base64.URLEncoding.EncodeToString(data), "=")
}

func base64URLDecode(s string) ([]byte, error) {
	if l := len(s) % 4; l > 0 {
		s += strings.Repeat("=", 4-l)
	}
	return base64.URLEncoding.DecodeString(s)
}

func hmacSHA256(data, secret string) []byte {
	h := hmac.New(sha256.New, []byte(secret))
	h.Write([]byte(data))
	return h.Sum(nil)
}

// Password functions
func hashPassword(password string) string {
	salt := make([]byte, 16)
	rand.Read(salt)
	hash := pbkdf2.Key([]byte(password), salt, 100000, 32, sha256.New)
	return hex.EncodeToString(salt) + "$" + hex.EncodeToString(hash)
}

func verifyPassword(password, hashString string) bool {
	parts := strings.Split(hashString, "$")
	if len(parts) != 2 {
		return false
	}

	salt, _ := hex.DecodeString(parts[0])
	storedHash, _ := hex.DecodeString(parts[1])
	hash := pbkdf2.Key([]byte(password), salt, 100000, 32, sha256.New)

	return hmac.Equal(hash, storedHash)
}

// Session helpers
func setSession(w http.ResponseWriter, userID int) {
	token := createJWT(userID)
	http.SetCookie(w, &http.Cookie{
		Name:     "session",
		Value:    token,
		Path:     "/",
		HttpOnly: true,
		MaxAge:   int(config.JWTExpiry),
		SameSite: http.SameSiteLaxMode,
	})
}

func getCurrentUser(r *http.Request) *User {
	cookie, err := r.Cookie("session")
	if err != nil {
		return nil
	}

	userID, valid := verifyJWT(cookie.Value)
	if !valid {
		return nil
	}

	var user User
	err = db.QueryRow(`
		SELECT id, name, email, password_hash, role
		FROM users WHERE id = ? AND deleted_at IS NULL
	`, userID).Scan(&user.ID, &user.Name, &user.Email, &user.PasswordHash, &user.Role)

	if err != nil {
		return nil
	}

	return &user
}

func getUserWithTeam(userID int) *User {
	var user User
	err := db.QueryRow(`
		SELECT u.id, u.name, u.email, u.password_hash, u.role, tm.team_id, tm.role
		FROM users u
		LEFT JOIN team_members tm ON u.id = tm.user_id
		WHERE u.id = ? LIMIT 1
	`, userID).Scan(&user.ID, &user.Name, &user.Email, &user.PasswordHash, &user.Role, &user.TeamID, &user.TeamRole)

	if err != nil {
		return nil
	}
	return &user
}

func authRequired(handler http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if user := getCurrentUser(r); user == nil {
			http.Redirect(w, r, "/sign-in", http.StatusFound)
			return
		}
		handler(w, r)
	}
}

// Activity logging
func logActivity(teamID int, userID int, action string, r *http.Request) {
	if teamID == 0 {
		return
	}
	ip := r.RemoteAddr
	db.Exec("INSERT INTO activity_logs (team_id, user_id, action, ip_address) VALUES (?, ?, ?, ?)",
		teamID, userID, action, ip)
}

// Template helpers
func activityLabel(action string) string {
	labels := map[string]string{
		"SIGN_UP":            "Signed up",
		"SIGN_IN":            "Signed in",
		"SIGN_OUT":           "Signed out",
		"UPDATE_PASSWORD":    "Updated password",
		"DELETE_ACCOUNT":     "Deleted account",
		"UPDATE_ACCOUNT":     "Updated account",
		"CREATE_TEAM":        "Created team",
		"REMOVE_TEAM_MEMBER": "Removed team member",
		"INVITE_TEAM_MEMBER": "Invited team member",
		"ACCEPT_INVITATION":  "Accepted invitation",
	}
	if label, ok := labels[action]; ok {
		return label
	}
	return action
}

func capitalize(s string) string {
	if len(s) == 0 {
		return s
	}
	return strings.ToUpper(s[:1]) + strings.ToLower(s[1:])
}

// Handlers
func indexHandler(w http.ResponseWriter, r *http.Request) {
	if getCurrentUser(r) != nil {
		http.Redirect(w, r, "/dashboard", http.StatusFound)
	} else {
		http.Redirect(w, r, "/sign-in", http.StatusFound)
	}
}

func signInHandler(w http.ResponseWriter, r *http.Request) {
	if getCurrentUser(r) != nil {
		http.Redirect(w, r, "/dashboard", http.StatusFound)
		return
	}

	data := map[string]interface{}{
		"AppName": config.AppName,
		"Title":   "Sign In",
	}

	if r.Method == "POST" {
		email := strings.TrimSpace(r.FormValue("email"))
		password := r.FormValue("password")

		var user User
		var teamID sql.NullInt64
		err := db.QueryRow(`
			SELECT u.id, u.password_hash, tm.team_id
			FROM users u
			LEFT JOIN team_members tm ON u.id = tm.user_id
			WHERE u.email = ? AND u.deleted_at IS NULL LIMIT 1
		`, email).Scan(&user.ID, &user.PasswordHash, &teamID)

		if err != nil || !verifyPassword(password, user.PasswordHash) {
			data["Error"] = "Invalid email or password. Please try again."
			data["Email"] = email
			templates.ExecuteTemplate(w, "sign_in.html", data)
			return
		}

		setSession(w, user.ID)
		if teamID.Valid {
			logActivity(int(teamID.Int64), user.ID, "SIGN_IN", r)
		}
		http.Redirect(w, r, "/dashboard", http.StatusFound)
		return
	}

	templates.ExecuteTemplate(w, "sign_in.html", data)
}

func signUpHandler(w http.ResponseWriter, r *http.Request) {
	if getCurrentUser(r) != nil {
		http.Redirect(w, r, "/dashboard", http.StatusFound)
		return
	}

	data := map[string]interface{}{
		"AppName":  config.AppName,
		"Title":    "Sign Up",
		"InviteID": r.URL.Query().Get("inviteId"),
	}

	if r.Method == "POST" {
		email := strings.TrimSpace(r.FormValue("email"))
		password := r.FormValue("password")
		inviteID := r.FormValue("inviteId")

		if len(password) < 8 {
			data["Error"] = "Password must be at least 8 characters."
			data["Email"] = email
			templates.ExecuteTemplate(w, "sign_up.html", data)
			return
		}

		// Check if email exists
		var exists int
		db.QueryRow("SELECT 1 FROM users WHERE email = ? AND deleted_at IS NULL", email).Scan(&exists)
		if exists == 1 {
			data["Error"] = "Failed to create user. Please try again."
			data["Email"] = email
			templates.ExecuteTemplate(w, "sign_up.html", data)
			return
		}

		// Create user
		passwordHash := hashPassword(password)
		result, err := db.Exec("INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'owner')", email, passwordHash)
		if err != nil {
			data["Error"] = "Failed to create user. Please try again."
			templates.ExecuteTemplate(w, "sign_up.html", data)
			return
		}

		userID64, _ := result.LastInsertId()
		userID := int(userID64)

		var teamID int
		userRole := "owner"

		if inviteID != "" {
			var invitation struct {
				ID     int
				TeamID int
				Role   string
			}
			err := db.QueryRow(`
				SELECT id, team_id, role FROM invitations
				WHERE id = ? AND email = ? AND status = 'pending'
			`, inviteID, email).Scan(&invitation.ID, &invitation.TeamID, &invitation.Role)

			if err == nil {
				teamID = invitation.TeamID
				userRole = invitation.Role
				db.Exec("UPDATE invitations SET status = 'accepted' WHERE id = ?", invitation.ID)
				logActivity(teamID, userID, "ACCEPT_INVITATION", r)
			} else {
				data["Error"] = "Invalid or expired invitation."
				templates.ExecuteTemplate(w, "sign_up.html", data)
				return
			}
		} else {
			result, _ := db.Exec("INSERT INTO teams (name) VALUES (?)", email+"'s Team")
			teamID64, _ := result.LastInsertId()
			teamID = int(teamID64)
			logActivity(teamID, userID, "CREATE_TEAM", r)
		}

		db.Exec("INSERT INTO team_members (user_id, team_id, role) VALUES (?, ?, ?)", userID, teamID, userRole)
		logActivity(teamID, userID, "SIGN_UP", r)

		setSession(w, userID)
		http.Redirect(w, r, "/dashboard", http.StatusFound)
		return
	}

	templates.ExecuteTemplate(w, "sign_up.html", data)
}

func signOutHandler(w http.ResponseWriter, r *http.Request) {
	if user := getCurrentUser(r); user != nil {
		if userWithTeam := getUserWithTeam(user.ID); userWithTeam != nil && userWithTeam.TeamID.Valid {
			logActivity(int(userWithTeam.TeamID.Int64), user.ID, "SIGN_OUT", r)
		}
	}

	http.SetCookie(w, &http.Cookie{
		Name:   "session",
		Value:  "",
		Path:   "/",
		MaxAge: -1,
	})
	http.Redirect(w, r, "/sign-in", http.StatusFound)
}

func dashboardHandler(w http.ResponseWriter, r *http.Request) {
	user := getCurrentUser(r)

	var team *Team
	row := db.QueryRow(`
		SELECT t.id, t.name, t.plan_name FROM teams t
		JOIN team_members tm ON t.id = tm.team_id
		WHERE tm.user_id = ? LIMIT 1
	`, user.ID)

	var t Team
	if err := row.Scan(&t.ID, &t.Name, &t.PlanName); err == nil {
		team = &t
	}

	data := map[string]interface{}{
		"AppName":     config.AppName,
		"Title":       "Dashboard",
		"CurrentUser": user,
		"User":        user,
		"Team":        team,
	}

	templates.ExecuteTemplate(w, "dashboard.html", data)
}

func generalHandler(w http.ResponseWriter, r *http.Request) {
	user := getCurrentUser(r)

	if r.Method == "POST" {
		name := strings.TrimSpace(r.FormValue("name"))
		email := strings.TrimSpace(r.FormValue("email"))

		if email != user.Email {
			var exists int
			db.QueryRow("SELECT 1 FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL", email, user.ID).Scan(&exists)
			if exists == 1 {
				http.Redirect(w, r, "/dashboard/general?error=email", http.StatusFound)
				return
			}
		}

		db.Exec("UPDATE users SET name = ?, email = ? WHERE id = ?", name, email, user.ID)

		if userWithTeam := getUserWithTeam(user.ID); userWithTeam != nil && userWithTeam.TeamID.Valid {
			logActivity(int(userWithTeam.TeamID.Int64), user.ID, "UPDATE_ACCOUNT", r)
		}

		http.Redirect(w, r, "/dashboard/general?success=1", http.StatusFound)
		return
	}

	data := map[string]interface{}{
		"AppName":     config.AppName,
		"Title":       "General Settings",
		"CurrentUser": user,
		"User":        user,
		"Success":     r.URL.Query().Get("success") == "1",
	}

	templates.ExecuteTemplate(w, "general.html", data)
}

func securityHandler(w http.ResponseWriter, r *http.Request) {
	user := getCurrentUser(r)

	if r.Method == "POST" {
		currentPassword := r.FormValue("currentPassword")
		newPassword := r.FormValue("newPassword")
		confirmPassword := r.FormValue("confirmPassword")

		if !verifyPassword(currentPassword, user.PasswordHash) {
			http.Redirect(w, r, "/dashboard/security?error=current", http.StatusFound)
			return
		}

		if currentPassword == newPassword {
			http.Redirect(w, r, "/dashboard/security?error=same", http.StatusFound)
			return
		}

		if newPassword != confirmPassword {
			http.Redirect(w, r, "/dashboard/security?error=match", http.StatusFound)
			return
		}

		if len(newPassword) < 8 {
			http.Redirect(w, r, "/dashboard/security?error=length", http.StatusFound)
			return
		}

		db.Exec("UPDATE users SET password_hash = ? WHERE id = ?", hashPassword(newPassword), user.ID)

		if userWithTeam := getUserWithTeam(user.ID); userWithTeam != nil && userWithTeam.TeamID.Valid {
			logActivity(int(userWithTeam.TeamID.Int64), user.ID, "UPDATE_PASSWORD", r)
		}

		http.Redirect(w, r, "/dashboard/security?success=1", http.StatusFound)
		return
	}

	data := map[string]interface{}{
		"AppName":     config.AppName,
		"Title":       "Security Settings",
		"CurrentUser": user,
		"User":        user,
		"Success":     r.URL.Query().Get("success") == "1",
	}

	templates.ExecuteTemplate(w, "security.html", data)
}

func deleteAccountHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Redirect(w, r, "/dashboard/security", http.StatusFound)
		return
	}

	user := getCurrentUser(r)
	password := r.FormValue("password")

	if !verifyPassword(password, user.PasswordHash) {
		http.Redirect(w, r, "/dashboard/security?error=delete", http.StatusFound)
		return
	}

	userWithTeam := getUserWithTeam(user.ID)
	if userWithTeam != nil && userWithTeam.TeamID.Valid {
		logActivity(int(userWithTeam.TeamID.Int64), user.ID, "DELETE_ACCOUNT", r)
		db.Exec("DELETE FROM team_members WHERE user_id = ? AND team_id = ?", user.ID, userWithTeam.TeamID.Int64)
	}

	db.Exec("UPDATE users SET deleted_at = NOW(), email = CONCAT(email, '-', id, '-deleted') WHERE id = ?", user.ID)

	http.SetCookie(w, &http.Cookie{Name: "session", Value: "", Path: "/", MaxAge: -1})
	http.Redirect(w, r, "/sign-in", http.StatusFound)
}

func activityHandler(w http.ResponseWriter, r *http.Request) {
	user := getCurrentUser(r)

	rows, _ := db.Query(`
		SELECT al.id, al.action, al.ip_address, al.timestamp, u.name
		FROM activity_logs al
		LEFT JOIN users u ON al.user_id = u.id
		WHERE al.user_id = ?
		ORDER BY al.timestamp DESC LIMIT 10
	`, user.ID)
	defer rows.Close()

	var logs []ActivityLog
	for rows.Next() {
		var log ActivityLog
		rows.Scan(&log.ID, &log.Action, &log.IPAddress, &log.Timestamp, &log.UserName)
		logs = append(logs, log)
	}

	data := map[string]interface{}{
		"AppName":     config.AppName,
		"Title":       "Activity Log",
		"CurrentUser": user,
		"User":        user,
		"Logs":        logs,
	}

	templates.ExecuteTemplate(w, "activity.html", data)
}

func teamHandler(w http.ResponseWriter, r *http.Request) {
	user := getCurrentUser(r)

	var team Team
	err := db.QueryRow(`
		SELECT t.id, t.name, t.plan_name, t.subscription_status FROM teams t
		JOIN team_members tm ON t.id = tm.team_id
		WHERE tm.user_id = ? LIMIT 1
	`, user.ID).Scan(&team.ID, &team.Name, &team.PlanName, &team.SubscriptionStatus)

	if err != nil {
		http.Redirect(w, r, "/dashboard", http.StatusFound)
		return
	}

	// Get members
	rows, _ := db.Query(`
		SELECT tm.id, u.id, u.name, u.email, tm.role
		FROM team_members tm
		JOIN users u ON tm.user_id = u.id
		WHERE tm.team_id = ? AND u.deleted_at IS NULL
		ORDER BY tm.joined_at ASC
	`, team.ID)
	defer rows.Close()

	for rows.Next() {
		var m TeamMember
		rows.Scan(&m.ID, &m.UserID, &m.Name, &m.Email, &m.Role)
		team.Members = append(team.Members, m)
	}

	// Get invitations
	invRows, _ := db.Query("SELECT id, email, role, status, invited_at FROM invitations WHERE team_id = ? ORDER BY invited_at DESC", team.ID)
	defer invRows.Close()

	var invitations []Invitation
	for invRows.Next() {
		var inv Invitation
		invRows.Scan(&inv.ID, &inv.Email, &inv.Role, &inv.Status, &inv.InvitedAt)
		invitations = append(invitations, inv)
	}

	data := map[string]interface{}{
		"AppName":     config.AppName,
		"Title":       "Team Settings",
		"CurrentUser": user,
		"User":        user,
		"Team":        team,
		"Invitations": invitations,
	}

	templates.ExecuteTemplate(w, "team.html", data)
}

func inviteHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Redirect(w, r, "/dashboard/team", http.StatusFound)
		return
	}

	user := getCurrentUser(r)
	userWithTeam := getUserWithTeam(user.ID)

	if userWithTeam == nil || !userWithTeam.TeamID.Valid {
		http.Redirect(w, r, "/dashboard", http.StatusFound)
		return
	}

	email := strings.TrimSpace(r.FormValue("email"))
	role := r.FormValue("role")
	if role != "member" && role != "owner" {
		role = "member"
	}

	teamID := int(userWithTeam.TeamID.Int64)

	// Check if already member
	var exists int
	db.QueryRow(`
		SELECT 1 FROM users u
		JOIN team_members tm ON u.id = tm.user_id
		WHERE u.email = ? AND tm.team_id = ?
	`, email, teamID).Scan(&exists)

	if exists == 1 {
		http.Redirect(w, r, "/dashboard/team?error=member", http.StatusFound)
		return
	}

	// Check existing invitation
	db.QueryRow("SELECT 1 FROM invitations WHERE email = ? AND team_id = ? AND status = 'pending'", email, teamID).Scan(&exists)
	if exists == 1 {
		http.Redirect(w, r, "/dashboard/team?error=invited", http.StatusFound)
		return
	}

	db.Exec("INSERT INTO invitations (team_id, email, role, invited_by, status) VALUES (?, ?, ?, ?, 'pending')",
		teamID, email, role, user.ID)
	logActivity(teamID, user.ID, "INVITE_TEAM_MEMBER", r)

	http.Redirect(w, r, "/dashboard/team?success=invited", http.StatusFound)
}

func removeMemberHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Redirect(w, r, "/dashboard/team", http.StatusFound)
		return
	}

	user := getCurrentUser(r)
	userWithTeam := getUserWithTeam(user.ID)

	if userWithTeam == nil || !userWithTeam.TeamID.Valid {
		http.Redirect(w, r, "/dashboard", http.StatusFound)
		return
	}

	memberID, _ := strconv.Atoi(r.FormValue("memberId"))
	teamID := int(userWithTeam.TeamID.Int64)

	db.Exec("DELETE FROM team_members WHERE id = ? AND team_id = ?", memberID, teamID)
	logActivity(teamID, user.ID, "REMOVE_TEAM_MEMBER", r)

	http.Redirect(w, r, "/dashboard/team?success=removed", http.StatusFound)
}

func pricingHandler(w http.ResponseWriter, r *http.Request) {
	data := map[string]interface{}{
		"AppName":     config.AppName,
		"Title":       "Pricing",
		"CurrentUser": getCurrentUser(r),
	}
	templates.ExecuteTemplate(w, "pricing.html", data)
}

func apiUserHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	user := getCurrentUser(r)
	if user == nil {
		w.WriteHeader(http.StatusUnauthorized)
		json.NewEncoder(w).Encode(map[string]string{"error": "Unauthorized"})
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{
		"user": map[string]interface{}{
			"id":    user.ID,
			"name":  user.Name.String,
			"email": user.Email,
			"role":  user.Role,
		},
	})
}

func apiTeamHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	user := getCurrentUser(r)
	if user == nil {
		w.WriteHeader(http.StatusUnauthorized)
		json.NewEncoder(w).Encode(map[string]string{"error": "Unauthorized"})
		return
	}

	var team struct {
		ID       int    `json:"id"`
		Name     string `json:"name"`
		PlanName string `json:"plan_name"`
	}

	err := db.QueryRow(`
		SELECT t.id, t.name, COALESCE(t.plan_name, '') FROM teams t
		JOIN team_members tm ON t.id = tm.team_id
		WHERE tm.user_id = ? LIMIT 1
	`, user.ID).Scan(&team.ID, &team.Name, &team.PlanName)

	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"team": nil})
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"team": team})
}
