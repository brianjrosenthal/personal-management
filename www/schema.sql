-- Family Office application schema
-- Create DB then use it
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Users table with exact structure specified
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) DEFAULT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL DEFAULT '',
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  email_verify_token VARCHAR(64) DEFAULT NULL,
  email_verified_at DATETIME DEFAULT NULL,
  password_reset_token_hash CHAR(64) DEFAULT NULL,
  password_reset_expires_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_users_email_verify_token ON users(email_verify_token);
CREATE INDEX idx_users_pwreset_expires ON users(password_reset_expires_at);

-- Settings key-value table
CREATE TABLE settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(191) NOT NULL UNIQUE,
  value LONGTEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default settings
INSERT INTO settings (key_name, value) VALUES
  ('site_title', 'Family Office'),
  ('announcement', ''),
  ('timezone', 'America/New_York'),
  ('login_image_file_id', '')
ON DUPLICATE KEY UPDATE value=VALUES(value);

-- ===== Files Storage (DB-backed uploads) =====

-- Public files (profile photos)
CREATE TABLE public_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data LONGBLOB NOT NULL,
  content_type VARCHAR(100) DEFAULT NULL,
  original_filename VARCHAR(255) DEFAULT NULL,
  byte_length INT UNSIGNED DEFAULT NULL,
  sha256 CHAR(64) DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pf_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_pf_sha256 ON public_files(sha256);
CREATE INDEX idx_pf_created_by ON public_files(created_by_user_id);
CREATE INDEX idx_pf_created_at ON public_files(created_at);

-- Link columns (added via ALTER to avoid circular FK creation order)
ALTER TABLE users
  ADD COLUMN photo_public_file_id INT NULL;

ALTER TABLE users
  ADD CONSTRAINT fk_users_photo_public_file
    FOREIGN KEY (photo_public_file_id) REFERENCES public_files(id) ON DELETE SET NULL;

-- ===== Activity Log =====
CREATE TABLE activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NULL,
  action_type VARCHAR(64) NOT NULL,
  json_metadata LONGTEXT NULL,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_al_created_at ON activity_log(created_at);
CREATE INDEX idx_al_user_id ON activity_log(user_id);
CREATE INDEX idx_al_action_type ON activity_log(action_type);

