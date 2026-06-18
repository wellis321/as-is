CREATE TABLE IF NOT EXISTS as_is_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_as_is_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lanes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    as_is_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    color VARCHAR(7) NOT NULL DEFAULT '#e8f0fe',
    CONSTRAINT fk_lanes_as_is FOREIGN KEY (as_is_id) REFERENCES as_is_documents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS systems (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    category VARCHAR(100) NULL,
    hosting ENUM('saas','cloud','on-premise','hybrid','unknown') NOT NULL DEFAULT 'unknown',
    vendor VARCHAR(255) NULL,
    owner VARCHAR(255) NULL,
    contact VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_system_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS steps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    as_is_id INT UNSIGNED NOT NULL,
    lane_id INT UNSIGNED NOT NULL,
    step_number INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    step_type ENUM('start', 'task', 'decision', 'end') NOT NULL DEFAULT 'task',
    CONSTRAINT fk_steps_as_is FOREIGN KEY (as_is_id) REFERENCES as_is_documents (id) ON DELETE CASCADE,
    CONSTRAINT fk_steps_lane FOREIGN KEY (lane_id) REFERENCES lanes (id) ON DELETE CASCADE,
    UNIQUE KEY uniq_step_number_per_doc (as_is_id, step_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS step_systems (
    step_id INT UNSIGNED NOT NULL,
    system_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (step_id, system_id),
    CONSTRAINT fk_step_systems_step FOREIGN KEY (step_id) REFERENCES steps (id) ON DELETE CASCADE,
    CONSTRAINT fk_step_systems_system FOREIGN KEY (system_id) REFERENCES systems (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS step_connections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_step_id INT UNSIGNED NOT NULL,
    to_step_id INT UNSIGNED NOT NULL,
    label VARCHAR(100) NULL,
    CONSTRAINT fk_connections_from FOREIGN KEY (from_step_id) REFERENCES steps (id) ON DELETE CASCADE,
    CONSTRAINT fk_connections_to FOREIGN KEY (to_step_id) REFERENCES steps (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
