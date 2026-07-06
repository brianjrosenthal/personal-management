-- Step 2: List modules — Household Assets, Document Vault, Contacts, Insurance Policies.
-- Adds private_files (login-checked document storage) and the module tables.

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

CREATE TABLE asset_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  public_file_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ap_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
  CONSTRAINT fk_ap_file FOREIGN KEY (public_file_id) REFERENCES public_files(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_ap_asset_id ON asset_photos(asset_id);

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

CREATE TABLE contact_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contact_id INT NOT NULL,
  category VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cc_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
  UNIQUE KEY unique_contact_category (contact_id, category)
) ENGINE=InnoDB;

CREATE INDEX idx_cc_category ON contact_categories(category);

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
