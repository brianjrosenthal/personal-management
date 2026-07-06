-- Add login image setting
-- This allows admins to upload a custom image that appears on login and auth pages

INSERT INTO settings (key_name, value) VALUES ('login_image_file_id', '') 
ON DUPLICATE KEY UPDATE key_name = key_name;
