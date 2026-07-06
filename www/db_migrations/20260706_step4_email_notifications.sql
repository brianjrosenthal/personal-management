-- Step 4: Email notifications — notification log + settings for the daily runner.

CREATE TABLE notification_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obligation_id INT DEFAULT NULL,
  recipient_user_id INT NOT NULL,
  notification_type ENUM('overdue','due_today','entered_window','weekly_summary') NOT NULL,
  notification_date DATE NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  email_address VARCHAR(255) DEFAULT NULL,
  delivery_status ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  error_message TEXT DEFAULT NULL,
  CONSTRAINT fk_nl_obligation FOREIGN KEY (obligation_id) REFERENCES obligations(id) ON DELETE CASCADE,
  CONSTRAINT fk_nl_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_nl_dedup ON notification_log(obligation_id, recipient_user_id, notification_type, notification_date);
CREATE INDEX idx_nl_date ON notification_log(notification_date);

INSERT INTO settings (key_name, value) VALUES
  ('site_base_url', 'https://familyoffice.brianrosenthal.org'),
  ('weekly_digest_enabled', '1')
ON DUPLICATE KEY UPDATE key_name = key_name;
