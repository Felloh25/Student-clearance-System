-- Student Clearance System schema
-- Use this file in phpMyAdmin or via MySQL command line.

DROP TABLE IF EXISTS clearance_items;
DROP TABLE IF EXISTS clearancerecord;
DROP TABLE IF EXISTS report;
DROP TABLE IF EXISTS student;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS department;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS clearances;
DROP TABLE IF EXISTS clearance_requests;
DROP TABLE IF EXISTS departments_backup;

CREATE TABLE IF NOT EXISTS department (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  code VARCHAR(32) DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('student','department','admin') NOT NULL DEFAULT 'student',
  full_name VARCHAR(180) NOT NULL,
  admission_no VARCHAR(60) DEFAULT NULL UNIQUE,
  email VARCHAR(150) DEFAULT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  school VARCHAR(120) DEFAULT NULL,
  course VARCHAR(120) DEFAULT NULL,
  gender VARCHAR(20) DEFAULT NULL,
  year_of_study TINYINT DEFAULT NULL,
  department_id INT DEFAULT NULL,
  profile_photo VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES department(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  AdmissionNo VARCHAR(60) NOT NULL UNIQUE,
  StudentName VARCHAR(180) DEFAULT NULL,
  Course VARCHAR(120) DEFAULT NULL,
  PhoneNumber VARCHAR(40) DEFAULT NULL,
  year_of_study TINYINT DEFAULT NULL,
  school VARCHAR(120) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clearancerecord (
  RecordID INT AUTO_INCREMENT PRIMARY KEY,
  AdmissionNo VARCHAR(60) NOT NULL,
  DepartmentID INT DEFAULT NULL,
  Reason VARCHAR(80) DEFAULT NULL,
  academic_year VARCHAR(40) DEFAULT NULL,
  request_comment TEXT,
  ClearanceStatus VARCHAR(20) NOT NULL DEFAULT 'Pending',
  ClearanceDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (DepartmentID) REFERENCES department(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clearance_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  clearancerecord_id INT NOT NULL,
  department_id INT NOT NULL,
  status ENUM('pending','cleared','rejected') NOT NULL DEFAULT 'pending',
  comment TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (clearancerecord_id) REFERENCES clearancerecord(RecordID) ON DELETE CASCADE,
  FOREIGN KEY (department_id) REFERENCES department(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS report (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('single','all') NOT NULL,
  generated_by INT DEFAULT NULL,
  generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  file_path VARCHAR(255) DEFAULT NULL,
  notes TEXT,
  FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_users_admission ON users(admission_no);
CREATE INDEX idx_clearancerecord_user ON clearancerecord(user_id);
CREATE INDEX idx_clearance_items_record ON clearance_items(clearancerecord_id);

INSERT INTO department (name, code, description) VALUES
('Library', 'LIB', 'Library clearance and book returns'),
('Finance', 'FIN', 'Fee clearance from finance office'),
('Registry', 'REG', 'Records and documentation clearance'),
('Hostel', 'HST', 'Hostel and accommodation clearance'),
('ICT', 'ICT', 'IT and student system clearance');

INSERT INTO users (username, password, role, full_name, admission_no, email, phone, school, course) VALUES
('admin', '$2y$10$wIgjuhdPN8HvkG2jwS74xuSN6fLppvk0oC.c7zza3ianBdpyb6.ku', 'admin', 'System Administrator', NULL, 'admin@example.com', NULL, NULL, NULL),
('student1', '$2y$10$Xd1Hny8rNWkRwhKKHnYx7Obd60iRfrDkB.51kBeWFe6rmG5XGweeW', 'student', 'Student One', '2026001', 'student1@example.com', '+256700000001', 'School of Computing', 'ICT');

INSERT INTO student (user_id, admission_no, year_of_study, school) VALUES
(2, '2026001', 2, 'School of Computing');

INSERT INTO users (username, password, role, full_name, email, department_id) VALUES
('finance1', '$2y$10$AelRG73mLFMk5.FPhR1HQeUo.HzyeQVSMZg3dsibBini9ukGy9vIS', 'department', 'Finance Clerk', 'finance@example.com', 2);

-- Sample login credentials:
-- Admin: username=admin / password=admin123
-- Student: username=2026001 / password=student123
-- Department: username=finance1 / password=dept123
