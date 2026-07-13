-- Obligation updates: comments with optional private-file attachments
-- (receipts, progress documentation) and an optional link to the completion
-- the update recorded.

CREATE TABLE obligation_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obligation_id INT NOT NULL,
  created_by_user_id INT DEFAULT NULL,
  comment TEXT DEFAULT NULL,
  private_file_id INT DEFAULT NULL,
  completion_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ocm_obligation FOREIGN KEY (obligation_id) REFERENCES obligations(id) ON DELETE CASCADE,
  CONSTRAINT fk_ocm_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ocm_file FOREIGN KEY (private_file_id) REFERENCES private_files(id) ON DELETE SET NULL,
  CONSTRAINT fk_ocm_completion FOREIGN KEY (completion_id) REFERENCES obligation_completions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_ocm_obligation ON obligation_comments(obligation_id);
CREATE INDEX idx_ocm_completion ON obligation_comments(completion_id);
