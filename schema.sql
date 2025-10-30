

-- 사용자
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nickname VARCHAR(80) NOT NULL,
  theme ENUM('light','dark') DEFAULT 'light',
  sound_on TINYINT(1) DEFAULT 1,
  role ENUM('user','admin') DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 기록(메모/사진)
CREATE TABLE IF NOT EXISTS records (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  date DATE NOT NULL,
  datetime DATETIME NOT NULL,
  text TEXT,
  img VARCHAR(512),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX(user_id, date), INDEX(user_id, datetime)
);

-- 알림
CREATE TABLE IF NOT EXISTS reminders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  label VARCHAR(40) NOT NULL,
  time CHAR(5) NOT NULL,         -- '14:00'
  days VARCHAR(64) NOT NULL,     -- 'mon,tue,...'
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX(user_id)
);

-- 고객센터 문의
CREATE TABLE IF NOT EXISTS support_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,              -- 비로그인도 접수 가능
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  category VARCHAR(40) NOT NULL,
  message TEXT NOT NULL,
  screenshot VARCHAR(512),
  status ENUM('접수','처리중','답변완료') DEFAULT '접수',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX(user_id), INDEX(created_at)
);

-- 앱 브랜딩(단일행)
CREATE TABLE IF NOT EXISTS app_branding (
  id TINYINT PRIMARY KEY,
  hero1 VARCHAR(512), hero2 VARCHAR(512), logo VARCHAR(512)
);
INSERT IGNORE INTO app_branding(id) VALUES (1);
