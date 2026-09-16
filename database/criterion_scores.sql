CREATE TABLE event_criterion_scores (
    id INT NOT NULL AUTO_INCREMENT,
    event_id INT NOT NULL,
    criterion_id INT NOT NULL,
    participant_id INT NOT NULL,
    judge_id INT NOT NULL,
    score DECIMAL(10,2) NOT NULL,
    notes VARCHAR(500) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_criterion_score (event_id, criterion_id, participant_id, judge_id),
    KEY idx_criterion_scores_event (event_id),
    KEY idx_criterion_scores_judge (judge_id),
    CONSTRAINT fk_criterion_scores_event
        FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_criterion_scores_criterion
        FOREIGN KEY (criterion_id) REFERENCES criteria (id) ON DELETE CASCADE,
    CONSTRAINT fk_criterion_scores_participant
        FOREIGN KEY (participant_id) REFERENCES event_participants (id) ON DELETE CASCADE,
    CONSTRAINT fk_criterion_scores_judge
        FOREIGN KEY (judge_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
