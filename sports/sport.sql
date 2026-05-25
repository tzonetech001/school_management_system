-- Sports and Games Tables

-- Game types table
CREATE TABLE IF NOT EXISTS game_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    game_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tournament stages table
CREATE TABLE IF NOT EXISTS tournament_stages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    stage_name VARCHAR(50) NOT NULL UNIQUE,
    stage_order INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Teams table
CREATE TABLE IF NOT EXISTS teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_name VARCHAR(100) NOT NULL,
    team_type ENUM('Form Five Combination', 'Form Six Combination', 'Staff') NOT NULL,
    combination_code VARCHAR(10) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_combination_team (team_type, combination_code)
);

-- Participants table (links students/staff to teams)
CREATE TABLE IF NOT EXISTS team_participants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    participant_type ENUM('Student', 'Staff') NOT NULL,
    participant_id INT NOT NULL, -- student_id or admin_id
    position VARCHAR(50),
    jersey_number VARCHAR(10),
    is_captain BOOLEAN DEFAULT FALSE,
    joined_date DATE,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (team_id, participant_type, participant_id)
);

-- Tournaments table
CREATE TABLE IF NOT EXISTS tournaments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tournament_name VARCHAR(100) NOT NULL,
    game_type_id INT NOT NULL,
    season VARCHAR(20),
    start_date DATE,
    end_date DATE,
    description TEXT,
    status ENUM('Upcoming', 'Ongoing', 'Completed', 'Cancelled') DEFAULT 'Upcoming',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (game_type_id) REFERENCES game_types(id),
    FOREIGN KEY (created_by) REFERENCES admins(id)
);

-- Tournament teams (participating teams)
CREATE TABLE IF NOT EXISTS tournament_teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tournament_id INT NOT NULL,
    team_id INT NOT NULL,
    group_name VARCHAR(10),
    points INT DEFAULT 0,
    matches_played INT DEFAULT 0,
    wins INT DEFAULT 0,
    draws INT DEFAULT 0,
    losses INT DEFAULT 0,
    goals_for INT DEFAULT 0,
    goals_against INT DEFAULT 0,
    goal_difference INT DEFAULT 0,
    status ENUM('Active', 'Eliminated', 'Winner', 'RunnerUp') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tournament_team (tournament_id, team_id)
);

-- Matches table
CREATE TABLE IF NOT EXISTS matches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tournament_id INT NOT NULL,
    game_type_id INT NOT NULL,
    stage_id INT NOT NULL,
    match_number INT,
    team1_id INT NOT NULL,
    team2_id INT NOT NULL,
    team1_score INT DEFAULT 0,
    team2_score INT DEFAULT 0,
    winner_team_id INT NULL,
    match_date DATE NOT NULL,
    match_time TIME NOT NULL,
    venue VARCHAR(100),
    status ENUM('Scheduled', 'In Progress', 'Completed', 'Postponed', 'Cancelled') DEFAULT 'Scheduled',
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (game_type_id) REFERENCES game_types(id),
    FOREIGN KEY (stage_id) REFERENCES tournament_stages(id),
    FOREIGN KEY (team1_id) REFERENCES teams(id),
    FOREIGN KEY (team2_id) REFERENCES teams(id),
    FOREIGN KEY (winner_team_id) REFERENCES teams(id),
    FOREIGN KEY (created_by) REFERENCES admins(id)
);

-- Match officials table
CREATE TABLE IF NOT EXISTS match_officials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    match_id INT NOT NULL,
    admin_id INT NOT NULL,
    role ENUM('Referee', 'Assistant Referee 1', 'Assistant Referee 2', 'Scorekeeper', 'Timekeeper') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id),
    UNIQUE KEY unique_match_official (match_id, admin_id, role)
);

-- Match statistics table
CREATE TABLE IF NOT EXISTS match_statistics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    match_id INT NOT NULL,
    team_id INT NOT NULL,
    participant_id INT,
    participant_type ENUM('Student', 'Staff'),
    event_type ENUM('Goal', 'Yellow Card', 'Red Card', 'Substitution', 'Injury') NOT NULL,
    event_time TIME NOT NULL,
    event_minute INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id)
);

-- Insert default data
INSERT INTO game_types (game_name, description) VALUES
('Football', 'Soccer/Football matches'),
('Netball', 'Netball matches'),
('Handball', 'Handball matches'),
('Volleyball', 'Volleyball matches');

INSERT INTO tournament_stages (stage_name, stage_order, description) VALUES
('Group Stage', 1, 'Group stage matches'),
('Quarter Finals', 2, 'Quarter final matches'),
('Semi Finals', 3, 'Semi final matches'),
('Final', 4, 'Championship final match'),
('3rd Place Playoff', 5, 'Third place playoff match');

-- Insert teams (Form Five combinations)
INSERT INTO teams (team_name, team_type, combination_code) VALUES
('HGE Form Five', 'Form Five Combination', 'HGE'),
('HGL Form Five', 'Form Five Combination', 'HGL'),
('HGK Form Five', 'Form Five Combination', 'HGK'),
('HKL Form Five', 'Form Five Combination', 'HKL'),
('KLF Form Five', 'Form Five Combination', 'KLF'),
('EGM Form Five', 'Form Five Combination', 'EGM'),
('HLF Form Five', 'Form Five Combination', 'HLF'),
('HGF Form Five', 'Form Five Combination', 'HGF');

-- Insert teams (Form Six combinations)
INSERT INTO teams (team_name, team_type, combination_code) VALUES
('HGE Form Six', 'Form Six Combination', 'HGE'),
('HGL Form Six', 'Form Six Combination', 'HGL'),
('HGK Form Six', 'Form Six Combination', 'HGK'),
('HKL Form Six', 'Form Six Combination', 'HKL'),
('KLF Form Six', 'Form Six Combination', 'KLF'),
('EGM Form Six', 'Form Six Combination', 'EGM'),
('HLF Form Six', 'Form Six Combination', 'HLF'),
('HGF Form Six', 'Form Six Combination', 'HGF');

-- Insert Staff team
INSERT INTO teams (team_name, team_type, combination_code) VALUES
('Staff Team', 'Staff', NULL);

-- Sports Equipment Store Tables

-- Equipment table
CREATE TABLE IF NOT EXISTS sports_equipment (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    min_quantity INT NOT NULL DEFAULT 5,
    short_note TEXT,
    image_path VARCHAR(255),
    purchase_date DATE,
    purchase_price DECIMAL(10,2) DEFAULT 0,
    is_archived BOOLEAN DEFAULT FALSE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id)
);

-- Equipment transactions table (for tracking stock changes)
CREATE TABLE IF NOT EXISTS equipment_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    equipment_id INT NOT NULL,
    transaction_type ENUM('IN', 'OUT') NOT NULL,
    quantity INT NOT NULL,
    previous_quantity INT NOT NULL,
    new_quantity INT NOT NULL,
    reason TEXT NOT NULL,
    performed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES sports_equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES admins(id)
);

-- Add indexes for better performance
ALTER TABLE sports_equipment ADD INDEX idx_category (category);
ALTER TABLE sports_equipment ADD INDEX idx_quantity (quantity);
ALTER TABLE equipment_transactions ADD INDEX idx_equipment (equipment_id);
ALTER TABLE equipment_transactions ADD INDEX idx_created_at (created_at);