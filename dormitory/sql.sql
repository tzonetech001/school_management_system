-- =============================================
-- DROP ALL EXISTING DORMITORY TABLES
-- =============================================

-- First, drop foreign key constraints if they exist
ALTER TABLE student_dormitory DROP FOREIGN KEY IF EXISTS student_dormitory_ibfk_1;
ALTER TABLE student_dormitory DROP FOREIGN KEY IF EXISTS student_dormitory_ibfk_2;
ALTER TABLE student_dormitory DROP FOREIGN KEY IF EXISTS student_dormitory_ibfk_3;
ALTER TABLE student_dormitory DROP FOREIGN KEY IF EXISTS student_dormitory_ibfk_4;
ALTER TABLE dormitory_rooms DROP FOREIGN KEY IF EXISTS dormitory_rooms_ibfk_1;

-- Drop tables in correct order
DROP TABLE IF EXISTS student_dormitory;
DROP TABLE IF EXISTS dormitory_rooms;
DROP TABLE IF EXISTS dormitories;

-- =============================================
-- CREATE FRESH TABLES
-- =============================================

-- Table: dormitories
CREATE TABLE `dormitories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dorm_name` varchar(50) NOT NULL,
  `dorm_type` enum('Male','Female') NOT NULL,
  `rooms_count` int(11) NOT NULL DEFAULT 0,
  `capacity_per_room` int(11) NOT NULL DEFAULT 0,
  `total_capacity` int(11) NOT NULL DEFAULT 0,
  `current_occupancy` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `status` enum('Active','Full','Maintenance','Closed') DEFAULT 'Active',
  `school_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_school_id` (`school_id`),
  KEY `idx_dorm_type` (`dorm_type`),
  KEY `idx_status` (`status`),
  -- This allows same dorm name across different schools, but unique within a school
  UNIQUE KEY `unique_school_dorm` (`school_id`, `dorm_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: dormitory_rooms
CREATE TABLE `dormitory_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dormitory_id` int(11) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `room_label` varchar(20) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 0,
  `current_occupancy` int(11) NOT NULL DEFAULT 0,
  `status` enum('Available','Full','Maintenance') DEFAULT 'Available',
  `school_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_dorm_room` (`dormitory_id`, `room_number`),
  KEY `idx_dormitory_id` (`dormitory_id`),
  KEY `idx_status` (`status`),
  KEY `idx_school_id` (`school_id`),
  CONSTRAINT `dormitory_rooms_ibfk_1` FOREIGN KEY (`dormitory_id`) REFERENCES `dormitories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: student_dormitory
CREATE TABLE `student_dormitory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `dormitory_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `bed_number` varchar(10) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('Active','Left','Graduated') DEFAULT 'Active',
  `notes` text DEFAULT NULL,
  `school_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_active_assignment` (`student_id`, `status`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_dormitory_id` (`dormitory_id`),
  KEY `idx_room_id` (`room_id`),
  KEY `idx_status` (`status`),
  KEY `idx_school_id` (`school_id`),
  CONSTRAINT `student_dormitory_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_dormitory_ibfk_2` FOREIGN KEY (`dormitory_id`) REFERENCES `dormitories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_dormitory_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `dormitory_rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- CREATE TRIGGERS
-- =============================================

DELIMITER $$

-- Trigger: Update room occupancy when student assigned
CREATE TRIGGER `update_room_occupancy_insert` 
AFTER INSERT ON `student_dormitory` 
FOR EACH ROW 
BEGIN
    IF NEW.status = 'Active' THEN
        UPDATE dormitory_rooms 
        SET current_occupancy = current_occupancy + 1,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = NEW.room_id;
    END IF;
END$$

-- Trigger: Update room occupancy when student removed
CREATE TRIGGER `update_room_occupancy_delete` 
AFTER UPDATE ON `student_dormitory` 
FOR EACH ROW 
BEGIN
    IF OLD.status = 'Active' AND NEW.status != 'Active' THEN
        UPDATE dormitory_rooms 
        SET current_occupancy = GREATEST(current_occupancy - 1, 0),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = OLD.room_id;
    END IF;
END$$

-- Trigger: Update dormitory occupancy
CREATE TRIGGER `update_dormitory_occupancy` 
AFTER UPDATE ON `dormitory_rooms` 
FOR EACH ROW 
BEGIN
    IF OLD.current_occupancy != NEW.current_occupancy THEN
        UPDATE dormitories 
        SET current_occupancy = (
            SELECT COALESCE(SUM(current_occupancy), 0)
            FROM dormitory_rooms
            WHERE dormitory_id = NEW.dormitory_id
        ),
        status = CASE 
            WHEN (SELECT COALESCE(SUM(current_occupancy), 0) FROM dormitory_rooms WHERE dormitory_id = NEW.dormitory_id) >= total_capacity 
            THEN 'Full'
            ELSE 'Active'
        END,
        updated_at = CURRENT_TIMESTAMP
        WHERE id = NEW.dormitory_id;
    END IF;
END$$

DELIMITER ;

-- =============================================
-- INSERT SAMPLE DORMITORIES FOR TESTING
-- =============================================

INSERT INTO `dormitories` (`dorm_name`, `dorm_type`, `rooms_count`, `capacity_per_room`, `total_capacity`, `current_occupancy`, `description`, `status`, `school_id`) VALUES
('Magufuli', 'Male', 20, 6, 120, 0, 'Magufuli Male Dormitory - Rooms A1 to B10', 'Active', 1),
('Sokoine', 'Male', 20, 6, 120, 0, 'Sokoine Male Dormitory - Rooms A1 to B10', 'Active', 1),
('Mwandu', 'Male', 20, 6, 120, 0, 'Mwandu Male Dormitory - Rooms A1 to B10', 'Active', 1),
('Nyerere', 'Male', 10, 12, 120, 0, 'Nyerere Male Dormitory - Rooms A1 to A10', 'Active', 1),
('Kisutu Juu', 'Male', 5, 6, 30, 0, 'Kisutu Juu Male Dormitory - Rooms A1 to A5', 'Active', 1),
('Kisutu Bombani', 'Male', 2, 12, 24, 0, 'Kisutu Bombani Male Dormitory - Rooms A1 to B1', 'Active', 1),
('Kisutu Chini', 'Male', 2, 6, 12, 0, 'Kisutu Chini Male Dormitory - Rooms A1 to B1', 'Active', 1),
('Kisutu Prison', 'Male', 7, 2, 14, 0, 'Kisutu Prison Male Dormitory - Rooms A1 to A7', 'Active', 1),
('Safina', 'Female', 16, 10, 160, 0, 'Safina Female Dormitory - Rooms A1 to B8', 'Active', 1),
('Samia', 'Female', 20, 6, 120, 0, 'Samia Female Dormitory - Rooms A1 to B10', 'Active', 1);

-- =============================================
-- CREATE ROOMS FOR EACH DORMITORY
-- =============================================

DELIMITER $$

CREATE PROCEDURE `create_dormitory_rooms`()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_dorm_id INT;
    DECLARE v_rooms_count INT;
    DECLARE v_capacity INT;
    DECLARE v_school_id INT;
    DECLARE v_dorm_name VARCHAR(50);
    DECLARE cur CURSOR FOR SELECT id, rooms_count, capacity_per_room, school_id, dorm_name FROM dormitories;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_dorm_id, v_rooms_count, v_capacity, v_school_id, v_dorm_name;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Delete existing rooms for this dormitory
        DELETE FROM dormitory_rooms WHERE dormitory_id = v_dorm_id;
        
        -- Create new rooms
        SET @i = 1;
        WHILE @i <= v_rooms_count DO
            SET @room_label = CONCAT(
                CASE 
                    WHEN @i <= 10 THEN 'A'
                    ELSE CHAR(ASCII('A') + FLOOR((@i - 1) / 10))
                END,
                CASE 
                    WHEN @i <= 10 THEN @i
                    ELSE ((@i - 1) % 10) + 1
                END
            );
            
            INSERT INTO dormitory_rooms (dormitory_id, room_number, room_label, capacity, current_occupancy, status, school_id)
            VALUES (v_dorm_id, @room_label, @room_label, v_capacity, 0, 'Available', v_school_id);
            
            SET @i = @i + 1;
        END WHILE;
        
    END LOOP;
    
    CLOSE cur;
END$$

DELIMITER ;

-- Run the procedure to create rooms
CALL create_dormitory_rooms();

-- Drop the procedure after use
DROP PROCEDURE IF EXISTS create_dormitory_rooms;


-- =============================================
-- RECREATE STORED PROCEDURES FOR DORMITORY
-- =============================================

DELIMITER $$

-- Drop existing procedures if they exist
DROP PROCEDURE IF EXISTS assign_student_to_dormitory$$
DROP PROCEDURE IF EXISTS update_student_dormitory$$
DROP PROCEDURE IF EXISTS remove_dormitory_assignment$$

-- =============================================
-- PROCEDURE: assign_student_to_dormitory
-- =============================================
CREATE PROCEDURE `assign_student_to_dormitory` (
    IN `p_student_id` INT, 
    IN `p_dormitory_id` INT, 
    IN `p_room_id` INT, 
    IN `p_bed_number` VARCHAR(10), 
    IN `p_assigned_by` INT, 
    IN `p_notes` TEXT
)
BEGIN
    DECLARE v_student_name VARCHAR(201);
    DECLARE v_school_id INT;
    
    START TRANSACTION;
    
    -- Get student name and school_id
    SELECT CONCAT(first_name, ' ', last_name), school_id INTO v_student_name, v_school_id
    FROM students WHERE id = p_student_id;
    
    -- Check if student already has active assignment
    IF EXISTS (SELECT 1 FROM student_dormitory WHERE student_id = p_student_id AND status = 'Active') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Student already has an active dormitory assignment!';
    END IF;
    
    -- Check if room has capacity
    IF EXISTS (SELECT 1 FROM dormitory_rooms WHERE id = p_room_id AND current_occupancy >= capacity) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room is already at full capacity!';
    END IF;
    
    -- Insert the assignment
    INSERT INTO student_dormitory (
        student_id, 
        dormitory_id, 
        room_id, 
        bed_number, 
        assigned_by, 
        status, 
        notes,
        school_id
    )
    VALUES (
        p_student_id, 
        p_dormitory_id, 
        p_room_id, 
        p_bed_number, 
        p_assigned_by, 
        'Active', 
        p_notes,
        v_school_id
    );
    
    -- Update room occupancy (trigger will handle this, but explicit update ensures it)
    UPDATE dormitory_rooms 
    SET current_occupancy = current_occupancy + 1,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_room_id;
    
    -- Update dormitory occupancy
    UPDATE dormitories 
    SET current_occupancy = (
        SELECT COALESCE(SUM(current_occupancy), 0)
        FROM dormitory_rooms
        WHERE dormitory_id = p_dormitory_id
    ),
    status = CASE 
        WHEN (SELECT COALESCE(SUM(current_occupancy), 0) FROM dormitory_rooms WHERE dormitory_id = p_dormitory_id) >= total_capacity 
        THEN 'Full'
        ELSE 'Active'
    END,
    updated_at = CURRENT_TIMESTAMP
    WHERE id = p_dormitory_id;
    
    COMMIT;
    
    SELECT 'SUCCESS' as status, CONCAT('Student ', v_student_name, ' assigned to dormitory successfully!') as message;
END$$

-- =============================================
-- PROCEDURE: update_student_dormitory
-- =============================================
CREATE PROCEDURE `update_student_dormitory` (
    IN `p_assignment_id` INT,
    IN `p_new_dormitory_id` INT,
    IN `p_new_room_id` INT,
    IN `p_new_bed_number` VARCHAR(10),
    IN `p_updated_by` INT,
    IN `p_notes` TEXT
)
BEGIN
    DECLARE v_old_room_id INT;
    DECLARE v_old_dormitory_id INT;
    DECLARE v_student_id INT;
    DECLARE v_student_name VARCHAR(201);
    DECLARE v_school_id INT;
    
    START TRANSACTION;
    
    -- Get current assignment details
    SELECT room_id, dormitory_id, student_id, school_id 
    INTO v_old_room_id, v_old_dormitory_id, v_student_id, v_school_id
    FROM student_dormitory 
    WHERE id = p_assignment_id AND status = 'Active';
    
    IF v_old_room_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active assignment not found!';
    END IF;
    
    -- Get student name
    SELECT CONCAT(first_name, ' ', last_name) INTO v_student_name
    FROM students WHERE id = v_student_id;
    
    -- Check new room capacity
    IF EXISTS (
        SELECT 1 FROM dormitory_rooms 
        WHERE id = p_new_room_id 
        AND current_occupancy >= capacity
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'New room is already at full capacity!';
    END IF;
    
    -- Update assignment
    UPDATE student_dormitory 
    SET dormitory_id = p_new_dormitory_id,
        room_id = p_new_room_id,
        bed_number = p_new_bed_number,
        notes = CONCAT(COALESCE(notes, ''), ' | Changed: ', p_notes),
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_assignment_id;
    
    -- Update old room occupancy
    UPDATE dormitory_rooms 
    SET current_occupancy = GREATEST(current_occupancy - 1, 0),
        updated_at = CURRENT_TIMESTAMP
    WHERE id = v_old_room_id;
    
    -- Update new room occupancy
    UPDATE dormitory_rooms 
    SET current_occupancy = current_occupancy + 1,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_new_room_id;
    
    -- Update old dormitory occupancy
    UPDATE dormitories 
    SET current_occupancy = (
        SELECT COALESCE(SUM(current_occupancy), 0)
        FROM dormitory_rooms
        WHERE dormitory_id = v_old_dormitory_id
    ),
    status = CASE 
        WHEN (SELECT COALESCE(SUM(current_occupancy), 0) FROM dormitory_rooms WHERE dormitory_id = v_old_dormitory_id) >= total_capacity 
        THEN 'Full'
        ELSE 'Active'
    END,
    updated_at = CURRENT_TIMESTAMP
    WHERE id = v_old_dormitory_id;
    
    -- Update new dormitory occupancy
    UPDATE dormitories 
    SET current_occupancy = (
        SELECT COALESCE(SUM(current_occupancy), 0)
        FROM dormitory_rooms
        WHERE dormitory_id = p_new_dormitory_id
    ),
    status = CASE 
        WHEN (SELECT COALESCE(SUM(current_occupancy), 0) FROM dormitory_rooms WHERE dormitory_id = p_new_dormitory_id) >= total_capacity 
        THEN 'Full'
        ELSE 'Active'
    END,
    updated_at = CURRENT_TIMESTAMP
    WHERE id = p_new_dormitory_id;
    
    COMMIT;
    
    SELECT 'SUCCESS' as status, CONCAT('Assignment for ', v_student_name, ' updated successfully!') as message;
END$$

-- =============================================
-- PROCEDURE: remove_dormitory_assignment
-- =============================================
CREATE PROCEDURE `remove_dormitory_assignment` (
    IN `p_assignment_id` INT,
    IN `p_notes` TEXT
)
BEGIN
    DECLARE v_student_name VARCHAR(201);
    DECLARE v_student_id INT;
    DECLARE v_room_id INT;
    DECLARE v_dormitory_id INT;
    DECLARE v_school_id INT;
    
    START TRANSACTION;
    
    -- Get student info
    SELECT s.id, CONCAT(s.first_name, ' ', s.last_name), sd.room_id, sd.dormitory_id, sd.school_id
    INTO v_student_id, v_student_name, v_room_id, v_dormitory_id, v_school_id
    FROM student_dormitory sd
    JOIN students s ON sd.student_id = s.id
    WHERE sd.id = p_assignment_id AND sd.status = 'Active';
    
    IF v_student_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active assignment not found!';
    END IF;
    
    -- Update assignment status
    UPDATE student_dormitory 
    SET status = 'Left', 
        notes = CONCAT(COALESCE(notes, ''), ' | Removed: ', p_notes),
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_assignment_id;
    
    -- Update room occupancy
    UPDATE dormitory_rooms 
    SET current_occupancy = GREATEST(current_occupancy - 1, 0),
        updated_at = CURRENT_TIMESTAMP
    WHERE id = v_room_id;
    
    -- Update dormitory occupancy
    UPDATE dormitories 
    SET current_occupancy = (
        SELECT COALESCE(SUM(current_occupancy), 0)
        FROM dormitory_rooms
        WHERE dormitory_id = v_dormitory_id
    ),
    status = CASE 
        WHEN (SELECT COALESCE(SUM(current_occupancy), 0) FROM dormitory_rooms WHERE dormitory_id = v_dormitory_id) >= total_capacity 
        THEN 'Full'
        ELSE 'Active'
    END,
    updated_at = CURRENT_TIMESTAMP
    WHERE id = v_dormitory_id;
    
    COMMIT;
    
    SELECT 'SUCCESS' as status, CONCAT('Assignment for ', v_student_name, ' removed successfully!') as message;
END$$

DELIMITER ;

-- Verify procedures were created
SHOW PROCEDURE STATUS WHERE Db = 'school';