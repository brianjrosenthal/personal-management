-- Add a "does not repeat" recurrence type for one-time obligations.
-- anchor_date holds the due date; next_due_on becomes NULL once completed.

ALTER TABLE obligations
  MODIFY recurrence_type ENUM('does_not_repeat','every_n_days','every_n_weeks','every_n_months','every_n_years','day_of_month','date_of_year','after_completion') NOT NULL;

ALTER TABLE obligations
  MODIFY anchor_date DATE DEFAULT NULL COMMENT 'Due date for does_not_repeat; schedule start for every_n_*; optional first due date for after_completion';
