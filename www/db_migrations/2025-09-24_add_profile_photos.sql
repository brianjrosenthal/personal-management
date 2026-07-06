-- Migration: Add profile photo support
-- Date: 2025-09-24
-- Description: Adds public_files table and photo_public_file_id column to users table

-- Create public_files table for storing profile photos and other public files
CREATE TABLE IF NOT EXISTS public_files (
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

-- Add indexes for performance
CREATE INDEX idx_pf_sha256 ON public_files(sha256);
CREATE INDEX idx_pf_created_by ON public_files(created_by_user_id);
CREATE INDEX idx_pf_created_at ON public_files(created_at);

-- Add photo_public_file_id column to users table
ALTER TABLE users 
ADD COLUMN photo_public_file_id INT NULL;

-- Add foreign key constraint
ALTER TABLE users 
ADD CONSTRAINT fk_users_photo_public_file
  FOREIGN KEY (photo_public_file_id) REFERENCES public_files(id) ON DELETE SET NULL;
