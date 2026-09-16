ALTER TABLE event_participants
    ADD COLUMN team_name VARCHAR(150) NULL AFTER name;

CREATE TABLE event_participant_members (
    id INT NOT NULL AUTO_INCREMENT,
    participant_id INT NOT NULL,
    member_name VARCHAR(150) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_participant_members_participant (participant_id),
    CONSTRAINT fk_participant_members_participant
        FOREIGN KEY (participant_id) REFERENCES event_participants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
