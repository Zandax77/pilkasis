<?php
$db_file = __DIR__ . '/database.sqlite';

try {
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create tables
    $db->exec("CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        is_used INTEGER DEFAULT 0
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS candidates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        chairman_name TEXT NOT NULL,
        vice_chairman_name TEXT NOT NULL,
        vision TEXT,
        mission TEXT,
        photo TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS votes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token_id INTEGER NOT NULL,
        candidate_id INTEGER NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(token_id) REFERENCES tokens(id),
        FOREIGN KEY(candidate_id) REFERENCES candidates(id)
    )");

    // Insert default admin if not exists
    $stmt = $db->query("SELECT COUNT(*) FROM admins WHERE username = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO admins (username, password) VALUES ('admin', '$admin_password')");
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
