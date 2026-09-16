CREATE TABLE event_participants (
    id INT NOT NULL AUTO_INCREMENT,
    event_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    team_name VARCHAR(150) NULL,
    participant_type VARCHAR(20) NOT NULL DEFAULT 'team',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_participant_name (event_id, name),
    KEY idx_event_participants_event (event_id),
    CONSTRAINT fk_event_participants_event
        FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE event_participant_members (
    id INT NOT NULL AUTO_INCREMENT,
    participant_id INT NOT NULL,
    member_name VARCHAR(150) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_participant_members_participant (participant_id),
    CONSTRAINT fk_participant_members_participant
        FOREIGN KEY (participant_id) REFERENCES event_participants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE event_results (
    id INT NOT NULL AUTO_INCREMENT,
    event_id INT NOT NULL,
    participant_id INT NOT NULL,
    rank_position INT NULL,
    score DECIMAL(10,2) NULL,
    is_winner TINYINT(1) NOT NULL DEFAULT 0,
    notes VARCHAR(500) NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_result_participant (event_id, participant_id),
    KEY idx_event_results_event (event_id),
    CONSTRAINT fk_event_results_event
        FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_results_participant
        FOREIGN KEY (participant_id) REFERENCES event_participants (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_results_user
        FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
