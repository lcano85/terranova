CREATE TABLE IF NOT EXISTS attendance_history (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 attendance_id INT NOT NULL,
 actor_user_id INT NULL,
 actor_name VARCHAR(255) NOT NULL,
 changes_json LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_attendance_history (attendance_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;