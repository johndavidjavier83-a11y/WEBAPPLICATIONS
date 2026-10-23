CREATE DATABASE IF NOT EXISTS bayanalert CHARACTER SET utf8mb4;
USE bayanalert;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  mobile VARCHAR(20), barangay VARCHAR(60) DEFAULT 'Talon',
  municipality VARCHAR(60) DEFAULT 'Amadeo, Cavite',
  purok VARCHAR(30), postal_code VARCHAR(10) DEFAULT '4119',
  password VARCHAR(255) NOT NULL,
  role ENUM('resident','admin') DEFAULT 'resident',   -- first registered user becomes admin
  pref_sms TINYINT DEFAULT 1, pref_status TINYINT DEFAULT 1, pref_announce TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ref_no VARCHAR(20) UNIQUE,
  type ENUM('complaint','disaster') NOT NULL,
  user_id INT NULL,
  category VARCHAR(60), severity VARCHAR(20),
  subject VARCHAR(200), description TEXT,
  barangay VARCHAR(60), municipality VARCHAR(60), purok VARCHAR(30), landmark VARCHAR(120),
  people_affected VARCHAR(80), need VARCHAR(60), contact VARCHAR(30),
  lat DECIMAL(10,7) NULL, lng DECIMAL(10,7) NULL,
  anonymous TINYINT DEFAULT 0, sms_updates TINYINT DEFAULT 1,
  priority ENUM('Low','Medium','High','Urgent') DEFAULT 'Medium',
  status ENUM('Pending','In Progress','Urgent','Resolved') DEFAULT 'Pending',
  assigned_to VARCHAR(80) DEFAULT 'Barangay Maintenance Team',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE report_photos (
  id INT AUTO_INCREMENT PRIMARY KEY, report_id INT NOT NULL, file VARCHAR(200),
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);

CREATE TABLE report_updates (            -- status timeline + resident comments
  id INT AUTO_INCREMENT PRIMARY KEY, report_id INT NOT NULL, user_id INT NULL,
  title VARCHAR(120), message TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);

CREATE TABLE announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('Advisory','Notice','Event','Health') NOT NULL,
  title VARCHAR(160), body TEXT, posted_by VARCHAR(80) DEFAULT 'Barangay Talon Council',
  created_at DATE
);

CREATE TABLE evac_centers (
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), capacity INT, distance_km DECIMAL(3,1),
  status VARCHAR(20) DEFAULT 'Open', pos_x TINYINT, pos_y TINYINT
);

CREATE TABLE hazards (                   -- pos_x/pos_y are % positions on the schematic map
  id INT AUTO_INCREMENT PRIMARY KEY, kind ENUM('flood','landslide') NOT NULL,
  label VARCHAR(100), pos_x TINYINT, pos_y TINYINT, size_w TINYINT, size_h TINYINT
);

INSERT INTO announcements (kind,title,body,created_at) VALUES
('Advisory','Heavy rainfall advisory for Amadeo, Cavite','Residents of low-lying puroks are advised to prepare emergency go-bags, secure important documents and monitor official channels for updates.','2026-10-13'),
('Notice','Garbage collection schedule update','Collection in Puroks 1 to 3 moves to Mondays and Thursdays. Please place segregated waste outside by 6:00 AM.','2026-10-11'),
('Event','Barangay disaster preparedness drill','Join the quarterly earthquake and fire drill at the Talon Covered Court. All households are encouraged to take part.','2026-10-24'),
('Health','Free medical check-up at the Barangay Hall','Basic check-ups and blood pressure monitoring for senior citizens and children. Bring a valid ID.','2026-10-09');

INSERT INTO evac_centers (name,capacity,distance_km,pos_x,pos_y) VALUES
('Talon Covered Court',300,0.4,30,38),('Talon Elementary School',500,0.9,80,52);

INSERT INTO hazards (kind,label,pos_x,pos_y,size_w,size_h) VALUES
('flood','Flood-prone · Purok 3-4',50,60,40,45),('landslide','Landslide-prone zone',16,80,24,26);
