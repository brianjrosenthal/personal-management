-- Step 3: Recurring Obligations (replaces the old task system).

-- Drop the superseded task tables
DROP TABLE IF EXISTS task_responsible_users;
DROP TABLE IF EXISTS task_completions;
DROP TABLE IF EXISTS tasks;

-- ===== Recurring Obligations =====
-- The app's central concept (see docs/app-spec.md). Recurrence types:
--   every_n_days / every_n_weeks / every_n_months / every_n_years:
--     fixed schedule of occurrences anchored at anchor_date, repeating every
--     recurrence_interval of the type's unit
--   day_of_month:  every month on day_of_month (clamped to short months)
--   date_of_year:  every year on annual_month_day 'MM-DD' (Feb 29 clamps to 28)
--   after_completion: due recurrence_interval recurrence_unit after the last
--     completion (anchor_date optionally sets the first due date)
CREATE TABLE obligations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL COMMENT 'Instructions for completing the obligation',
  category VARCHAR(100) DEFAULT NULL,
  recurrence_type ENUM('every_n_days','every_n_weeks','every_n_months','every_n_years','day_of_month','date_of_year','after_completion') NOT NULL,
  recurrence_interval INT DEFAULT NULL COMMENT 'N for every_n_* and after_completion',
  recurrence_unit ENUM('days','weeks','months') DEFAULT NULL COMMENT 'Unit for after_completion',
  day_of_month TINYINT DEFAULT NULL COMMENT '1-31 for day_of_month',
  annual_month_day CHAR(5) DEFAULT NULL COMMENT 'MM-DD for date_of_year',
  anchor_date DATE DEFAULT NULL COMMENT 'Schedule start for every_n_*; optional first due date for after_completion',
  responsible_user_id INT DEFAULT NULL COMMENT 'NULL = unassigned (notifications fall back to admins)',
  applies_to_user_id INT DEFAULT NULL COMMENT 'NULL = entire family',
  reminder_lead_days INT NOT NULL DEFAULT 7 COMMENT 'Days before due date to start reminding',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_completed_on DATE DEFAULT NULL,
  next_due_on DATE DEFAULT NULL,
  created_by_user_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_obl_responsible FOREIGN KEY (responsible_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_obl_applies_to FOREIGN KEY (applies_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_obl_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_obl_next_due ON obligations(next_due_on);
CREATE INDEX idx_obl_active ON obligations(is_active);
CREATE INDEX idx_obl_responsible ON obligations(responsible_user_id);
CREATE INDEX idx_obl_category ON obligations(category);

-- Permanent completion history (never overwritten; rows may only be added or deleted)
CREATE TABLE obligation_completions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obligation_id INT NOT NULL,
  completed_by_user_id INT DEFAULT NULL,
  completed_on DATE NOT NULL,
  notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_oc_obligation FOREIGN KEY (obligation_id) REFERENCES obligations(id) ON DELETE CASCADE,
  CONSTRAINT fk_oc_user FOREIGN KEY (completed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_oc_obligation ON obligation_completions(obligation_id);
CREATE INDEX idx_oc_completed_on ON obligation_completions(completed_on);

-- Linked objects: the supporting records needed to complete an obligation
CREATE TABLE obligation_assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obligation_id INT NOT NULL,
  asset_id INT NOT NULL,
  CONSTRAINT fk_oa_obligation FOREIGN KEY (obligation_id) REFERENCES obligations(id) ON DELETE CASCADE,
  CONSTRAINT fk_oa_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
  UNIQUE KEY unique_obligation_asset (obligation_id, asset_id)
) ENGINE=InnoDB;

CREATE TABLE obligation_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obligation_id INT NOT NULL,
  document_id INT NOT NULL,
  CONSTRAINT fk_od_obligation FOREIGN KEY (obligation_id) REFERENCES obligations(id) ON DELETE CASCADE,
  CONSTRAINT fk_od_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  UNIQUE KEY unique_obligation_document (obligation_id, document_id)
) ENGINE=InnoDB;

CREATE TABLE obligation_policies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obligation_id INT NOT NULL,
  policy_id INT NOT NULL,
  CONSTRAINT fk_op_obligation FOREIGN KEY (obligation_id) REFERENCES obligations(id) ON DELETE CASCADE,
  CONSTRAINT fk_op_policy FOREIGN KEY (policy_id) REFERENCES insurance_policies(id) ON DELETE CASCADE,
  UNIQUE KEY unique_obligation_policy (obligation_id, policy_id)
) ENGINE=InnoDB;

CREATE TABLE obligation_contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obligation_id INT NOT NULL,
  contact_id INT NOT NULL,
  CONSTRAINT fk_ocn_obligation FOREIGN KEY (obligation_id) REFERENCES obligations(id) ON DELETE CASCADE,
  CONSTRAINT fk_ocn_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
  UNIQUE KEY unique_obligation_contact (obligation_id, contact_id)
) ENGINE=InnoDB;
