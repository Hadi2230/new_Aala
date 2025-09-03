-- ===============================================
-- Database: aala_niroo (نسخه نهایی و کامل)
-- مناسب با کدهای پروژه (دارایی‌ها، گارانتی، نظرسنجی)
-- ===============================================

CREATE DATABASE IF NOT EXISTS aala_niroo
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_persian_ci;

USE aala_niroo;

-- انواع دارایی
CREATE TABLE IF NOT EXISTS asset_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  display_name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- فیلدهای سفارشی دارایی
CREATE TABLE IF NOT EXISTS asset_fields (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type_id INT,
  field_name VARCHAR(100),
  field_type ENUM('text','number','date','select','file'),
  is_required BOOLEAN DEFAULT false,
  options TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (type_id) REFERENCES asset_types(id) ON DELETE CASCADE,
  INDEX idx_type_id (type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- دارایی‌ها
CREATE TABLE IF NOT EXISTS assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  type_id INT NOT NULL,
  serial_number VARCHAR(255) UNIQUE,
  purchase_date DATE,
  status ENUM('فعال','غیرفعال','در حال تعمیر','آماده بهره‌برداری') DEFAULT 'فعال',
  brand VARCHAR(255),
  model VARCHAR(255),
  power_capacity VARCHAR(100),
  engine_type VARCHAR(100),
  consumable_type VARCHAR(100),
  engine_model VARCHAR(255),
  engine_serial VARCHAR(255),
  alternator_model VARCHAR(255),
  alternator_serial VARCHAR(255),
  device_model VARCHAR(255),
  device_serial VARCHAR(255),
  control_panel_model VARCHAR(255),
  breaker_model VARCHAR(255),
  fuel_tank_specs TEXT,
  battery VARCHAR(255),
  battery_charger VARCHAR(255),
  heater VARCHAR(255),
  oil_capacity VARCHAR(255),
  radiator_capacity VARCHAR(255),
  antifreeze VARCHAR(255),
  other_items TEXT,
  workshop_entry_date DATE,
  workshop_exit_date DATE,
  datasheet_link VARCHAR(500),
  engine_manual_link VARCHAR(500),
  alternator_manual_link VARCHAR(500),
  control_panel_manual_link VARCHAR(500),
  description TEXT,
  oil_filter_part VARCHAR(100),
  fuel_filter_part VARCHAR(100),
  water_fuel_filter_part VARCHAR(100),
  air_filter_part VARCHAR(100),
  water_filter_part VARCHAR(100),
  part_description TEXT,
  part_serial VARCHAR(255),
  part_register_date DATE,
  part_notes TEXT,
  supply_method VARCHAR(100),
  location VARCHAR(255),
  quantity INT DEFAULT 0,
  supplier_name VARCHAR(255),
  supplier_contact VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (type_id) REFERENCES asset_types(id) ON DELETE CASCADE,
  INDEX idx_type_id (type_id),
  INDEX idx_status (status),
  INDEX idx_serial (serial_number),
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- مشتریان
CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_type ENUM('حقیقی','حقوقی') NOT NULL DEFAULT 'حقیقی',
  full_name VARCHAR(255),
  phone VARCHAR(20),
  company VARCHAR(255),
  responsible_name VARCHAR(255),
  company_phone VARCHAR(20),
  responsible_phone VARCHAR(20),
  address TEXT NOT NULL,
  operator_name VARCHAR(255) NOT NULL,
  operator_phone VARCHAR(20) NOT NULL,
  notes TEXT,
  name VARCHAR(255)
    GENERATED ALWAYS AS (
      CASE WHEN customer_type='حقوقی' AND COALESCE(company,'')<>'' THEN company ELSE full_name END
    ) STORED,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_customer_type (customer_type),
  INDEX idx_full_name (full_name),
  INDEX idx_company (company),
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- کاربران
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(255),
  full_name VARCHAR(255),
  role ENUM('ادمین','کاربر عادی','اپراتور') DEFAULT 'کاربر عادی',
  is_active BOOLEAN DEFAULT true,
  last_login TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- کاربر پیش‌فرض admin/admin (اگر نبود)
INSERT INTO users (username, password, full_name, role)
SELECT 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدیر سیستم', 'ادمین'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='admin');

-- انتساب دارایی
CREATE TABLE IF NOT EXISTS asset_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  customer_id INT NOT NULL,
  assignment_date DATE,
  notes TEXT,
  assignment_status ENUM('فعال','خاتمه یافته','موقت') DEFAULT 'فعال',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  INDEX idx_asset_id (asset_id),
  INDEX idx_customer_id (customer_id),
  INDEX idx_assignment_date (assignment_date),
  INDEX idx_status (assignment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جزئیات انتساب
CREATE TABLE IF NOT EXISTS assignment_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT NOT NULL,
  installation_date DATE,
  delivery_person VARCHAR(255),
  installation_address TEXT,
  warranty_start_date DATE,
  warranty_end_date DATE,
  warranty_conditions TEXT,
  employer_name VARCHAR(255),
  employer_phone VARCHAR(20),
  recipient_name VARCHAR(255),
  recipient_phone VARCHAR(20),
  installer_name VARCHAR(255),
  installation_status ENUM('نصب شده','در حال نصب','لغو شده') NULL,
  installation_start_date DATE,
  installation_end_date DATE,
  temporary_delivery_date DATE,
  permanent_delivery_date DATE,
  first_service_date DATE,
  post_installation_commitments TEXT,
  notes TEXT,
  installation_photo VARCHAR(500),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (assignment_id) REFERENCES asset_assignments(id) ON DELETE CASCADE,
  INDEX idx_assignment_id (assignment_id),
  INDEX idx_installation_date (installation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- کارت گارانتی (با فیلدهای کامل)
CREATE TABLE IF NOT EXISTS guaranty_cards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  issue_date DATE NOT NULL,
  asset_id INT NOT NULL,
  coupler_company VARCHAR(255) NOT NULL,
  customer_id INT NOT NULL,
  guaranty_number VARCHAR(50) NOT NULL UNIQUE,
  alternator_model VARCHAR(255) NULL,
  alternator_serial VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- نظرسنجی‌ها
CREATE TABLE IF NOT EXISTS surveys (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS survey_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  survey_id INT NOT NULL,
  question_text TEXT NOT NULL,
  question_type ENUM('yes_no','rating','descriptive') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS survey_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  survey_id INT NOT NULL,
  customer_id INT NULL,
  asset_id INT NULL,
  started_by INT NOT NULL,
  status ENUM('in_progress','completed') DEFAULT 'in_progress',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_survey_id (survey_id),
  INDEX idx_customer_id (customer_id),
  INDEX idx_asset_id (asset_id),
  INDEX idx_started_by (started_by),
  FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS survey_responses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  survey_id INT NOT NULL,
  question_id INT NOT NULL,
  customer_id INT,
  asset_id INT,
  response_text TEXT NOT NULL,
  responded_by INT NOT NULL,
  submission_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
  FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_submission_id (submission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- یادداشت کاربران
CREATE TABLE IF NOT EXISTS user_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  note TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- لاگ سیستم
CREATE TABLE IF NOT EXISTS system_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(100) NOT NULL,
  description TEXT,
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_action (action),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- داده‌های اولیه
INSERT IGNORE INTO asset_types (name, display_name) VALUES
('generator', 'ژنراتور'),
('power_motor', 'موتور برق'),
('consumable', 'اقلام مصرفی');

INSERT IGNORE INTO users (username, password, full_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدیر سیستم', 'ادمین');