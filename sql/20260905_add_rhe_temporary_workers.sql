ALTER TABLE rhe_payments
  MODIFY user_id INT NULL,
  ADD COLUMN temporary_first_name VARCHAR(100) NULL AFTER user_id,
  ADD COLUMN temporary_last_name VARCHAR(100) NULL AFTER temporary_first_name;