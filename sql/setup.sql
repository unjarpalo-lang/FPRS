-- Funeral Service Management System - MySQL schema
CREATE DATABASE IF NOT EXISTS fprs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fprs;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(200) NOT NULL,
  email VARCHAR(200) NOT NULL UNIQUE,
  phone VARCHAR(50),
  password VARCHAR(255) NOT NULL,
  role ENUM('client','director','admin') DEFAULT 'client',
  valid_id_path VARCHAR(255),
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE packages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  director_id INT DEFAULT NULL,
  parlor_id INT DEFAULT NULL,
  title VARCHAR(200) NOT NULL,
  inclusions TEXT,
  price DECIMAL(10,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE funerals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  director_id INT DEFAULT NULL,
  package_id INT DEFAULT NULL,
  funeral_date DATETIME,
  status ENUM('pending','scheduled','completed','cancelled') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL
);

CREATE TABLE inventory_resources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  director_id INT,
  parlor_id INT,
  item_type ENUM('flower','candle','plant','casket','equipment','other'),
  name VARCHAR(200),
  details TEXT,
  available_status ENUM('available','unavailable') DEFAULT 'available',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  message_text TEXT NOT NULL,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE shops (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT,
  name VARCHAR(200) NOT NULL,
  location VARCHAR(255),
  contact_info VARCHAR(255),
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE funeral_parlors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  director_id INT NOT NULL,
  name VARCHAR(200) NOT NULL,
  location VARCHAR(255) NOT NULL,
  contact_number VARCHAR(50) NOT NULL,
  license_path VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE CASCADE
);
