ALTER TABLE users
    MODIFY role ENUM('superadmin','admin','staff','judge','student') NOT NULL DEFAULT 'student';

CREATE TABLE event_judges (
    event_id INT NOT NULL,
    judge_id INT NOT NULL,
    assigned_by INT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id, judge_id),
    KEY idx_event_judges_judge (judge_id),
    CONSTRAINT fk_event_judges_event
        FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_judges_judge
        FOREIGN KEY (judge_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_judges_assigned_by
        FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
