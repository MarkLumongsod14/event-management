CREATE TABLE event_files (
    id INT NOT NULL AUTO_INCREMENT,
    event_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    storage_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    is_cover TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_event_files_event (event_id),
    KEY idx_event_files_cover (event_id, is_cover),
    CONSTRAINT fk_event_files_event
        FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_files_user
        FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
