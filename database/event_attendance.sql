CREATE TABLE event_attendance (
    id INT NOT NULL AUTO_INCREMENT,
    event_id INT NOT NULL,
    student_id INT NOT NULL,
    checked_in_at TIMESTAMP NULL DEFAULT NULL,
    checked_out_at TIMESTAMP NULL DEFAULT NULL,
    status ENUM('present','late','absent','excused') NOT NULL DEFAULT 'present',
    scanned_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_student (event_id, student_id),
    KEY idx_event_attendance_event (event_id),
    KEY idx_event_attendance_student (student_id),
    CONSTRAINT fk_event_attendance_event
        FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_attendance_student
        FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_attendance_scanned_by
        FOREIGN KEY (scanned_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