-- ===== Email Log =====
CREATE TABLE emails_sent (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_by_user_id INT NULL,
  to_email VARCHAR(255) NOT NULL,
  to_name VARCHAR(255) DEFAULT NULL,
  cc_email VARCHAR(255) DEFAULT NULL,
  subject VARCHAR(500) NOT NULL,
  body_html LONGTEXT NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  error_message TEXT DEFAULT NULL,
  CONSTRAINT fk_emails_sent_user FOREIGN KEY (sent_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_emails_sent_created_at ON emails_sent(created_at);
CREATE INDEX idx_emails_sent_user_id ON emails_sent(sent_by_user_id);
CREATE INDEX idx_emails_sent_to_email ON emails_sent(to_email);
CREATE INDEX idx_emails_sent_success ON emails_sent(success);

-- ===== Private Files (Document Vault attachments) =====
-- Unlike public_files, these are served only through a login-checked download
-- endpoint and are never written to the on-disk cache.
CREATE TABLE private_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data LONGBLOB NOT NULL,
  content_type VARCHAR(100) DEFAULT NULL,
  original_filename VARCHAR(255) DEFAULT NULL,
  byte_length INT UNSIGNED DEFAULT NULL,
  sha256 CHAR(64) DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prf_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_prf_sha256 ON private_files(sha256);
CREATE INDEX idx_prf_created_by ON private_files(created_by_user_id);

-- ===== Household Assets =====
CREATE TABLE assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(100) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  purchase_date DATE DEFAULT NULL,
  purchase_price DECIMAL(12,2) DEFAULT NULL,
  warranty_info TEXT DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_assets_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_assets_name ON assets(name);
CREATE INDEX idx_assets_category ON assets(category);

-- An asset can have multiple photos (stored as public_files, like profile photos)
CREATE TABLE asset_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  public_file_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ap_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
  CONSTRAINT fk_ap_file FOREIGN KEY (public_file_id) REFERENCES public_files(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_ap_asset_id ON asset_photos(asset_id);

-- ===== Document Vault =====
CREATE TABLE documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  category VARCHAR(100) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  owner_user_id INT DEFAULT NULL COMMENT 'Family member this document belongs to',
  private_file_id INT DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Upload date',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_docs_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_docs_file FOREIGN KEY (private_file_id) REFERENCES private_files(id) ON DELETE SET NULL,
  CONSTRAINT fk_docs_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_docs_title ON documents(title);
CREATE INDEX idx_docs_category ON documents(category);
CREATE INDEX idx_docs_owner ON documents(owner_user_id);

-- ===== Contacts / Directory =====
CREATE TABLE contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  contact_type ENUM('person','organization') NOT NULL DEFAULT 'person',
  organization VARCHAR(255) DEFAULT NULL COMMENT 'Company/organization name (for a person: who they work for)',
  job_title VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  website VARCHAR(255) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_contacts_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_contacts_name ON contacts(name);

-- A contact may hold multiple categories/roles (e.g. both Plumber and Emergency Contact)
CREATE TABLE contact_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contact_id INT NOT NULL,
  category VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cc_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
  UNIQUE KEY unique_contact_category (contact_id, category)
) ENGINE=InnoDB;

CREATE INDEX idx_cc_category ON contact_categories(category);

-- ===== Insurance Policies =====
CREATE TABLE insurance_policies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(100) DEFAULT NULL,
  insurance_company VARCHAR(255) DEFAULT NULL,
  policy_number VARCHAR(100) DEFAULT NULL,
  effective_date DATE DEFAULT NULL,
  expiration_date DATE DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_policies_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_policies_name ON insurance_policies(name);
CREATE INDEX idx_policies_category ON insurance_policies(category);
CREATE INDEX idx_policies_expiration ON insurance_policies(expiration_date);

-- ===== Task Management =====

-- Tasks table
CREATE TABLE tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  instructions TEXT DEFAULT NULL,
  recurrence_type ENUM('one-off', 'annual', 'monthly', 'periodic') NOT NULL DEFAULT 'one-off',
  date DATE DEFAULT NULL,
  annual_date VARCHAR(5) DEFAULT NULL COMMENT 'MM-DD format for annual tasks',
  day_of_month TINYINT DEFAULT NULL COMMENT 'Day of month (1-31) for monthly tasks',
  periodic_days INT DEFAULT NULL COMMENT 'Number of days between occurrences for periodic tasks',
  completed TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Task is no longer needed',
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tasks_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_tasks_recurrence_type ON tasks(recurrence_type);
CREATE INDEX idx_tasks_completed ON tasks(completed);
CREATE INDEX idx_tasks_created_by ON tasks(created_by_user_id);

-- Task completions
CREATE TABLE task_completions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  completed_on DATE NOT NULL,
  completed_for VARCHAR(20) DEFAULT NULL COMMENT 'Year (YYYY), month (YYYY-MM), or date (YYYY-MM-DD) depending on recurrence type',
  notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tc_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_tc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_tc_task_id ON task_completions(task_id);
CREATE INDEX idx_tc_user_id ON task_completions(user_id);
CREATE INDEX idx_tc_completed_on ON task_completions(completed_on);
CREATE INDEX idx_tc_completed_for ON task_completions(completed_for);

-- Task responsible users (many-to-many)
CREATE TABLE task_responsible_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tru_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_tru_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_task_user (task_id, user_id)
) ENGINE=InnoDB;

CREATE INDEX idx_tru_task_id ON task_responsible_users(task_id);
CREATE INDEX idx_tru_user_id ON task_responsible_users(user_id);

-- Optional: seed an admin user (update email and password hash, then remove)
INSERT INTO users (first_name,last_name,email,password_hash,is_admin,email_verified_at)
VALUES ('Brian','Rosenthal','brian.rosenthal@gmail.com','$2y$10$9xH7Jq4v3o6s9k3y8i4rVOyWb0yBYZ5rW.0f9pZ.gG9K6l7lS6b2S',1,NOW());
