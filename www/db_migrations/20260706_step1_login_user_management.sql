-- Step 1: Login / user management cleanup.
-- No structural changes: no-login family members reuse the existing nullable
-- users.email and the empty-string password_hash default.

-- Re-theme the default site title left over from the template application.
UPDATE settings SET value = 'Family Office'
WHERE key_name = 'site_title' AND value = 'Change This Title';
