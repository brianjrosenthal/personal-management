-- Migration: Add task management tables
-- Date: 2025-01-06

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
