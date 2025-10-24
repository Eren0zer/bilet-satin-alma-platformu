<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function migrate(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN (\'user\',\'firm_admin\',\'admin\')),
        credit REAL NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS firms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS firm_admins (
        user_id INTEGER NOT NULL,
        firm_id INTEGER NOT NULL,
        PRIMARY KEY (user_id),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(firm_id) REFERENCES firms(id) ON DELETE CASCADE
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS trips (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        firm_id INTEGER NOT NULL,
        origin TEXT NOT NULL,
        destination TEXT NOT NULL,
        trip_date TEXT NOT NULL,
        departure_time TEXT NOT NULL,
        price REAL NOT NULL,
        seat_count INTEGER NOT NULL DEFAULT 40,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(firm_id) REFERENCES firms(id) ON DELETE CASCADE
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS tickets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        trip_id INTEGER NOT NULL,
        seat_no INTEGER NOT NULL,
        price_paid REAL NOT NULL,
        status TEXT NOT NULL CHECK(status IN (\'active\',\'canceled\')) DEFAULT \'active\',
        purchased_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        canceled_at TEXT,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(trip_id) REFERENCES trips(id) ON DELETE CASCADE,
        UNIQUE(trip_id, seat_no)
    )');

    // USERS
    $pdo->exec('CREATE TABLE IF NOT EXISTS users(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ("user","firm_admin","admin")),
        credit REAL NOT NULL DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    /* mevcut tabloda active kolonu yoksa ekle */
    $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $hasActive = false;
    foreach ($cols as $c){ if(($c['name'] ?? '') === 'active'){ $hasActive = true; break; } }
    if(!$hasActive){
    $pdo->exec('ALTER TABLE users ADD COLUMN active INTEGER NOT NULL DEFAULT 1');
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_active ON users(active)');


    $pdo->exec('CREATE TABLE IF NOT EXISTS coupons(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        percent INTEGER NOT NULL CHECK(percent>=1 AND percent<=90),
        usage_limit INTEGER NOT NULL DEFAULT 1,
        used_count INTEGER NOT NULL DEFAULT 0,
        expires_at TEXT,
        active INTEGER NOT NULL DEFAULT 1,
        firm_id INTEGER NULL,
        FOREIGN KEY(firm_id) REFERENCES firms(id) ON DELETE SET NULL
    )');

    /* Mevcut tabloda firm_id yoksa ekle (SQLite) */
    $cols = $pdo->query("PRAGMA table_info(coupons)")->fetchAll(PDO::FETCH_ASSOC);
    $hasFirmId = false;
    foreach ($cols as $c) { if (($c['name'] ?? '') === 'firm_id') { $hasFirmId = true; break; } }
    if (!$hasFirmId) {
    $pdo->exec('ALTER TABLE coupons ADD COLUMN firm_id INTEGER NULL');
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_coupons_firm ON coupons(firm_id)');


    $pdo->exec('CREATE TABLE IF NOT EXISTS wallet_tx (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        amount REAL NOT NULL,
        type TEXT NOT NULL CHECK(type IN (\'credit\',\'debit\')),
        reason TEXT,
        ref_id INTEGER,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS audit_log(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT NOT NULL,
        ip TEXT,
        ua TEXT,
        payload TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

}

function seed(PDO $pdo): void {
    $uCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($uCount === 0) {
        $stmt = $pdo->prepare('INSERT INTO users (name,email,password_hash,role,credit) VALUES (?,?,?,?,?)');
        $stmt->execute(['Admin','admin@local', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 0]);
        $stmt->execute(['Firma Yetkilisi','firma@local', password_hash('firma123', PASSWORD_DEFAULT), 'firm_admin', 0]);
        $stmt->execute(['Örnek Yolcu','user@local', password_hash('user123', PASSWORD_DEFAULT), 'user', 300]);

        $pdo->exec("INSERT INTO firms (name) VALUES ('Yıldız Tur')");
        $firmId = (int)$pdo->lastInsertId();
        $firmAdminId = (int)$pdo->query("SELECT id FROM users WHERE email='firma@local'")->fetchColumn();
        $pdo->prepare('INSERT INTO firm_admins (user_id, firm_id) VALUES (?,?)')->execute([$firmAdminId, $firmId]);

        $today = (new DateTime('now'))->format('Y-m-d');
        $tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');
        $st = $pdo->prepare('INSERT INTO trips (firm_id,origin,destination,trip_date,departure_time,price,seat_count) VALUES (?,?,?,?,?,?,?)');
        $st->execute([$firmId,'İstanbul','Ankara',$today,'20:30',450,45]);
        $st->execute([$firmId,'İstanbul','Bursa',$tomorrow,'09:00',280,40]);

        $pdo->exec("INSERT INTO coupons (code, percent, usage_limit, used_count, expires_at, active)
                    VALUES ('INDIRIM10', 10, 100, 0, date('now','+7 day'), 1)");
    }
}
?>
