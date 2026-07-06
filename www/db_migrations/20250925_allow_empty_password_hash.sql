-- Allow empty password hash for users who haven't set their password yet
-- This enables the email verification -> password setup flow

ALTER TABLE users MODIFY COLUMN password_hash VARCHAR(255) NOT NULL DEFAULT '';
