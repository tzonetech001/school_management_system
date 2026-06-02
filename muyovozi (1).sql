-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 02, 2026 at 02:26 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `muyovozi`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `assign_student_to_dormitory` (IN `p_student_id` INT, IN `p_dormitory_id` INT, IN `p_room_id` INT, IN `p_bed_number` VARCHAR(10), IN `p_assigned_by` INT, IN `p_notes` TEXT)   BEGIN
    DECLARE v_student_name VARCHAR(201);
    
    START TRANSACTION;
    
    -- Get student name for error messages
    SELECT CONCAT(first_name, ' ', last_name) INTO v_student_name
    FROM students WHERE id = p_student_id;
    
    -- Insert the assignment (triggers will handle validation and occupancy updates)
    INSERT INTO student_dormitory (student_id, dormitory_id, room_id, bed_number, assigned_by, status, notes)
    VALUES (p_student_id, p_dormitory_id, p_room_id, p_bed_number, p_assigned_by, 'Active', p_notes);
    
    COMMIT;
    
    SELECT 'SUCCESS' as status, CONCAT('Student ', v_student_name, ' assigned to dormitory successfully!') as message;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_available_rooms` (IN `p_dormitory_id` INT)   BEGIN
    SELECT 
        dr.id,
        dr.room_number,
        dr.room_label,
        dr.capacity,
        dr.current_occupancy,
        (dr.capacity - dr.current_occupancy) as available_beds,
        dr.status,
        d.dorm_name,
        d.dorm_type
    FROM dormitory_rooms dr
    JOIN dormitories d ON dr.dormitory_id = d.id
    WHERE dr.dormitory_id = p_dormitory_id
    AND dr.status = 'Available'
    AND dr.current_occupancy < dr.capacity
    ORDER BY dr.room_number;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `graduate_form_six_students` (IN `p_academic_year` VARCHAR(9), IN `p_graduation_date` DATE, IN `p_admin_id` INT, IN `p_student_ids` TEXT)   BEGIN
    DECLARE v_student_id INT;
    DECLARE v_done INT DEFAULT FALSE;
    DECLARE cur_students CURSOR FOR 
        SELECT id FROM students 
        WHERE FIND_IN_SET(id, p_student_ids) > 0 
        AND class = 'Form Six' 
        AND is_leaver = 0;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;
    
    OPEN cur_students;
    
    graduation_loop:LOOP
        FETCH cur_students INTO v_student_id;
        IF v_done THEN
            LEAVE graduation_loop;
        END IF;
        
        UPDATE students 
        SET is_leaver = 1,
            graduation_status = 'Graduated',
            graduation_year = YEAR(p_graduation_date),
            year_left = YEAR(p_graduation_date),
            status = 0,
            updated_by_admin = p_admin_id
        WHERE id = v_student_id;
        
        INSERT INTO student_graduation_history (
            student_id, from_class, to_class, academic_year,
            graduation_type, graduation_date, recorded_by
        ) VALUES (
            v_student_id, 'Form Six', 'Graduated', p_academic_year,
            'Graduation', p_graduation_date, p_admin_id
        );
        
    END LOOP;
    
    CLOSE cur_students;
    
    SELECT CONCAT('Graduated ', ROW_COUNT(), ' Form Six students') as result;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `init_contribution_setting` (IN `academic_year` VARCHAR(20))   BEGIN
    INSERT INTO contribution_settings (required_amount, academic_year, is_active) 
    SELECT 80000.00, academic_year, TRUE
    WHERE NOT EXISTS (SELECT 1 FROM contribution_settings);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `promote_form_five_to_six` (IN `p_academic_year` VARCHAR(9), IN `p_promotion_date` DATE, IN `p_admin_id` INT, IN `p_student_ids` TEXT)   BEGIN
    DECLARE v_student_id INT;
    DECLARE v_done INT DEFAULT FALSE;
    DECLARE cur_students CURSOR FOR 
        SELECT id FROM students 
        WHERE FIND_IN_SET(id, p_student_ids) > 0 
        AND class = 'Form Five' 
        AND is_leaver = 0;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;
    
    OPEN cur_students;
    
    promotion_loop: LOOP
        FETCH cur_students INTO v_student_id;
        IF v_done THEN
            LEAVE promotion_loop;
        END IF;
        
        UPDATE students 
        SET class = 'Form Six',
            previous_class = 'Form Five',
            class_changed_at = CURRENT_TIMESTAMP,
            promotion_status = 'Promoted to Form Six',
            updated_by_admin = p_admin_id
        WHERE id = v_student_id;
        
        INSERT INTO student_graduation_history (
            student_id, from_class, to_class, academic_year,
            graduation_type, graduation_date, recorded_by
        ) VALUES (
            v_student_id, 'Form Five', 'Form Six', p_academic_year,
            'Promotion', p_promotion_date, p_admin_id
        );
        
    END LOOP;
    
    CLOSE cur_students;
    
    SELECT CONCAT('Promoted ', ROW_COUNT(), ' students from Form Five to Form Six') as result;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `remove_dormitory_assignment` (IN `p_assignment_id` INT, IN `p_notes` TEXT)   BEGIN
    DECLARE v_student_name VARCHAR(201);
    DECLARE v_student_id INT;
    
    START TRANSACTION;
    
    -- Get student info
    SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) INTO v_student_id, v_student_name
    FROM student_dormitory sd
    JOIN students s ON sd.student_id = s.id
    WHERE sd.id = p_assignment_id;
    
    IF v_student_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Assignment not found!';
    END IF;
    
    -- Update assignment status (triggers will handle occupancy)
    UPDATE student_dormitory 
    SET status = 'Left', 
        notes = CONCAT(COALESCE(notes, ''), ' | Removed: ', p_notes),
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_assignment_id;
    
    COMMIT;
    
    SELECT 'SUCCESS' as status, CONCAT('Assignment for ', v_student_name, ' removed successfully!') as message;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `update_student_dormitory` (IN `p_assignment_id` INT, IN `p_new_dormitory_id` INT, IN `p_new_room_id` INT, IN `p_new_bed_number` VARCHAR(10), IN `p_updated_by` INT, IN `p_notes` TEXT)   BEGIN
    DECLARE v_old_room_id INT;
    DECLARE v_old_dormitory_id INT;
    DECLARE v_student_id INT;
    DECLARE v_student_name VARCHAR(201);
    
    START TRANSACTION;
    
    -- Get current assignment details
    SELECT room_id, dormitory_id, student_id INTO v_old_room_id, v_old_dormitory_id, v_student_id
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
    
    -- Update assignment (triggers will handle room occupancy changes)
    UPDATE student_dormitory 
    SET dormitory_id = p_new_dormitory_id,
        room_id = p_new_room_id,
        bed_number = p_new_bed_number,
        notes = CONCAT(COALESCE(notes, ''), ' | Changed: ', p_notes),
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_assignment_id;
    
    COMMIT;
    
    SELECT 'SUCCESS' as status, CONCAT('Assignment for ', v_student_name, ' updated successfully!') as message;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `sex` enum('Male','Female') NOT NULL,
  `email` varchar(100) NOT NULL,
  `check_number` varchar(50) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `nida` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `reset_otp` varchar(6) DEFAULT NULL,
  `reset_otp_expiry` datetime DEFAULT NULL,
  `last_password_change` datetime DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_notification_check` timestamp NULL DEFAULT NULL,
  `address` text DEFAULT NULL,
  `updated_by_admin` int(11) DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_attempt` datetime DEFAULT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `is_super_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `first_name`, `middle_name`, `last_name`, `sex`, `email`, `check_number`, `phone_number`, `nida`, `password`, `reset_otp`, `reset_otp_expiry`, `last_password_change`, `profile_image`, `status`, `created_at`, `updated_at`, `last_notification_check`, `address`, `updated_by_admin`, `failed_login_attempts`, `locked_until`, `last_login_attempt`, `school_id`, `is_super_admin`) VALUES
(12, 'muyovozi', '', 'muyovozi', 'Male', 'admin@muyovozi.ac.tz', '', '255714343162', NULL, '$2y$10$4i9hIukEfe2nUj2dOsr9segLg1GlzZn1I3TNFgn3a0LGDNoHNPvNu', NULL, NULL, NULL, 'admin_12_1768193337.jpeg', 1, '2026-01-06 04:26:58', '2026-04-04 16:36:04', '2026-04-04 16:35:04', '', NULL, 0, NULL, '2026-03-14 16:42:08', 1, 0),
(13, 'ashura', 'tophic', 'mussa', 'Female', 'ashuu@gmail.com', '6578887654', '255790909090', '67543234569769767779', '$2y$10$4ECTtphQWimaAKTBC.akk.92NCZLARYoYMc.IiIEfokh9JK3FS3VG', NULL, NULL, NULL, 'admin_13_1773396332.jpg', 1, '2026-01-07 11:53:03', '2026-05-17 15:07:21', '2026-04-04 16:35:04', '', NULL, 0, NULL, '2026-05-17 18:07:21', 1, 0),
(14, 'samson', 'tophic', 'smith', 'Male', 'sam@gmail.com', '', '255790909087', '67549874567890987658', '$2y$10$sA7LcE/vF6AO4gB.mZ7kzu5ZB6Xlc8L9s9Qb0zItuhE9KO11UFpTq', NULL, NULL, NULL, '', 1, '2026-01-09 11:44:30', '2026-04-05 07:55:15', '2026-04-04 16:35:04', '', NULL, 0, NULL, '2026-04-05 10:55:15', 1, 0),
(15, 'aujenia', 'tophic', 'leo', 'Female', 'jen@gmail.com', '', '255714343162', NULL, '$2y$10$lPtgR8Q4VoNdTalk16tfs.5GsoOT4RJgEZFELQr3Uabf/ILdJAo1y', NULL, NULL, NULL, NULL, 1, '2026-01-21 15:05:50', '2026-04-04 16:36:04', '2026-04-04 16:35:04', NULL, NULL, 0, NULL, NULL, 1, 0),
(17, 'muyovozi', '', 'muyovozi', 'Male', 'muyovozi@gmail.com', '', '255766666666', '', '$2y$10$dvjQN799SCRkRaw0oz9gnOqMFKRqlc.yUOzehQTh42goE7Si6pz9.', NULL, NULL, NULL, 'admin_17_1773497651.jpg', 1, '2026-02-06 16:16:46', '2026-04-04 16:36:04', '2026-04-04 16:35:04', '', NULL, 0, NULL, '2026-03-14 16:46:08', 1, 0),
(26, 'Nazakia', 'Japan', 'Martine', 'Male', 'nazakiamartine04@gmail.com', '', '255763243765', NULL, '$2y$10$3/S2lHreAGqE9ICxv8AGnu.M9HHaq/14cCjL4rapWZ4pfkAgDe/BC', NULL, NULL, NULL, 'admin_26_1773393622.jpg', 1, '2026-03-08 05:37:43', '2026-05-21 11:51:27', '2026-04-04 16:35:04', '', NULL, 0, NULL, '2026-05-21 14:51:27', 1, 0),
(28, 'kafunsi', 'juma', 'kafunsi', 'Male', 'kafunsi@gmail.com', '', '255712837307', NULL, '$2y$10$5RT5YRj5T.npMZxntBmbU.0AA7ct7mAK3UyUcckA5tk1CfLIRt7hi', NULL, NULL, NULL, NULL, 1, '2026-03-11 17:33:32', '2026-05-21 11:54:30', '2026-04-04 16:35:04', NULL, NULL, 0, NULL, '2026-05-21 14:54:30', 1, 0),
(29, 'bamfu', 'leonard', 'bamfu', 'Male', 'bbamfu@gmail.com', '', '255823792374', NULL, '$2y$10$zT3feIqVAf8FGRX.20xzeub8wA.tcGwEcofwH9zPgBIqS1xN488su', NULL, NULL, NULL, NULL, 1, '2026-03-13 12:11:33', '2026-04-06 20:13:11', '2026-04-04 16:35:04', NULL, NULL, 0, NULL, '2026-04-06 23:13:11', 1, 0),
(31, 'Tzone', 'IT', 'TZ', 'Male', 'tz@gmail.com', NULL, '255714343162', '', '$2y$10$CrOylTI9y0x4vKXzbWI48extL2CjneN5VOs/LtSms57.6uoCaioE2', NULL, NULL, NULL, 'admin_31_1773490494.jpg', 1, '2026-03-13 12:55:59', '2026-04-09 04:42:25', '2026-04-04 16:35:04', '', NULL, 0, NULL, '2026-04-04 18:47:58', 1, 0),
(32, 'TZONE', 'tz', 'TECH', 'Male', 'tzone@gmail.com', '', '255783626760', '', '$2y$10$WbeCJLk5D6T3xS6eeb6hWeYc0lxQ1iKZT7QJeXdSQEXpPw5/cKTI6', NULL, NULL, NULL, '', 1, '2026-03-13 13:10:00', '2026-06-02 12:18:09', '2026-04-04 16:35:04', '', NULL, 0, NULL, '2026-06-02 15:18:09', 1, 0),
(34, 'Halima', 'leonard', 'peter', 'Female', 'fdiva5045@gmail.com', '', '255672389209', NULL, '$2y$10$4pa7e4B3hU1ofNKDsFg50OwZeXYVz0rbUbzzaoC1KwDMiSoPccOva', NULL, NULL, NULL, NULL, 1, '2026-03-14 15:26:23', '2026-04-04 16:36:04', '2026-04-04 16:35:04', NULL, NULL, 0, NULL, NULL, 1, 0),
(35, 'vivian', 'wiston', 'jacob', 'Female', 'vivian@gmail.com', '', '255755914218', NULL, '$2y$10$kghEh1Enfg3fyged8N/AReH65MrBhRXO3GLcsbXoiC2bt/kIu1O7a', NULL, NULL, NULL, 'admin_35_1774606773.jpg', 1, '2026-03-27 10:17:22', '2026-05-21 11:53:07', '2026-04-04 16:35:04', '', NULL, 0, NULL, '2026-05-21 14:53:07', 1, 0),
(36, 'Mkurugenzi', 'tz', 'Rashid', 'Male', 'ee@gmail.com', '', '255694372484', NULL, '$2y$10$SUYyskxjnnZaB2kl8Syrreb0gNuWU9kM7ESZoRDH6JFeYkKIrVlN2', NULL, NULL, NULL, NULL, 1, '2026-04-02 10:36:08', '2026-04-05 06:47:41', '2026-04-04 16:35:04', NULL, NULL, 0, NULL, '2026-04-05 09:45:11', 1, 0),
(37, 'tungilo', 'tungi', 'tungilo', 'Male', 'muyovozimuyovozi2@gmail.com', '', '255755082167', NULL, '$2y$10$JkbBLAZp8MKTMoXtRb21T.phJqy4MdCO3ppeXCwZGRnLQHayXO4LK', NULL, NULL, NULL, NULL, 1, '2026-04-08 14:32:38', '2026-04-11 07:45:33', NULL, NULL, NULL, 0, NULL, '2026-04-10 19:55:27', 1, 0),
(38, 'muyovozi', 'wiston', 'muyovozi', 'Male', 'muyovozimuyovozi3@gmail.com', NULL, '2556198440875', '', '$2y$10$iXc1248yBVPF.io47UilJuKvt0bicUxEwkmtp9M7TH6hkxjzK3RlS', NULL, NULL, NULL, NULL, 1, '2026-06-02 11:38:54', '2026-06-02 12:19:46', NULL, '', NULL, 0, NULL, '2026-06-02 15:19:46', 2, 0);

-- --------------------------------------------------------

--
-- Table structure for table `admin_login_attempts`
--

CREATE TABLE `admin_login_attempts` (
  `id` int(11) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `success` tinyint(1) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_login_attempts`
--

INSERT INTO `admin_login_attempts` (`id`, `identifier`, `success`, `ip_address`, `user_agent`, `attempt_time`, `school_id`) VALUES
(90, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 08:50:27', 1),
(91, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 09:08:35', 1),
(92, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 09:48:26', 1),
(93, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 11:16:56', 1),
(94, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 11:24:50', 1),
(95, 'tz@gmail.com', 1, '192.168.1.110', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 12:42:26', 1),
(96, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 13:59:24', 1),
(97, 'tz@gmail.com', 1, '192.168.1.110', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 16:25:48', 1),
(98, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 05:16:43', 1),
(99, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 07:38:12', 1),
(100, 'ashuu@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 08:39:19', 1),
(101, 'sam@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 08:40:20', 1),
(102, 'sam@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 08:40:28', 1),
(103, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 08:40:50', 1),
(104, 'ashuu@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 08:50:34', 1),
(105, 'franc@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 09:02:06', 1),
(106, 'tz', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 09:21:10', 1),
(107, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 09:21:19', 1),
(108, 'kafunsi@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 09:29:47', 1),
(109, 'ashuu@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 10:02:07', 1),
(110, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 10:05:53', 1),
(111, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 10:11:23', 1),
(112, 'sam@gmail.com', 1, '192.168.1.110', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 10:25:54', 1),
(113, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 10:29:46', 1),
(114, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 10:30:18', 1),
(115, 'tz@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 10:31:58', 1),
(116, 'e44', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 10:56:03', 1),
(117, 'bbamfu@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 12:12:38', 1),
(118, 'tz@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 12:49:18', 1),
(119, 'tz@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 12:57:59', 1),
(120, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 13:05:49', 1),
(121, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 13:15:15', 1),
(122, 'franc@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 13:46:32', 1),
(123, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 13:54:33', 1),
(124, 'bbamfu@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 13:55:57', 1),
(125, 'bbamfu@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 15:05:13', 1),
(126, 'sam@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 15:05:41', 1),
(127, 'ashuu@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 15:12:30', 1),
(128, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 15:20:39', 1),
(129, 'tz@gmail.com', 1, '192.168.1.131', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.0.0 Safari/537.36', '2026-03-13 15:43:08', 1),
(130, 'bbamfu@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:29:54', 1),
(131, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:40:54', 1),
(132, 'ashuu@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:59:08', 1),
(133, 'sam@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:58:13', 1),
(134, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:58:52', 1),
(135, 'sam@gmail.com', 1, '192.168.1.110', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 08:13:33', 1),
(136, 'sam@gmail.com', 1, '192.168.1.110', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 08:16:27', 1),
(137, 'franc@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 12:26:22', 1),
(138, 'sam@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 12:46:30', 1),
(139, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 12:48:32', 1),
(140, 'bbamfu@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 12:53:17', 1),
(141, 'sam@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 12:53:47', 1),
(142, 'franc@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 12:57:11', 1),
(143, 'franc@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 12:57:26', 1),
(144, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 13:21:41', 1),
(145, 'admin@muyovozi.ac.tz', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 13:42:08', 1),
(146, 'muyovozi@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 13:46:08', 1),
(147, 'bbamfu@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 14:16:59', 1),
(148, 'tz@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 16:30:12', 1),
(149, 'tz@gmail.com', 1, '192.168.1.186', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 17:26:32', 1),
(150, 'sam@gmail.com', 1, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 17:48:40', 1),
(151, 'tz@gmail.com', 1, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 17:56:00', 1),
(152, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 16:52:28', 1),
(153, 'tzone@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 05:19:31', 1),
(154, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 05:19:40', 1),
(155, 'sam@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 07:13:50', 1),
(156, 'sam@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 07:14:06', 1),
(157, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 07:17:43', 1),
(158, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:10:02', 1),
(159, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:15:52', 1),
(160, 'agness@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:17:48', 1),
(161, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 11:27:13', 1),
(162, 'tzone@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 01:48:41', 1),
(163, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 01:49:04', 1),
(164, 'sam@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 08:23:46', 1),
(165, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-28 08:31:43', 1),
(166, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 09:03:48', 1),
(167, 'tzone@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 16:07:04', 1),
(168, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 16:07:21', 1),
(169, 'tzone@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-31 18:32:56', 1),
(170, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-31 18:33:20', 1),
(171, 'kafunsi@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-31 18:33:50', 1),
(172, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 06:03:01', 1),
(173, 'kafunsi@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-01 06:10:39', 1),
(174, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 07:21:44', 1),
(175, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 22:01:41', 1),
(176, 'agness@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 22:05:22', 1),
(177, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 22:17:11', 1),
(178, 'tzone@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 22:27:17', 1),
(179, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 22:27:28', 1),
(180, 'sam@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 07:26:12', 1),
(181, 'tzonee@gmail.com', 0, '10.98.187.64', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-04-02 08:04:45', 1),
(182, 'tzone@gmail.com', 1, '10.98.187.64', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-04-02 08:05:00', 1),
(183, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 10:26:21', 1),
(184, 'agness@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-02 14:30:51', 1),
(185, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 16:01:49', 1),
(186, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 18:06:40', 1),
(187, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 03:51:44', 1),
(188, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 03:57:31', 1),
(189, 'agness@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-03 06:16:56', 1),
(190, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 09:18:52', 1),
(191, 'agness@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 09:19:42', 1),
(192, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 16:14:02', 1),
(193, 'tzone@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:56:31', 1),
(194, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:56:42', 1),
(195, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:57:08', 1),
(196, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 18:10:03', 1),
(197, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 18:21:28', 1),
(198, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:22:34', 1),
(199, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:56:17', 1),
(200, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 06:25:00', 1),
(201, 'tz@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-04 06:32:35', 1),
(202, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 15:01:33', 1),
(203, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 15:17:49', 1),
(204, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 15:18:16', 1),
(205, 'tz@gmail.com', 1, '10.98.187.248', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 15:47:58', 1),
(206, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 20:46:36', 1),
(207, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-04 22:04:08', 1),
(208, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 23:15:30', 1),
(209, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 04:25:21', 1),
(210, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 04:36:18', 1),
(211, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 05:13:54', 1),
(212, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 05:54:15', 1),
(213, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 06:11:27', 1),
(214, 'ee@gmail.com', 1, '10.98.187.63', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-05 06:45:11', 1),
(215, 'sam@gmail.com', 1, '10.98.187.248', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 06:51:31', 1),
(216, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 07:39:34', 1),
(217, 'sam@gmail.com', 1, '10.98.187.248', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 07:55:15', 1),
(218, 'tzone@gmail.com', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 19:27:56', 1);

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_id`, `action`, `description`, `details`, `ip_address`, `user_agent`, `created_at`, `school_id`) VALUES
(109, 12, 'Shule Salama Post', NULL, 'Posted: fgfg (ID: 0)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 15:43:09', 1),
(110, 12, 'Shule Salama Post', NULL, 'Posted: errerer (ID: 12)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 16:01:00', 1),
(111, 12, 'Shule Salama Post', NULL, 'Posted: eaferer (ID: 13)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 16:01:14', 1),
(112, 12, 'Shule Salama Delete', NULL, 'Deleted post ID: 12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 16:01:22', 1),
(113, 12, 'Shule Salama Delete', NULL, 'Deleted post ID: 9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 16:01:33', 1),
(114, 12, 'Shule Salama Delete', NULL, 'Deleted post ID: 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 16:01:42', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 08:28:33', 1),
(0, 12, 'Login', NULL, NULL, '192.168.1.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 09:46:16', 1),
(0, 12, 'Shule Salama Post', NULL, 'Posted: hello (ID: 0)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 13:43:18', 1),
(0, 12, 'Shule Salama Post', NULL, 'Posted: hkgvgfgb (ID: 0)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 14:25:26', 1),
(0, 12, 'Shule Salama Post', NULL, 'Posted: uiuiui (ID: 0)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 14:58:43', 1),
(0, 12, 'Shule Salama Delete', NULL, 'Deleted post ID: 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 15:02:01', 1),
(0, 12, 'Shule Salama Delete', NULL, 'Deleted post ID: 7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 15:02:19', 1),
(0, 14, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 15:04:02', 1),
(0, 12, 'Shule Salama Post', NULL, 'Posted: tazan the greatest (ID: 0)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 15:06:05', 1),
(0, 12, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 15:37:33', 1),
(0, 12, 'Shule Salama Post', NULL, 'Posted: helo herena (ID: 0)', '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 15:41:40', 1),
(0, 12, 'Shule Salama Post', NULL, 'Posted: fdsd (ID: 0)', '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 15:59:34', 1),
(0, 12, 'Logout', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:03:40', 1),
(0, 16, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:03:59', 1),
(0, 16, 'Logout', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:49:23', 1),
(0, 13, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:49:38', 1),
(0, 13, 'Logout', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:49:41', 1),
(0, 12, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:49:55', 1),
(0, 12, 'Logout', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:49:58', 1),
(0, 17, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:55:41', 1),
(0, 14, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-07 08:38:20', 1),
(0, 12, 'Login', NULL, NULL, '192.168.1.105', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-07 15:52:56', 1),
(0, 12, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 06:47:59', 1),
(0, 12, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 06:52:38', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 08:22:17', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 15:04:05', 1),
(109, 12, 'Shule Salama Post', NULL, 'Posted: fgfg (ID: 0)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 15:43:09', 1),
(110, 12, 'Shule Salama Post', NULL, 'Posted: errerer (ID: 12)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 16:01:00', 1),
(111, 12, 'Shule Salama Post', NULL, 'Posted: eaferer (ID: 13)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 16:01:14', 1),
(112, 12, 'Shule Salama Delete', NULL, 'Deleted post ID: 12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 16:01:22', 1),
(113, 12, 'Shule Salama Delete', NULL, 'Deleted post ID: 9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 16:01:33', 1),
(114, 12, 'Shule Salama Delete', NULL, 'Deleted post ID: 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 16:01:42', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 08:28:33', 1),
(0, 12, 'Login', NULL, NULL, '192.168.1.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 09:46:16', 1),
(0, 12, 'Shule Salama Post', NULL, 'Posted: hello (ID: 0)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 13:43:18', 1),
(0, 12, 'Shule Salama Post', NULL, 'Posted: hkgvgfgb (ID: 0)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 14:25:26', 1),
(0, 12, 'Shule Salama Post', NULL, 'Posted: uiuiui (ID: 0)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 14:58:43', 1),
(0, 12, 'Shule Salama Delete', NULL, 'Deleted post ID: 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 15:02:01', 1),
(0, 12, 'Shule Salama Delete', NULL, 'Deleted post ID: 7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 15:02:19', 1),
(0, 14, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-06 15:04:02', 1),
(0, 12, 'Shule Salama Post', NULL, 'Posted: tazan the greatest (ID: 0)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 15:06:05', 1),
(0, 12, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 15:37:33', 1),
(0, 12, 'Shule Salama Post', NULL, 'Posted: helo herena (ID: 0)', '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 15:41:40', 1),
(0, 12, 'Shule Salama Post', NULL, 'Posted: fdsd (ID: 0)', '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 15:59:34', 1),
(0, 12, 'Logout', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:03:40', 1),
(0, 16, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:03:59', 1),
(0, 16, 'Logout', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:49:23', 1),
(0, 13, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:49:38', 1),
(0, 13, 'Logout', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:49:41', 1),
(0, 12, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:49:55', 1),
(0, 12, 'Logout', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:49:58', 1),
(0, 17, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 16:55:41', 1),
(0, 14, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-07 08:38:20', 1),
(0, 12, 'Login', NULL, NULL, '192.168.1.105', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-07 15:52:56', 1),
(0, 12, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 06:47:59', 1),
(0, 12, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 06:52:38', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 08:22:17', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 13:29:17', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 13:37:43', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 21:41:48', 1),
(0, 12, 'Shule Salama Post', NULL, 'Posted: hello (ID: 0)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 21:42:29', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 21:42:45', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 23:22:40', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 23:52:25', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 23:59:38', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 00:05:39', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 00:06:40', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 00:06:50', 1),
(0, 14, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 00:06:59', 1),
(0, 14, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 00:07:21', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 00:08:18', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 00:12:58', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 00:13:54', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 00:14:42', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 01:35:15', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 04:06:42', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 04:16:39', 1),
(0, 26, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 11:37:05', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-10 11:38:35', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 13:07:18', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 13:07:24', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 13:17:59', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 13:18:30', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 13:51:09', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 13:51:30', 1),
(0, 14, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 14:21:01', 1),
(0, 14, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 14:21:19', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 14:46:49', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 14:46:58', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 15:04:57', 1),
(0, 12, 'Student Registered', 'Registered student: aaaaa 89y7y (Admission: 121212) with default password as parent phone', NULL, '::1', NULL, '2026-03-10 15:44:16', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 15:45:05', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 08:50:27', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 08:56:22', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 09:08:03', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 09:08:35', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 09:09:31', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 09:48:26', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 09:48:35', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 11:16:56', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 11:17:06', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 11:24:50', 1),
(0, 12, 'Login', NULL, NULL, '192.168.1.110', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 12:42:26', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 13:59:24', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 14:02:49', 1),
(0, 12, 'Login', NULL, NULL, '192.168.1.110', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 16:25:48', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 05:16:43', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 07:34:16', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 07:38:12', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 08:39:05', 1),
(0, 13, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 08:39:19', 1),
(0, 13, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 08:39:38', 1),
(0, 14, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 08:40:28', 1),
(0, 14, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 08:40:40', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 08:40:50', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 08:50:22', 1),
(0, 13, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 08:50:34', 1),
(0, 13, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 09:01:52', 1),
(0, 26, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 09:02:06', 1),
(0, 26, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 09:21:01', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 09:21:19', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 09:29:26', 1),
(0, 28, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 09:29:47', 1),
(0, 28, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 10:01:49', 1),
(0, 13, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 10:02:07', 1),
(0, 13, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 10:05:45', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 10:05:53', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 10:08:54', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 10:11:23', 1),
(0, 14, 'Login', NULL, NULL, '192.168.1.110', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 10:25:54', 1),
(0, 14, 'Logout', NULL, NULL, '192.168.1.110', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 10:26:35', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 10:29:34', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 10:29:46', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 10:30:18', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 10:31:04', 1),
(0, 29, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 12:12:38', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 12:48:56', 1),
(0, 31, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 13:05:49', 1),
(0, 31, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 13:14:47', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 13:15:15', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 13:46:06', 1),
(0, 26, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 13:46:32', 1),
(0, 26, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 13:53:34', 1),
(0, 31, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 13:54:33', 1),
(0, 31, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 13:55:35', 1),
(0, 29, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 13:55:57', 1),
(0, 29, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 15:04:48', 1),
(0, 29, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 15:05:13', 1),
(0, 29, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 15:05:25', 1),
(0, 14, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 15:05:41', 1),
(0, 14, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 15:12:10', 1),
(0, 13, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 15:12:30', 1),
(0, 13, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 15:20:21', 1),
(0, 31, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 15:20:39', 1),
(0, 31, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 15:42:12', 1),
(0, 31, 'Login', NULL, NULL, '192.168.1.131', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.0.0 Safari/537.36', '2026-03-13 15:43:08', 1),
(0, 31, 'Logout', NULL, NULL, '192.168.1.131', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.0.0 Safari/537.36', '2026-03-13 15:43:53', 1),
(0, 29, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:29:54', 1),
(0, 29, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:40:34', 1),
(0, 31, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:40:54', 1),
(0, 31, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:58:54', 1),
(0, 13, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 19:59:08', 1),
(0, 13, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:57:56', 1),
(0, 14, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:58:13', 1),
(0, 14, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:58:34', 1),
(0, 31, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 20:58:52', 1),
(0, 14, 'Login', NULL, NULL, '192.168.1.110', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 08:13:33', 1),
(0, 14, 'Login', NULL, NULL, '192.168.1.110', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 08:16:27', 1),
(0, 31, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 12:25:55', 1),
(0, 26, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 12:26:22', 1),
(0, 26, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 12:46:15', 1),
(0, 14, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 12:46:30', 1),
(0, 14, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 12:48:17', 1),
(0, 31, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 12:48:32', 1),
(0, 14, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 12:53:47', 1),
(0, 14, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 12:56:41', 1),
(0, 26, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 12:57:26', 1),
(0, 26, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 13:21:15', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 13:21:41', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 13:41:52', 1),
(0, 12, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 13:42:08', 1),
(0, 12, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 13:45:56', 1),
(0, 17, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 13:46:08', 1),
(0, 17, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 14:16:10', 1),
(0, 29, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 14:16:59', 1),
(0, 31, 'register_teacher', 'Registered new teacher: Halima peter (ID: 34)', NULL, '::1', NULL, '2026-03-14 15:26:23', 1),
(0, 31, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 16:30:12', 1),
(0, 29, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-14 16:50:56', 1),
(0, 31, 'Login', NULL, NULL, '192.168.1.186', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 17:26:32', 1),
(0, 14, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 17:48:40', 1),
(0, 14, 'Logout', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 17:55:48', 1),
(0, 31, 'Login', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 17:56:00', 1),
(0, 31, 'Logout', NULL, NULL, '192.168.1.172', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 18:01:51', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 16:52:28', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 05:19:40', 1),
(0, 14, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 07:14:06', 1),
(0, 14, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 07:17:31', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 07:17:43', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 07:50:54', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:10:02', 1),
(0, 32, 'Student Registered', 'Registered student: princess toy (Admission: y78) with default password as parent phone', NULL, '::1', NULL, '2026-03-27 10:12:05', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:12:32', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:15:52', 1),
(0, 32, 'register_teacher', 'Registered new teacher: agness taze (ID: 35)', NULL, '::1', NULL, '2026-03-27 10:17:22', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:17:29', 1),
(0, 35, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:17:48', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 11:27:13', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 01:49:04', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 08:23:27', 1),
(0, 14, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 08:23:46', 1),
(0, 14, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-28 08:29:49', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-28 08:31:43', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 09:03:48', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 16:07:21', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-31 18:33:20', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-31 18:33:41', 1),
(0, 28, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-31 18:33:50', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 06:03:01', 1),
(0, 28, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-01 06:10:39', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 07:21:44', 1),
(0, 32, 'Toggle Exam Status', NULL, 'deactivated exam type: Terminal Exam 2', NULL, NULL, '2026-04-01 08:25:09', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 22:01:41', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 22:05:04', 1),
(0, 35, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 22:05:22', 1),
(0, 35, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 22:12:53', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 22:17:11', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 22:25:48', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 22:27:28', 1),
(0, 14, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 07:26:12', 1),
(0, 32, 'Login', NULL, NULL, '10.98.187.64', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-04-02 08:05:00', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 10:26:21', 1),
(0, 32, 'register_teacher', 'Registered new teacher: herjmpew mzima (ID: 36)', NULL, '::1', NULL, '2026-04-02 10:36:08', 1),
(0, 32, 'Add Exam Type', NULL, 'Added exam type: school_exam (m33) for Form Six in year 2026', NULL, NULL, '2026-04-02 11:28:24', 1),
(0, 32, 'Duplicate Exam Type', NULL, 'Duplicated exam type from m33 to m33_F6_2027 for year 2027', NULL, NULL, '2026-04-02 11:28:38', 1),
(0, 32, 'Delete Exam Type', NULL, 'Deleted exam type: school_exam (m33_F6_2027) for Form Six in year 2027', NULL, NULL, '2026-04-02 11:46:22', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: school_exam', NULL, NULL, '2026-04-02 11:46:28', 1),
(0, 32, 'Toggle Exam Status', NULL, 'deactivated exam type: school_exam', NULL, NULL, '2026-04-02 11:46:33', 1),
(0, 32, 'Delete Exam Type', NULL, 'Deleted exam type: Mid-Term 1 (MT1) for Form Five in year 2026', NULL, NULL, '2026-04-02 11:47:12', 1),
(0, 32, 'Delete Exam Type', NULL, 'Deleted exam type: Mid-Term 2 (MT2) for Form Five in year 2026', NULL, NULL, '2026-04-02 11:47:16', 1),
(0, 32, 'Delete Exam Type', NULL, 'Deleted exam type: Terminal Exam 1 (TE1) for Form Five in year 2026', NULL, NULL, '2026-04-02 11:47:19', 1),
(0, 32, 'Delete Exam Type', NULL, 'Deleted exam type: School Exam (SE) for Form Five in year 2026 with 54 associated results', NULL, NULL, '2026-04-02 11:47:24', 1),
(0, 32, 'Delete Exam Type', NULL, 'Deleted exam type: Terminal Exam 2 (TE2) for Form Five in year 2026', NULL, NULL, '2026-04-02 11:47:28', 1),
(0, 32, 'Delete Exam Type', NULL, 'Deleted exam type: Pre-NECTA (PN) for Form Five in year 2026 with 71 associated results', NULL, NULL, '2026-04-02 11:47:34', 1),
(0, 32, 'Add Exam Type', NULL, 'Added exam type: school_exam (m33) for Form Five in year 2026', NULL, NULL, '2026-04-02 11:48:52', 1),
(0, 32, 'Delete Exam Type', NULL, 'Deleted exam type: school_exam (m33) for Form Five in year 2026', NULL, NULL, '2026-04-02 11:49:08', 1),
(0, 32, 'Add Exam Type', NULL, 'Added exam type: school_exam (m33) for Form Five in year 2026', NULL, NULL, '2026-04-02 11:49:26', 1),
(0, 32, 'Add Exam Type', NULL, 'Added exam type: school_exam (y77) for Form Six in year 2026', NULL, NULL, '2026-04-02 13:01:35', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: school_exam', NULL, NULL, '2026-04-02 13:04:16', 1),
(0, 32, 'Delete Exam Type', NULL, 'Deleted exam type: school_exam (y77) for Form Six in year 2026', NULL, NULL, '2026-04-02 13:07:02', 1),
(0, 32, 'Add Exam Type', NULL, 'Added exam type: school_exam (y77) for Form Six in year 2026', NULL, NULL, '2026-04-02 13:09:23', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: school_exam', NULL, NULL, '2026-04-02 13:09:28', 1),
(0, 32, 'Toggle Exam Status', NULL, 'deactivated exam type: school_exam', NULL, NULL, '2026-04-02 13:19:34', 1),
(0, 32, 'Delete Exam Type', NULL, 'Deleted exam type: school_exam (y77) for Form Six in year 2026', NULL, NULL, '2026-04-02 13:19:41', 1),
(0, 32, 'Add Exam Type', NULL, 'Added exam type: school_exam (m34) for Form Six in year 2026', NULL, NULL, '2026-04-02 13:25:32', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: school_exam', NULL, NULL, '2026-04-02 13:25:38', 1),
(0, 32, 'Add Exam Type', NULL, 'Added exam type: school_exam (y77) for Form Six in year 2026', NULL, NULL, '2026-04-02 13:49:24', 1),
(0, 32, 'Toggle Exam Status', NULL, 'deactivated exam type: school_exam', NULL, NULL, '2026-04-02 13:49:35', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: school_exam', NULL, NULL, '2026-04-02 13:50:21', 1),
(0, 32, 'Edit Exam Type', NULL, 'Edited exam type ID 18: school', NULL, NULL, '2026-04-02 13:50:36', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: school_exam', NULL, NULL, '2026-04-02 14:16:16', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: school', NULL, NULL, '2026-04-02 14:19:39', 1),
(0, 35, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-02 14:30:51', 1),
(0, 32, 'Delete Exam Type', NULL, 'Deleted exam type: school_exam (y77) for Form Six in year 2026 with 1 associated results', NULL, NULL, '2026-04-02 14:51:00', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 16:01:49', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: school_exam', NULL, NULL, '2026-04-02 16:02:15', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 18:06:40', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 03:51:44', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 03:57:31', 1),
(0, 32, 'Delete Exam Type', NULL, 'Deleted exam type: school_exam (m33) for Form Five in year 2026 with 5 associated results', NULL, NULL, '2026-04-03 05:08:33', 1),
(0, 32, 'Add Exam Type', NULL, 'Added exam type: hello_exam (89) for Form Five in year 2026', NULL, NULL, '2026-04-03 05:09:06', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: hello_exam', NULL, NULL, '2026-04-03 05:09:14', 1),
(0, 32, 'Assign Subject', NULL, 'Assigned ac to teacher ID 35 for Form Five (2026)', NULL, NULL, '2026-04-03 06:16:03', 1),
(0, 35, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-03 06:16:56', 1),
(0, 32, 'Remove Subject Assignment', NULL, 'Removed ac from agness taze for Form Five', NULL, NULL, '2026-04-03 06:46:13', 1),
(0, 32, 'Assign Subject', NULL, 'Assigned b_math to teacher ID 35 for Form Five (2026)', NULL, NULL, '2026-04-03 06:46:32', 1),
(0, 32, 'Assign Subject', NULL, 'Assigned his to teacher ID 35 for Form Six (2026)', NULL, NULL, '2026-04-03 07:13:36', 1),
(0, 32, 'Add Exam Type', NULL, 'Added exam type: tzine (yy) for Form Five in year 2026', NULL, NULL, '2026-04-03 07:24:09', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 09:18:52', 1);
INSERT INTO `admin_logs` (`id`, `admin_id`, `action`, `description`, `details`, `ip_address`, `user_agent`, `created_at`, `school_id`) VALUES
(0, 35, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 09:19:42', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 16:07:17', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 16:14:02', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:26:31', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:56:42', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:56:54', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:57:08', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:57:14', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 18:10:03', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 18:14:22', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 18:21:28', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:22:34', 1),
(0, 32, 'Student Registered', 'Registered student: agness world (Admission: t6re56778) with default password as parent phone', NULL, '::1', NULL, '2026-04-03 20:47:28', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:52:48', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:56:17', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 20:56:50', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 06:25:00', 1),
(0, 32, 'Logout', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 06:33:23', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 15:01:33', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 15:17:49', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 15:18:16', 1),
(0, 31, 'Login', NULL, NULL, '10.98.187.248', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 15:47:58', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 20:46:36', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-04 22:04:08', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-04 23:15:30', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 04:25:21', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 04:36:18', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 05:13:54', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 05:54:15', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 06:11:27', 1),
(0, 36, 'Login', NULL, NULL, '10.98.187.63', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-05 06:45:11', 1),
(0, 14, 'Login', NULL, NULL, '10.98.187.248', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 06:51:31', 1),
(0, 32, 'Add Exam Type', NULL, 'Added exam type: pre-necta (777) for Form Six in year 2026', NULL, NULL, '2026-04-05 06:56:14', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: pre-necta', NULL, NULL, '2026-04-05 06:56:23', 1),
(0, 32, 'Assign Subject', NULL, 'Assigned geo to teacher ID 36 for Form Six (2026)', NULL, NULL, '2026-04-05 06:57:21', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 07:39:34', 1),
(0, 14, 'Login', NULL, NULL, '10.98.187.248', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-05 07:55:15', 1),
(0, 32, 'Assign Subject', NULL, 'Assigned b_math to teacher ID 14 for Form Six (2026)', NULL, NULL, '2026-04-05 08:14:24', 1),
(0, 32, 'Remove Subject Assignment', NULL, 'Removed geo from Mkurugenzi Rashid for Form Six', NULL, NULL, '2026-04-05 08:16:31', 1),
(0, 32, 'Remove Subject Assignment', NULL, 'Removed b_math from samson smith for Form Six', NULL, NULL, '2026-04-05 08:16:46', 1),
(0, 32, 'Remove Subject Assignment', NULL, 'Removed his from agness taze for Form Six', NULL, NULL, '2026-04-05 08:16:54', 1),
(0, 32, 'Login', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 19:27:56', 1),
(0, 32, 'register_teacher', 'Registered new teacher: jkkfwlekwlcjnm wefqwe (ID: 37)', NULL, '::1', NULL, '2026-04-08 14:32:38', 1),
(0, 32, 'Assign Subject', NULL, 'Assigned htm to teacher ID 35 for Form Six (2026)', NULL, NULL, '2026-04-09 10:56:18', 1),
(0, 32, 'Remove Subject Assignment', NULL, 'Removed htm from agness taze for Form Six', NULL, NULL, '2026-04-09 10:56:26', 1),
(0, 32, 'Edit Exam Type', NULL, 'Edited exam type ID 21: school', NULL, NULL, '2026-04-11 05:44:13', 1),
(0, 32, 'Edit Exam Type', NULL, 'Edited exam type ID 20: mid_exam', NULL, NULL, '2026-04-11 05:44:43', 1),
(0, 32, 'Assign Subject', NULL, 'Assigned ac to teacher ID 13 for Form Five (2026)', NULL, NULL, '2026-04-11 06:19:11', 1),
(0, 32, 'Assign Subject', NULL, 'Assigned htm to teacher ID 28 for Form Five (2026)', NULL, NULL, '2026-04-11 06:19:30', 1),
(0, 32, 'Assign Subject', NULL, 'Assigned his to teacher ID 26 for Form Five (2026)', NULL, NULL, '2026-04-11 06:19:47', 1),
(0, 32, 'Assign Subject', NULL, 'Assigned ac to teacher ID 14 for Form Five (2026)', NULL, NULL, '2026-04-11 06:20:00', 1),
(0, 32, 'Assign Subject', NULL, 'Assigned eco to teacher ID 35 for Form Five (2026)', NULL, NULL, '2026-04-11 06:34:57', 1),
(0, 32, 'Assign Subject', NULL, 'Assigned eco to teacher ID 35 for Form Six (2026)', NULL, NULL, '2026-04-12 10:34:52', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: school', NULL, NULL, '2026-04-12 13:30:27', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: mid_exam', NULL, NULL, '2026-04-12 13:30:33', 1),
(0, 32, 'Add Exam Type', NULL, 'Added exam type: tzoneu (hgdrurt) for Form Five in year 2026', NULL, NULL, '2026-04-12 13:30:49', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: tzoneu', NULL, NULL, '2026-04-12 13:30:56', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: mid_exam', NULL, NULL, '2026-04-12 13:31:01', 1),
(0, 32, 'Remove Subject Assignment', NULL, 'Removed ac from ashura mussa for Form Five', NULL, NULL, '2026-04-12 13:42:42', 1),
(0, 32, 'Remove Subject Assignment', NULL, 'Removed htm from kafunsi kafunsi for Form Five', NULL, NULL, '2026-04-12 13:42:47', 1),
(0, 32, 'Remove Subject Assignment', NULL, 'Removed ac from samson smith for Form Five', NULL, NULL, '2026-04-12 13:42:54', 1),
(0, 32, 'Remove Subject Assignment', NULL, 'Removed his from Franc peter for Form Five', NULL, NULL, '2026-04-12 13:43:00', 1),
(0, 32, 'Remove Subject Assignment', NULL, 'Removed b_math from agness taze for Form Five', NULL, NULL, '2026-04-12 13:43:05', 1),
(0, 32, 'Remove Subject Assignment', NULL, 'Removed eco from agness taze for Form Five', NULL, NULL, '2026-04-12 13:43:12', 1),
(0, 32, 'Remove Subject Assignment', NULL, 'Removed eco from agness taze for Form Six', NULL, NULL, '2026-04-12 13:43:20', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: school', NULL, NULL, '2026-04-12 13:53:12', 1),
(0, 32, 'Delete Exam Type', NULL, 'Deleted exam type: school (m34) for Form Six in year 2026 with 57 associated results', NULL, NULL, '2026-04-12 13:55:03', 1),
(0, 32, 'Add Exam Type', NULL, 'Added exam type: school_exam (uihui) for Form Six in year 2026', NULL, NULL, '2026-04-12 13:55:14', 1),
(0, 32, 'Toggle Exam Status', NULL, 'activated exam type: school_exam', NULL, NULL, '2026-04-12 13:55:20', 1),
(0, 32, 'Assign Subject', NULL, 'Assigned eco to teacher ID 35 for Form Five (2026)', NULL, NULL, '2026-04-12 14:04:41', 1),
(0, 32, 'Assign Subject', NULL, 'Assigned eco to teacher ID 35 for Form Six (2026)', NULL, NULL, '2026-04-12 14:04:58', 1),
(0, 32, 'Assign Subject', NULL, 'Assigned b_math to teacher ID 35 for Form Six (2026)', NULL, NULL, '2026-04-12 14:05:15', 1),
(0, 32, 'Student Registered', 'Registered student: nakupenda uje (Admission: 909090hjj) with default password as parent phone', NULL, '::1', NULL, '2026-04-21 10:10:56', 1),
(0, 26, 'Toggle Exam Status', NULL, 'deactivated exam type: school', NULL, NULL, '2026-05-21 06:22:34', 1),
(0, 26, 'Toggle Exam Status', NULL, 'activated exam type: mid_exam', NULL, NULL, '2026-05-21 06:22:40', 1);

-- --------------------------------------------------------

--
-- Table structure for table `admin_roles`
--

CREATE TABLE `admin_roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_roles`
--

INSERT INTO `admin_roles` (`id`, `role_name`, `description`) VALUES
(1, 'Head Master', 'Head of the school'),
(2, 'Second Master', 'Deputy head master'),
(3, 'Academic Master', 'Responsible for academic affairs'),
(4, 'Discipline Master', 'Responsible for student discipline'),
(5, 'Class Teacher', 'Class teacher responsibilities'),
(6, 'Sports & Games', 'Responsible for sports activities'),
(7, 'Dormitory Teacher', 'Responsible for dormitories'),
(8, 'School Bursar & store', 'Responsible for finances & store'),
(9, 'Production', 'Responsible for production store'),
(10, 'INS Coach', 'Instructional coach'),
(11, 'Food Store', 'Responsible for food store'),
(12, 'PS', 'Personal Secretary'),
(13, 'Librarian', 'Library management'),
(14, 'Shule Salama', 'School security and safety'),
(15, 'Normal Teacher', 'Regular teaching duties'),
(16, 'Maintainance', 'Maintanance of the school');

-- --------------------------------------------------------

--
-- Table structure for table `admin_role_assignments`
--

CREATE TABLE `admin_role_assignments` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_role_assignments`
--

INSERT INTO `admin_role_assignments` (`id`, `admin_id`, `role_id`, `is_primary`, `assigned_at`) VALUES
(13, 11, 7, 0, '2026-01-07 11:45:53'),
(14, 11, 11, 1, '2026-01-07 11:45:53'),
(15, 13, 16, 1, '2026-01-07 11:53:03'),
(20, 14, 5, 1, '2026-01-09 11:44:30'),
(21, 15, 4, 1, '2026-01-21 15:05:50'),
(0, 25, 16, 1, '2026-02-06 16:21:17'),
(13, 11, 7, 0, '2026-01-07 11:45:53'),
(14, 11, 11, 1, '2026-01-07 11:45:53'),
(15, 13, 16, 1, '2026-01-07 11:53:03'),
(20, 14, 5, 1, '2026-01-09 11:44:30'),
(21, 15, 4, 1, '2026-01-21 15:05:50'),
(0, 25, 16, 1, '2026-02-06 16:21:17'),
(0, 0, 3, 0, '2026-03-08 05:33:05'),
(0, 0, 3, 0, '2026-03-08 05:33:05'),
(0, 0, 4, 1, '2026-03-08 05:33:05'),
(0, 0, 4, 1, '2026-03-08 05:33:05'),
(0, 12, 13, 0, '2026-03-13 12:42:26'),
(0, 12, 8, 1, '2026-03-13 12:42:26'),
(0, 31, 1, 1, '2026-03-13 12:57:11'),
(0, 31, 1, 0, '2026-03-13 12:57:43'),
(0, 32, 1, 1, '2026-03-14 14:12:23'),
(0, 17, 2, 1, '2026-03-14 14:12:54'),
(0, 34, 6, 1, '2026-03-14 15:26:23'),
(0, 29, 12, 1, '2026-04-05 04:49:28'),
(0, 29, 2, 0, '2026-04-05 04:49:28'),
(0, 36, 13, 0, '2026-04-05 06:47:41'),
(0, 36, 9, 1, '2026-04-05 06:47:41'),
(0, 37, 3, 1, '2026-04-11 07:45:33'),
(0, 35, 3, 1, '2026-05-21 11:15:05'),
(0, 28, 7, 1, '2026-05-21 11:15:53'),
(0, 28, 15, 0, '2026-05-21 11:15:53'),
(0, 26, 3, 1, '2026-05-21 11:22:38'),
(0, 38, 1, 1, '2026-06-02 11:38:54');

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `application_number` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `date_of_birth` date NOT NULL,
  `birth_certificate_number` varchar(50) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `previous_school` varchar(200) NOT NULL,
  `previous_school_address` varchar(200) NOT NULL,
  `last_exam_year` int(11) NOT NULL,
  `last_exam_grade` varchar(20) NOT NULL,
  `program_applying` varchar(50) NOT NULL,
  `combination` varchar(20) NOT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `father_phone` varchar(20) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `mother_phone` varchar(20) DEFAULT NULL,
  `guardian_name` varchar(100) DEFAULT NULL,
  `guardian_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_name` varchar(100) NOT NULL,
  `emergency_contact_phone` varchar(20) NOT NULL,
  `medical_conditions` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `special_needs` text DEFAULT NULL,
  `application_date` datetime NOT NULL,
  `status` enum('Pending','Under Review','Accepted','Rejected','Waitlisted') DEFAULT 'Pending',
  `review_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `review_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','replied') DEFAULT 'unread',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `full_name`, `phone_number`, `email`, `subject`, `message`, `status`, `ip_address`, `created_at`, `updated_at`, `school_id`) VALUES
(1, 'tazan', '07898888877', '', '', 'hello', 'unread', '::1', '2026-04-04 10:45:33', '2026-04-04 10:45:33', 1),
(2, 'tazan', '07898888877', '', '', 'hello', 'unread', '::1', '2026-04-04 10:48:29', '2026-04-04 10:48:29', 1),
(3, 'tazan', '07898888877', '', '', 'hello', 'unread', '::1', '2026-04-04 10:49:57', '2026-04-04 10:49:57', 1),
(4, 'tazan', '07898888877', '', '', 'hello', 'unread', '::1', '2026-04-04 10:51:02', '2026-04-04 10:51:02', 1);

-- --------------------------------------------------------

--
-- Table structure for table `current_students_by_class`
--

CREATE TABLE `current_students_by_class` (
  `id` int(11) DEFAULT NULL,
  `index_number` varchar(50) DEFAULT NULL,
  `full_name` varchar(302) DEFAULT NULL,
  `sex` enum('Male','Female') DEFAULT NULL,
  `combination` enum('HGE','HGL','HGK','HKL','KLF','EGM','HLF','HGF') DEFAULT NULL,
  `class` enum('Form Five','Form Six','Leavers','Graduated') DEFAULT NULL,
  `graduation_status` enum('Active','Form Five','Form Six','Graduated','Left') DEFAULT NULL,
  `promotion_status` enum('Not Promoted','Promoted to Form Six','Retained') DEFAULT NULL,
  `date_of_admission` date DEFAULT NULL,
  `parent_name` varchar(200) DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `dormitory_id` int(11) DEFAULT NULL,
  `dorm_name` varchar(50) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `room_label` varchar(20) DEFAULT NULL,
  `equipment_status` enum('Complete','Incomplete','None') DEFAULT NULL,
  `contribution_status` enum('Paid','Partially Paid','Not Paid') DEFAULT NULL,
  `is_leaver` tinyint(1) DEFAULT NULL,
  `year_left` year(4) DEFAULT NULL,
  `student_active` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discipline_records`
--

CREATE TABLE `discipline_records` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `list_type` enum('white','black') NOT NULL COMMENT 'White list = Good, Black list = Disciplinary issues',
  `record_type` enum('warning','appreciation','suspension','reprimand','commendation','expulsion') NOT NULL,
  `short_note` text NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_type` enum('image','video','audio','document','other') DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL COMMENT 'Size in bytes',
  `recorded_by` int(11) NOT NULL COMMENT 'Admin who recorded',
  `is_visible_to_student` tinyint(1) DEFAULT 1 COMMENT 'Can student see this?',
  `severity_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `follow_up_required` tinyint(1) DEFAULT 0,
  `follow_up_due_date` date DEFAULT NULL,
  `follow_up_completed` tinyint(1) DEFAULT 0,
  `follow_up_notes` text DEFAULT NULL,
  `status` enum('active','resolved','archived','deleted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `discipline_records`
--

INSERT INTO `discipline_records` (`id`, `student_id`, `list_type`, `record_type`, `short_note`, `file_path`, `file_type`, `file_name`, `file_size`, `recorded_by`, `is_visible_to_student`, `severity_level`, `follow_up_required`, `follow_up_due_date`, `follow_up_completed`, `follow_up_notes`, `status`, `created_at`, `updated_at`, `school_id`) VALUES
(0, 39, 'white', 'appreciation', 'good', NULL, NULL, NULL, NULL, 12, 1, 'high', 0, NULL, 0, NULL, 'active', '2026-03-08 01:35:53', '2026-03-08 01:35:53', 1),
(0, 221, 'black', 'reprimand', 'too bad', NULL, NULL, NULL, NULL, 12, 1, 'low', 0, NULL, 0, NULL, 'active', '2026-03-08 01:36:27', '2026-03-08 01:36:27', 1);

-- --------------------------------------------------------

--
-- Table structure for table `discipline_statistics`
--

CREATE TABLE `discipline_statistics` (
  `student_id` int(11) DEFAULT NULL,
  `student_name` varchar(201) DEFAULT NULL,
  `index_number` varchar(50) DEFAULT NULL,
  `class` enum('Form Five','Form Six','Leavers','Graduated') DEFAULT NULL,
  `combination` enum('HGE','HGL','HGK','HKL','KLF','EGM','HLF','HGF') DEFAULT NULL,
  `sex` enum('Male','Female') DEFAULT NULL,
  `is_leaver` tinyint(1) DEFAULT NULL,
  `student_status` tinyint(1) DEFAULT NULL,
  `blacklist_count` bigint(21) DEFAULT NULL,
  `whitelist_count` bigint(21) DEFAULT NULL,
  `critical_issues` bigint(21) DEFAULT NULL,
  `pending_followups` bigint(21) DEFAULT NULL,
  `last_blacklist_entry` timestamp NULL DEFAULT NULL,
  `last_whitelist_entry` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dormitories`
--

CREATE TABLE `dormitories` (
  `id` int(11) NOT NULL,
  `dorm_name` varchar(50) NOT NULL,
  `dorm_type` enum('Male','Female') NOT NULL,
  `rooms_count` int(11) NOT NULL,
  `capacity_per_room` int(11) NOT NULL,
  `total_capacity` int(11) NOT NULL,
  `current_occupancy` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `status` enum('Active','Full','Maintenance','Closed') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dormitories`
--

INSERT INTO `dormitories` (`id`, `dorm_name`, `dorm_type`, `rooms_count`, `capacity_per_room`, `total_capacity`, `current_occupancy`, `description`, `status`, `created_at`, `updated_at`, `school_id`) VALUES
(1, 'Safina', 'Female', 16, 10, 160, 0, 'Safina Female Dormitory - Rooms A1 to B8, 10 students per room', 'Active', '2026-02-07 07:03:59', '2026-04-21 18:49:15', 1),
(2, 'Samia', 'Female', 20, 6, 120, 0, 'Samia Female Dormitory - Rooms A1 to B10, 6 students per room', 'Active', '2026-02-07 07:03:59', '2026-02-08 18:34:16', 1),
(3, 'Magufuli', 'Male', 20, 6, 120, 0, 'Magufuli Male Dormitory - Rooms A1 to B10, 6 students per room', 'Active', '2026-02-07 07:03:59', '2026-02-07 09:36:30', 1),
(4, 'Sokoine', 'Male', 20, 6, 120, 0, 'Sokoine Male Dormitory - Rooms A1 to B10, 6 students per room', 'Active', '2026-02-07 07:03:59', '2026-02-07 09:03:34', 1),
(5, 'Mwandu', 'Male', 20, 6, 120, 0, 'Mwandu Male Dormitory - Rooms A1 to B10, 6 students per room', 'Active', '2026-02-07 07:03:59', '2026-03-08 02:40:47', 1),
(6, 'Nyerere', 'Male', 10, 12, 120, 0, 'Nyerere Male Dormitory - Rooms A1 to A10, 12 students per room', 'Active', '2026-02-07 07:03:59', '2026-02-07 09:03:57', 1),
(7, 'Kisutu Juu', 'Male', 5, 6, 30, 0, 'Kisutu Juu Male Dormitory - Rooms A1 to A5, 6 students per room', 'Active', '2026-02-07 07:03:59', '2026-02-08 18:32:01', 1),
(8, 'Kisutu Bombani', 'Male', 2, 12, 24, 1, 'Kisutu Bombani Male Dormitory - Rooms A1 to B1, 12 students per room', 'Active', '2026-02-07 07:03:59', '2026-05-21 11:56:34', 1),
(9, 'Kisutu Chini', 'Male', 2, 6, 12, 0, 'Kisutu Chini Male Dormitory - Rooms A1 to B1, 6 students per room', 'Active', '2026-02-07 07:03:59', '2026-02-09 17:49:47', 1),
(10, 'Kisutu Prison', 'Male', 7, 2, 14, 0, 'Kisutu Prison Male Dormitory - Rooms A1 to A7, 2 students per room', 'Active', '2026-02-07 07:03:59', '2026-02-09 17:49:53', 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `dormitory_occupancy_summary`
-- (See below for the actual view)
--
CREATE TABLE `dormitory_occupancy_summary` (
`id` int(11)
,`dorm_name` varchar(50)
,`dorm_type` enum('Male','Female')
,`rooms_count` int(11)
,`capacity_per_room` int(11)
,`total_capacity` int(11)
,`current_occupancy` int(11)
,`available_beds` bigint(12)
,`occupancy_percentage` decimal(16,2)
,`dormitory_status` enum('Active','Full','Maintenance','Closed')
,`description` text
,`total_rooms` bigint(21)
,`available_rooms` bigint(21)
,`full_rooms` bigint(21)
,`maintenance_rooms` bigint(21)
,`active_student_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `dormitory_rooms`
--

CREATE TABLE `dormitory_rooms` (
  `id` int(11) NOT NULL,
  `dormitory_id` int(11) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `room_label` varchar(20) NOT NULL,
  `capacity` int(11) NOT NULL,
  `current_occupancy` int(11) DEFAULT 0,
  `status` enum('Available','Full','Maintenance') DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dormitory_rooms`
--

INSERT INTO `dormitory_rooms` (`id`, `dormitory_id`, `room_number`, `room_label`, `capacity`, `current_occupancy`, `status`, `created_at`, `updated_at`, `school_id`) VALUES
(1, 1, 'A1', 'Safina A1', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-04-21 18:49:15', 1),
(2, 1, 'A2', 'Safina A2', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(3, 1, 'A3', 'Safina A3', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(4, 1, 'A4', 'Safina A4', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(5, 1, 'A5', 'Safina A5', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(6, 1, 'A6', 'Safina A6', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(7, 1, 'A7', 'Safina A7', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(8, 1, 'A8', 'Safina A8', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(9, 1, 'B1', 'Safina B1', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(10, 1, 'B2', 'Safina B2', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-02-08 18:33:02', 1),
(11, 1, 'B3', 'Safina B3', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(12, 1, 'B4', 'Safina B4', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(13, 1, 'B5', 'Safina B5', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(14, 1, 'B6', 'Safina B6', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(15, 1, 'B7', 'Safina B7', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(16, 1, 'B8', 'Safina B8', 10, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(17, 2, 'A1', 'Samia A1', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-08 18:34:08', 1),
(18, 2, 'A2', 'Samia A2', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(19, 2, 'A3', 'Samia A3', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(20, 2, 'A4', 'Samia A4', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(21, 2, 'A5', 'Samia A5', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(22, 2, 'A6', 'Samia A6', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(23, 2, 'A7', 'Samia A7', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(24, 2, 'A8', 'Samia A8', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(25, 2, 'A9', 'Samia A9', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(26, 2, 'A10', 'Samia A10', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(27, 2, 'B1', 'Samia B1', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(28, 2, 'B2', 'Samia B2', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(29, 2, 'B3', 'Samia B3', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(30, 2, 'B4', 'Samia B4', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(31, 2, 'B5', 'Samia B5', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-08 18:34:16', 1),
(32, 2, 'B6', 'Samia B6', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(33, 2, 'B7', 'Samia B7', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(34, 2, 'B8', 'Samia B8', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(35, 2, 'B9', 'Samia B9', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(36, 2, 'B10', 'Samia B10', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(37, 3, 'A1', 'Magufuli A1', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 09:36:30', 1),
(38, 3, 'A2', 'Magufuli A2', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(39, 3, 'A3', 'Magufuli A3', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(40, 3, 'A4', 'Magufuli A4', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(41, 3, 'A5', 'Magufuli A5', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(42, 3, 'A6', 'Magufuli A6', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(43, 3, 'A7', 'Magufuli A7', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(44, 3, 'A8', 'Magufuli A8', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(45, 3, 'A9', 'Magufuli A9', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(46, 3, 'A10', 'Magufuli A10', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(47, 3, 'B1', 'Magufuli B1', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(48, 3, 'B2', 'Magufuli B2', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(49, 3, 'B3', 'Magufuli B3', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(50, 3, 'B4', 'Magufuli B4', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(51, 3, 'B5', 'Magufuli B5', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(52, 3, 'B6', 'Magufuli B6', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(53, 3, 'B7', 'Magufuli B7', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(54, 3, 'B8', 'Magufuli B8', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(55, 3, 'B9', 'Magufuli B9', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(56, 3, 'B10', 'Magufuli B10', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(57, 4, 'A1', 'Sokoine A1', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:49:45', 1),
(58, 4, 'A2', 'Sokoine A2', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(59, 4, 'A3', 'Sokoine A3', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(60, 4, 'A4', 'Sokoine A4', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(61, 4, 'A5', 'Sokoine A5', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(62, 4, 'A6', 'Sokoine A6', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(63, 4, 'A7', 'Sokoine A7', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(64, 4, 'A8', 'Sokoine A8', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(65, 4, 'A9', 'Sokoine A9', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(66, 4, 'A10', 'Sokoine A10', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(67, 4, 'B1', 'Sokoine B1', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(68, 4, 'B2', 'Sokoine B2', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(69, 4, 'B3', 'Sokoine B3', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(70, 4, 'B4', 'Sokoine B4', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(71, 4, 'B5', 'Sokoine B5', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(72, 4, 'B6', 'Sokoine B6', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(73, 4, 'B7', 'Sokoine B7', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(74, 4, 'B8', 'Sokoine B8', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(75, 4, 'B9', 'Sokoine B9', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(76, 4, 'B10', 'Sokoine B10', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(77, 5, 'A1', 'Mwandu A1', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-08 18:32:23', 1),
(78, 5, 'A2', 'Mwandu A2', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(79, 5, 'A3', 'Mwandu A3', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(80, 5, 'A4', 'Mwandu A4', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(81, 5, 'A5', 'Mwandu A5', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(82, 5, 'A6', 'Mwandu A6', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(83, 5, 'A7', 'Mwandu A7', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(84, 5, 'A8', 'Mwandu A8', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(85, 5, 'A9', 'Mwandu A9', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(86, 5, 'A10', 'Mwandu A10', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(87, 5, 'B1', 'Mwandu B1', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-03-08 02:40:47', 1),
(88, 5, 'B2', 'Mwandu B2', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(89, 5, 'B3', 'Mwandu B3', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(90, 5, 'B4', 'Mwandu B4', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(91, 5, 'B5', 'Mwandu B5', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(92, 5, 'B6', 'Mwandu B6', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(93, 5, 'B7', 'Mwandu B7', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(94, 5, 'B8', 'Mwandu B8', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(95, 5, 'B9', 'Mwandu B9', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(96, 5, 'B10', 'Mwandu B10', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(97, 6, 'A1', 'Nyerere A1', 12, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:52:18', 1),
(98, 6, 'A2', 'Nyerere A2', 12, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(99, 6, 'A3', 'Nyerere A3', 12, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(100, 6, 'A4', 'Nyerere A4', 12, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(101, 6, 'A5', 'Nyerere A5', 12, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(102, 6, 'A6', 'Nyerere A6', 12, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(103, 6, 'A7', 'Nyerere A7', 12, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(104, 6, 'A8', 'Nyerere A8', 12, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(105, 6, 'A9', 'Nyerere A9', 12, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(106, 6, 'A10', 'Nyerere A10', 12, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(107, 7, 'A1', 'Kisutu Juu A1', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-08 18:32:01', 1),
(108, 7, 'A2', 'Kisutu Juu A2', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(109, 7, 'A3', 'Kisutu Juu A3', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(110, 7, 'A4', 'Kisutu Juu A4', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(111, 7, 'A5', 'Kisutu Juu A5', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(112, 8, 'A1', 'Kisutu Bombani A1', 12, 1, 'Available', '2026-02-07 07:03:59', '2026-05-21 11:56:34', 1),
(113, 8, 'B1', 'Kisutu Bombani B1', 12, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(114, 9, 'A1', 'Kisutu Chini A1', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-09 17:49:47', 1),
(115, 9, 'B1', 'Kisutu Chini B1', 6, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(116, 10, 'A1', 'Kisutu Prison A1', 2, 0, 'Available', '2026-02-07 07:03:59', '2026-02-09 17:49:53', 1),
(117, 10, 'A2', 'Kisutu Prison A2', 2, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(118, 10, 'A3', 'Kisutu Prison A3', 2, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(119, 10, 'A4', 'Kisutu Prison A4', 2, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(120, 10, 'A5', 'Kisutu Prison A5', 2, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(121, 10, 'A6', 'Kisutu Prison A6', 2, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1),
(122, 10, 'A7', 'Kisutu Prison A7', 2, 0, 'Available', '2026-02-07 07:03:59', '2026-02-07 07:03:59', 1);

--
-- Triggers `dormitory_rooms`
--
DELIMITER $$
CREATE TRIGGER `log_room_status_change` AFTER UPDATE ON `dormitory_rooms` FOR EACH ROW BEGIN
    -- Log only when status actually changes (not NULL)
    IF OLD.status != NEW.status AND OLD.status IS NOT NULL AND NEW.status IS NOT NULL THEN
        INSERT INTO room_status_logs (room_id, old_status, new_status, notes)
        VALUES (NEW.id, OLD.status, NEW.status, 'Status changed manually');
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_dormitory_occupancy` AFTER UPDATE ON `dormitory_rooms` FOR EACH ROW BEGIN
    DECLARE v_total_occupancy INT DEFAULT 0;
    DECLARE v_total_capacity INT DEFAULT 0;
    
    -- Only run if occupancy changed
    IF OLD.current_occupancy != NEW.current_occupancy THEN
        -- Calculate total occupancy for the dormitory (prevent negatives)
        SELECT COALESCE(SUM(GREATEST(current_occupancy, 0)), 0) INTO v_total_occupancy
        FROM dormitory_rooms
        WHERE dormitory_id = NEW.dormitory_id;
        
        -- Get total capacity
        SELECT total_capacity INTO v_total_capacity
        FROM dormitories
        WHERE id = NEW.dormitory_id;
        
        -- Update dormitory occupancy (ensure it doesn't exceed capacity)
        UPDATE dormitories 
        SET current_occupancy = LEAST(v_total_occupancy, v_total_capacity),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = NEW.dormitory_id;
        
        -- Update dormitory status
        UPDATE dormitories 
        SET status = CASE 
            WHEN v_total_occupancy >= v_total_capacity THEN 'Full'
            ELSE 'Active'
        END
        WHERE id = NEW.dormitory_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_room_status_auto` AFTER UPDATE ON `dormitory_rooms` FOR EACH ROW BEGIN
    -- Only run if occupancy changed
    IF OLD.current_occupancy != NEW.current_occupancy THEN
        -- Update room status based on occupancy (with bounds checking)
        IF NEW.current_occupancy >= NEW.capacity THEN
            UPDATE dormitory_rooms 
            SET status = 'Full',
                updated_at = CURRENT_TIMESTAMP
            WHERE id = NEW.id
            AND status != 'Maintenance';
            
            -- Log status change
            INSERT INTO room_status_logs (room_id, old_status, new_status, notes)
            VALUES (NEW.id, OLD.status, 'Full', CONCAT('Auto-changed: Room reached capacity (', NEW.current_occupancy, '/', NEW.capacity, ')'));
            
        ELSEIF NEW.current_occupancy < NEW.capacity AND NEW.status = 'Full' THEN
            UPDATE dormitory_rooms 
            SET status = 'Available',
                updated_at = CURRENT_TIMESTAMP
            WHERE id = NEW.id;
            
            -- Log status change
            INSERT INTO room_status_logs (room_id, old_status, new_status, notes)
            VALUES (NEW.id, 'Full', 'Available', CONCAT('Auto-changed: Room has space (', NEW.current_occupancy, '/', NEW.capacity, ')'));
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `equipment_transactions`
--

CREATE TABLE `equipment_transactions` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `transaction_type` enum('IN','OUT') NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_quantity` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `reason` text NOT NULL,
  `performed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_types`
--

CREATE TABLE `exam_types` (
  `id` int(11) NOT NULL,
  `exam_name` varchar(100) NOT NULL,
  `exam_code` varchar(20) NOT NULL,
  `term` varchar(20) DEFAULT NULL,
  `year` year(4) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `form_level` enum('Form Five','Form Six') NOT NULL DEFAULT 'Form Five',
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_types`
--

INSERT INTO `exam_types` (`id`, `exam_name`, `exam_code`, `term`, `year`, `is_active`, `created_at`, `description`, `created_by`, `updated_by`, `updated_at`, `form_level`, `school_id`) VALUES
(20, 'mid_exam', '89', 'Term 1', '2026', 1, '2026-04-03 05:09:06', '', 32, 32, '2026-05-21 06:22:40', 'Form Five', 1),
(21, 'school', 'yy', 'Term 1', '2026', 0, '2026-04-03 07:24:09', '', 32, 32, '2026-05-21 06:22:40', 'Form Five', 1),
(22, 'pre-necta', '777', 'Term 1', '2026', 0, '2026-04-05 06:56:14', '', 32, NULL, '2026-04-12 13:55:20', 'Form Six', 1),
(23, 'tzoneu', 'hgdrurt', 'Term 1', '2026', 0, '2026-04-12 13:30:49', '', 32, NULL, '2026-05-21 06:22:40', 'Form Five', 1),
(24, 'school_exam', 'uihui', 'Term 1', '2026', 1, '2026-04-12 13:55:14', '', 32, NULL, '2026-04-12 13:55:20', 'Form Six', 1);

-- --------------------------------------------------------

--
-- Table structure for table `food_stock`
--

CREATE TABLE `food_stock` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `date_added` date DEFAULT curdate(),
  `description` text DEFAULT NULL,
  `status` enum('available','low','out_of_stock') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `food_stock`
--
DELIMITER $$
CREATE TRIGGER `update_food_status` BEFORE UPDATE ON `food_stock` FOR EACH ROW BEGIN
    IF NEW.quantity <= 0 THEN
        SET NEW.status = 'out_of_stock';
    ELSEIF NEW.quantity <= 50 THEN
        SET NEW.status = 'low';
    ELSE
        SET NEW.status = 'available';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `food_stock_history`
--

CREATE TABLE `food_stock_history` (
  `id` int(11) NOT NULL,
  `food_id` int(11) NOT NULL,
  `old_quantity` decimal(10,2) DEFAULT NULL,
  `new_quantity` decimal(10,2) DEFAULT NULL,
  `change_type` enum('add','remove','adjust','initial') NOT NULL,
  `reason` text DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `form_five_promotion_candidates`
--

CREATE TABLE `form_five_promotion_candidates` (
  `id` int(11) DEFAULT NULL,
  `index_number` varchar(50) DEFAULT NULL,
  `full_name` varchar(201) DEFAULT NULL,
  `sex` enum('Male','Female') DEFAULT NULL,
  `combination` enum('HGE','HGL','HGK','HKL','KLF','EGM','HLF','HGF') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `date_of_admission` date DEFAULT NULL,
  `parent_name` varchar(200) DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `equipment_status` enum('Complete','Incomplete','None') DEFAULT NULL,
  `contribution_status` enum('Paid','Partially Paid','Not Paid') DEFAULT NULL,
  `blacklist_entries` bigint(21) DEFAULT NULL,
  `whitelist_entries` bigint(21) DEFAULT NULL,
  `is_leaver` tinyint(1) DEFAULT NULL,
  `graduation_status` enum('Active','Form Five','Form Six','Graduated','Left') DEFAULT NULL,
  `promotion_eligibility` varchar(22) DEFAULT NULL,
  `eligibility_notes` varchar(21) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `form_five_results`
--

CREATE TABLE `form_five_results` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exam_type_id` int(11) NOT NULL,
  `ac` int(11) DEFAULT NULL,
  `htm` int(11) DEFAULT NULL,
  `his` int(11) DEFAULT NULL,
  `geo` int(11) DEFAULT NULL,
  `kisw` int(11) DEFAULT NULL,
  `eng` int(11) DEFAULT NULL,
  `b_math` int(11) DEFAULT NULL,
  `adv_m` int(11) DEFAULT NULL,
  `eco` int(11) DEFAULT NULL,
  `fren` int(11) DEFAULT NULL,
  `total_points` int(11) DEFAULT NULL,
  `average` decimal(5,2) DEFAULT NULL,
  `division` varchar(20) DEFAULT NULL,
  `entered_by` int(11) DEFAULT NULL,
  `entered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `subject_teacher_id` int(11) DEFAULT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `form_five_results`
--

INSERT INTO `form_five_results` (`id`, `student_id`, `exam_type_id`, `ac`, `htm`, `his`, `geo`, `kisw`, `eng`, `b_math`, `adv_m`, `eco`, `fren`, `total_points`, `average`, `division`, `entered_by`, `entered_at`, `updated_at`, `subject_teacher_id`, `school_id`) VALUES
(67, 409, 20, 77, 99, NULL, NULL, NULL, NULL, 88, NULL, 55, NULL, 4, 79.75, 'Division I', 35, '2026-04-03 06:47:20', '2026-04-11 06:24:05', 35, 1),
(68, 27, 20, 66, 33, 77, 22, NULL, NULL, 76, NULL, 88, NULL, 10, 60.33, 'Division II', 35, '2026-04-03 06:49:28', '2026-04-11 06:24:02', 35, 1),
(69, 221, 20, 67, 76, 34, 45, NULL, NULL, 29, NULL, 54, NULL, 16, 50.83, 'Division III', 35, '2026-04-03 06:49:35', '2026-04-11 06:24:21', 35, 1),
(70, 251, 20, 44, 44, 67, 78, NULL, NULL, 41, NULL, 77, NULL, 7, 58.50, 'Division I', 35, '2026-04-03 06:50:33', '2026-04-03 11:46:01', 35, 1),
(71, 202, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 78, NULL, NULL, NULL, NULL, 35, '2026-04-12 10:58:34', '2026-04-12 10:58:34', NULL, 1),
(72, 82, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 89, NULL, NULL, NULL, NULL, 35, '2026-04-12 10:58:42', '2026-04-12 10:58:42', NULL, 1),
(73, 242, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 23, NULL, NULL, NULL, NULL, 35, '2026-04-12 10:58:43', '2026-04-12 10:58:43', NULL, 1),
(74, 24, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 45, NULL, NULL, NULL, NULL, 35, '2026-04-12 10:58:44', '2026-04-12 10:58:44', NULL, 1),
(75, 226, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 67, NULL, NULL, NULL, NULL, 35, '2026-04-12 10:58:44', '2026-04-12 10:58:44', NULL, 1),
(76, 34, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 97, NULL, NULL, NULL, NULL, 35, '2026-04-12 10:58:45', '2026-04-12 10:58:51', NULL, 1),
(77, 66, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 67, NULL, NULL, NULL, NULL, 35, '2026-04-12 10:58:55', '2026-04-12 10:58:55', NULL, 1),
(78, 186, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 90, NULL, NULL, NULL, NULL, 35, '2026-04-12 10:58:57', '2026-04-12 10:59:04', NULL, 1),
(79, 246, 20, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 32, '2026-04-12 13:52:36', '2026-04-12 13:52:36', NULL, 1),
(81, 409, 21, 55, 77, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 66.00, NULL, 32, '2026-04-20 13:23:18', '2026-04-20 14:19:48', NULL, 1),
(82, 27, 21, 56, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 56.00, NULL, 32, '2026-04-20 13:24:33', '2026-04-20 13:24:33', NULL, 1),
(83, 221, 21, 78, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 78.00, NULL, 32, '2026-04-20 13:24:34', '2026-04-20 13:24:34', NULL, 1),
(84, 251, 21, 67, 45, 55, 55, NULL, NULL, 55, NULL, 55, NULL, 12, 55.33, 'Division II', 32, '2026-04-20 13:24:35', '2026-04-20 16:40:11', NULL, 1),
(85, 28, 21, 12, 45, 55, 55, NULL, 67, NULL, NULL, NULL, NULL, 11, 46.80, 'Division II', 32, '2026-04-20 13:24:36', '2026-04-20 16:40:29', NULL, 1),
(86, 20, 21, 67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 67.00, NULL, 32, '2026-04-20 13:24:37', '2026-04-20 14:20:41', NULL, 1),
(87, 62, 21, 56, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 56.00, NULL, 32, '2026-04-20 13:24:39', '2026-04-20 13:24:39', NULL, 1),
(88, 222, 21, 78, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 78.00, NULL, 32, '2026-04-20 13:24:41', '2026-04-20 13:24:41', NULL, 1),
(89, 182, 21, 45, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 45.00, NULL, 32, '2026-04-20 13:24:42', '2026-04-20 13:24:42', NULL, 1),
(90, 38, 21, 23, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 23.00, NULL, 32, '2026-04-20 13:24:44', '2026-04-20 13:24:44', NULL, 1),
(91, 246, 21, 23, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 23.00, NULL, 32, '2026-04-20 13:24:45', '2026-04-20 13:24:45', NULL, 1),
(92, 206, 21, 34, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 34.00, NULL, 32, '2026-04-20 13:24:46', '2026-04-20 13:24:46', NULL, 1),
(94, 21, 21, 67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 67.00, NULL, 32, '2026-04-20 13:24:48', '2026-04-20 13:24:48', NULL, 1),
(95, 183, 21, 78, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 78.00, NULL, 32, '2026-04-20 13:24:50', '2026-04-20 13:24:50', NULL, 1),
(96, 79, 21, 89, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 89.00, NULL, 32, '2026-04-20 13:24:51', '2026-04-20 13:24:51', NULL, 1),
(97, 199, 21, 67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 67.00, NULL, 32, '2026-04-20 13:24:52', '2026-04-20 15:48:48', NULL, 1),
(98, 239, 21, 12, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12.00, NULL, 32, '2026-04-20 13:24:53', '2026-04-20 13:24:53', NULL, 1),
(99, 63, 21, 12, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12.00, NULL, 32, '2026-04-20 13:24:55', '2026-04-20 13:24:55', NULL, 1),
(100, 223, 21, 34, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 34.00, NULL, 32, '2026-04-20 13:24:56', '2026-04-20 13:24:56', NULL, 1),
(101, 31, 21, 55, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 55.00, NULL, 32, '2026-04-20 13:24:57', '2026-04-20 13:24:57', NULL, 1),
(102, 207, 21, 66, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 66.00, NULL, 32, '2026-04-20 13:24:59', '2026-04-20 13:24:59', NULL, 1),
(103, 87, 21, 77, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 77.00, NULL, 32, '2026-04-20 13:25:00', '2026-04-20 13:25:00', NULL, 1),
(104, 247, 21, 68, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 68.00, NULL, 32, '2026-04-20 13:25:02', '2026-04-20 14:21:42', NULL, 1),
(105, 200, 21, 67, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 67.00, NULL, 26, '2026-04-20 14:19:56', '2026-04-20 14:19:56', NULL, 1),
(106, 22, 21, 38, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 38.00, NULL, 26, '2026-04-20 14:19:59', '2026-04-20 14:20:01', NULL, 1),
(107, 240, 21, 90, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 90.00, NULL, 26, '2026-04-20 14:20:05', '2026-04-20 15:52:13', NULL, 1),
(108, 18, 20, 45, 55, 66, 77, NULL, NULL, 88, NULL, 99, NULL, 6, 71.67, 'Division I', 26, '2026-05-21 06:23:10', '2026-05-21 06:23:20', NULL, 1),
(109, 9, 20, 22, 33, 44, 55, NULL, NULL, 66, NULL, 77, NULL, 11, 49.50, 'Division II', 26, '2026-05-21 06:23:24', '2026-05-21 06:23:27', NULL, 1),
(110, 189, 20, 88, 99, 55, 55, NULL, NULL, 67, NULL, 45, NULL, 13, 68.17, 'Division III', 26, '2026-05-21 06:23:27', '2026-05-21 06:23:34', NULL, 1),
(111, 69, 20, 78, 34, 23, 34, NULL, NULL, 45, NULL, 34, NULL, 21, 41.33, 'Division 0', 26, '2026-05-21 06:23:35', '2026-05-21 06:23:40', NULL, 1),
(112, 93, 20, 56, 56, 67, 22, NULL, NULL, 34, NULL, 45, NULL, 15, 46.67, 'Division III', 26, '2026-05-21 06:23:41', '2026-05-21 06:23:50', NULL, 1),
(113, 61, 20, 56, 67, 78, 89, NULL, NULL, 90, NULL, 33, NULL, 10, 68.83, 'Division II', 26, '2026-05-21 06:23:50', '2026-05-21 06:23:55', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `form_six_results`
--

CREATE TABLE `form_six_results` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exam_type_id` int(11) NOT NULL,
  `ac` int(11) DEFAULT NULL,
  `htm` int(11) DEFAULT NULL,
  `b_math` int(11) DEFAULT NULL,
  `his` int(11) DEFAULT NULL,
  `geo` int(11) DEFAULT NULL,
  `kisw` int(11) DEFAULT NULL,
  `eng` int(11) DEFAULT NULL,
  `adv_m` int(11) DEFAULT NULL,
  `eco` int(11) DEFAULT NULL,
  `fren` int(11) DEFAULT NULL,
  `total_points` int(11) DEFAULT NULL,
  `average` decimal(5,2) DEFAULT NULL,
  `division` varchar(20) DEFAULT NULL,
  `entered_by` int(11) DEFAULT NULL,
  `entered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `subject_teacher_id` int(11) DEFAULT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `form_six_results`
--

INSERT INTO `form_six_results` (`id`, `student_id`, `exam_type_id`, `ac`, `htm`, `b_math`, `his`, `geo`, `kisw`, `eng`, `adv_m`, `eco`, `fren`, `total_points`, `average`, `division`, `entered_by`, `entered_at`, `updated_at`, `subject_teacher_id`, `school_id`) VALUES
(59, 18, 22, 44, NULL, 99, 34, 56, NULL, NULL, NULL, 55, NULL, 15, 57.60, 'Division III', 36, '2026-04-05 06:58:42', '2026-04-12 10:43:20', NULL, 1),
(60, 9, 22, NULL, NULL, NULL, 45, 82, NULL, NULL, NULL, 66, NULL, 9, 64.33, 'Division I', 36, '2026-04-05 06:58:55', '2026-04-09 04:36:33', NULL, 1),
(61, 189, 22, NULL, NULL, NULL, 45, 36, NULL, NULL, NULL, 55, NULL, 15, 45.33, 'Division III', 36, '2026-04-05 06:58:58', '2026-04-09 04:36:34', NULL, 1),
(62, 69, 22, NULL, NULL, NULL, 56, 95, NULL, NULL, NULL, 44, NULL, 10, 65.00, 'Division II', 36, '2026-04-05 07:01:10', '2026-04-09 04:36:35', NULL, 1),
(63, 93, 22, NULL, NULL, NULL, 67, 62, NULL, NULL, NULL, 55, NULL, 10, 61.33, 'Division II', 36, '2026-04-05 07:01:12', '2026-04-09 04:36:36', NULL, 1),
(64, 61, 22, NULL, NULL, NULL, 78, 77, NULL, NULL, NULL, 77, NULL, 6, 77.33, 'Division I', 36, '2026-04-05 07:01:14', '2026-04-09 04:36:37', NULL, 1),
(67, 205, 22, NULL, NULL, NULL, 67, 26, NULL, NULL, NULL, 44, NULL, 15, 45.67, 'Division III', 36, '2026-04-05 07:01:19', '2026-04-09 04:36:40', NULL, 1),
(68, 85, 22, NULL, NULL, NULL, 89, 52, NULL, NULL, NULL, 55, NULL, 9, 65.33, 'Division I', 36, '2026-04-05 07:01:22', '2026-04-09 04:36:41', NULL, 1),
(69, 53, 22, NULL, NULL, NULL, 34, 55, NULL, NULL, NULL, 66, NULL, 14, 51.67, 'Division III', 36, '2026-04-05 07:01:23', '2026-04-09 04:36:44', NULL, 1),
(70, 190, 22, NULL, NULL, NULL, 23, 100, NULL, NULL, NULL, NULL, NULL, 8, 61.50, 'Division I', 36, '2026-04-05 07:01:25', '2026-04-09 04:35:05', NULL, 1),
(71, 230, 22, NULL, NULL, NULL, 45, 52, NULL, NULL, NULL, NULL, NULL, 9, 48.50, 'Division I', 36, '2026-04-05 07:01:47', '2026-04-09 04:35:06', NULL, 1),
(72, 13, 22, NULL, NULL, NULL, 56, 85, NULL, NULL, NULL, NULL, NULL, 5, 70.50, 'Division I', 36, '2026-04-05 07:01:48', '2026-04-09 04:35:06', NULL, 1),
(73, 46, 22, NULL, NULL, NULL, 55, 44, NULL, NULL, NULL, NULL, NULL, 9, 49.50, 'Division I', 36, '2026-04-05 07:01:51', '2026-04-09 04:35:07', NULL, 1),
(74, 70, 22, NULL, NULL, NULL, 66, 11, NULL, NULL, NULL, NULL, NULL, 10, 38.50, 'Division II', 36, '2026-04-05 07:01:52', '2026-04-09 04:35:08', NULL, 1),
(75, 54, 22, NULL, NULL, NULL, 77, 22, NULL, NULL, NULL, NULL, NULL, 9, 49.50, 'Division I', 36, '2026-04-05 07:01:54', '2026-04-09 04:35:09', NULL, 1),
(76, 214, 22, NULL, NULL, NULL, 88, 33, NULL, NULL, NULL, NULL, NULL, 8, 60.50, 'Division I', 36, '2026-04-05 07:01:55', '2026-04-09 04:35:09', NULL, 1),
(77, 238, 22, NULL, NULL, NULL, 99, 58, NULL, NULL, NULL, NULL, NULL, 5, 78.50, 'Division I', 36, '2026-04-05 07:01:57', '2026-04-09 04:35:11', NULL, 1),
(78, 198, 22, NULL, NULL, NULL, 34, 91, NULL, NULL, NULL, NULL, NULL, 8, 62.50, 'Division I', 36, '2026-04-05 07:02:00', '2026-04-09 04:35:13', NULL, 1),
(79, 94, 22, NULL, NULL, NULL, 56, 79, NULL, NULL, NULL, NULL, NULL, 6, 67.50, 'Division I', 36, '2026-04-05 07:02:02', '2026-04-09 04:35:14', NULL, 1),
(80, 78, 22, NULL, NULL, NULL, 78, 53, NULL, NULL, NULL, NULL, NULL, 6, 65.50, 'Division I', 36, '2026-04-05 07:02:05', '2026-04-09 04:35:15', NULL, 1),
(81, 1, 22, NULL, NULL, NULL, 34, 23, NULL, NULL, NULL, NULL, NULL, 14, 28.50, 'Division III', 36, '2026-04-05 07:02:07', '2026-04-09 04:35:16', NULL, 1),
(82, 14, 22, NULL, NULL, NULL, 56, 18, NULL, NULL, NULL, NULL, NULL, 11, 37.00, 'Division II', 36, '2026-04-05 07:02:13', '2026-04-09 04:35:17', NULL, 1),
(83, 47, 22, NULL, NULL, NULL, 78, 54, NULL, NULL, NULL, NULL, NULL, 6, 66.00, 'Division I', 36, '2026-04-05 07:02:23', '2026-04-09 04:35:18', NULL, 1),
(84, 231, 22, NULL, NULL, NULL, 90, 12, NULL, NULL, NULL, NULL, NULL, 8, 51.00, 'Division I', 36, '2026-04-05 07:02:25', '2026-04-09 04:35:19', NULL, 1),
(85, 39, 22, NULL, NULL, NULL, 99, 42, NULL, NULL, NULL, NULL, NULL, 6, 70.50, 'Division I', 36, '2026-04-05 07:02:27', '2026-04-09 04:35:20', NULL, 1),
(86, 71, 22, NULL, NULL, NULL, 88, 73, NULL, NULL, NULL, NULL, NULL, 3, 80.50, 'Division I', 36, '2026-04-05 07:02:29', '2026-04-09 04:35:21', NULL, 1),
(87, 191, 22, NULL, NULL, NULL, 89, 85, NULL, NULL, NULL, NULL, NULL, 2, 87.00, NULL, 36, '2026-04-05 07:02:30', '2026-04-09 04:35:22', NULL, 1),
(88, 95, 22, NULL, NULL, NULL, 77, 53, NULL, NULL, NULL, NULL, NULL, 6, 65.00, 'Division I', 36, '2026-04-05 07:02:32', '2026-04-09 04:35:23', NULL, 1),
(89, 55, 22, NULL, NULL, NULL, 66, 52, NULL, NULL, NULL, NULL, NULL, 7, 59.00, 'Division I', 36, '2026-04-05 07:02:34', '2026-04-09 04:35:24', NULL, 1),
(90, 215, 22, NULL, NULL, NULL, 55, 55, NULL, NULL, NULL, NULL, NULL, 8, 55.00, 'Division I', 36, '2026-04-05 07:02:41', '2026-04-09 04:35:25', NULL, 1),
(91, 42, 22, NULL, NULL, NULL, NULL, 100, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:02:42', '2026-04-05 07:02:42', NULL, 1),
(92, 90, 22, NULL, NULL, NULL, NULL, 55, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:02:44', '2026-04-05 07:02:44', NULL, 1),
(93, 210, 22, NULL, NULL, NULL, NULL, 85, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:02:46', '2026-04-05 07:02:46', NULL, 1),
(94, 74, 22, NULL, NULL, NULL, NULL, 36, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:02:47', '2026-04-05 07:02:47', NULL, 1),
(95, 234, 22, NULL, NULL, NULL, NULL, 24, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:02:49', '2026-04-05 07:02:49', NULL, 1),
(96, 194, 22, NULL, NULL, NULL, NULL, 85, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:02:51', '2026-04-05 07:02:51', NULL, 1),
(97, 50, 22, NULL, NULL, NULL, NULL, 100, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:02:52', '2026-04-05 07:02:52', NULL, 1),
(98, 58, 22, NULL, NULL, NULL, NULL, 95, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:02:55', '2026-04-05 07:02:55', NULL, 1),
(99, 7, 22, NULL, NULL, NULL, NULL, 93, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:02:57', '2026-04-05 07:02:57', NULL, 1),
(100, 98, 22, NULL, NULL, NULL, NULL, 22, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:02:58', '2026-04-05 07:02:58', NULL, 1),
(101, 218, 22, NULL, NULL, NULL, NULL, 55, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:03:00', '2026-04-05 07:03:00', NULL, 1),
(102, 92, 22, NULL, NULL, NULL, NULL, 66, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:03:01', '2026-04-05 07:03:01', NULL, 1),
(103, 212, 22, NULL, NULL, NULL, NULL, 55, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:03:03', '2026-04-05 07:03:03', NULL, 1),
(104, 44, 22, NULL, NULL, NULL, NULL, 86, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:03:05', '2026-04-05 07:03:05', NULL, 1),
(105, 15, 22, NULL, NULL, NULL, NULL, 56, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:03:07', '2026-04-05 07:03:07', NULL, 1),
(106, 236, 22, NULL, NULL, NULL, NULL, 58, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:03:08', '2026-04-05 07:03:08', NULL, 1),
(107, 76, 22, NULL, NULL, NULL, NULL, 86, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:03:10', '2026-04-05 07:03:10', NULL, 1),
(108, 52, 22, NULL, NULL, NULL, NULL, 85, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:03:11', '2026-04-05 07:03:11', NULL, 1),
(109, 196, 22, NULL, NULL, NULL, NULL, 100, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 36, '2026-04-05 07:03:13', '2026-04-05 07:03:30', NULL, 1),
(110, 11, 22, NULL, NULL, NULL, 44, NULL, NULL, NULL, NULL, NULL, NULL, 5, 44.00, 'Division I', 32, '2026-04-09 04:35:26', '2026-04-09 04:35:26', NULL, 1),
(111, 192, 22, NULL, NULL, NULL, 33, NULL, NULL, NULL, NULL, NULL, NULL, 7, 33.00, 'Division I', 32, '2026-04-09 04:35:27', '2026-04-09 04:35:27', NULL, 1),
(112, 232, 22, NULL, NULL, NULL, 55, NULL, NULL, NULL, NULL, NULL, NULL, 4, 55.00, 'Division I', 32, '2026-04-09 04:35:28', '2026-04-09 04:35:28', NULL, 1),
(113, 48, 22, NULL, NULL, NULL, 66, NULL, NULL, NULL, NULL, NULL, NULL, 3, 66.00, 'Division I', 32, '2026-04-09 04:35:29', '2026-04-09 04:35:29', NULL, 1),
(114, 72, 22, NULL, NULL, NULL, 67, NULL, NULL, NULL, NULL, NULL, NULL, 3, 67.00, 'Division I', 32, '2026-04-09 04:35:30', '2026-04-09 04:35:30', NULL, 1),
(115, 40, 22, NULL, NULL, NULL, 66, NULL, NULL, NULL, NULL, NULL, NULL, 3, 66.00, 'Division I', 32, '2026-04-09 04:35:31', '2026-04-09 04:35:31', NULL, 1),
(116, 216, 22, NULL, NULL, NULL, 66, NULL, NULL, NULL, NULL, NULL, NULL, 3, 66.00, 'Division I', 32, '2026-04-09 04:35:32', '2026-04-09 04:35:32', NULL, 1),
(117, 184, 22, NULL, NULL, NULL, 66, NULL, NULL, NULL, NULL, NULL, NULL, 3, 66.00, 'Division I', 32, '2026-04-09 04:35:34', '2026-04-09 04:35:34', NULL, 1),
(118, 96, 22, NULL, NULL, NULL, 45, NULL, NULL, NULL, NULL, NULL, NULL, 5, 45.00, 'Division I', 32, '2026-04-09 04:35:35', '2026-04-09 04:35:35', NULL, 1),
(119, 56, 22, NULL, NULL, NULL, 34, NULL, NULL, NULL, NULL, NULL, NULL, 7, 34.00, 'Division I', 32, '2026-04-09 04:35:36', '2026-04-09 04:35:36', NULL, 1),
(120, 233, 22, NULL, NULL, NULL, NULL, NULL, 55, NULL, NULL, NULL, NULL, 4, 55.00, 'Division I', 32, '2026-04-09 04:35:37', '2026-04-09 04:35:37', NULL, 1),
(121, 18, 24, 55, 55, 55, 55, 55, NULL, NULL, NULL, 55, NULL, 12, 55.00, 'Division II', 32, '2026-04-12 13:55:28', '2026-04-12 13:55:44', NULL, 1),
(122, 9, 24, 89, 67, 66, 45, 78, NULL, NULL, NULL, 88, NULL, 8, 72.17, 'Division I', 35, '2026-04-12 14:06:35', '2026-04-20 17:27:14', NULL, 1),
(123, 189, 24, NULL, NULL, 45, NULL, NULL, NULL, NULL, NULL, 77, NULL, NULL, NULL, NULL, 35, '2026-04-12 14:06:41', '2026-04-12 14:07:29', NULL, 1),
(124, 69, 24, NULL, NULL, 34, NULL, NULL, NULL, NULL, NULL, 56, NULL, NULL, NULL, NULL, 35, '2026-04-12 14:06:49', '2026-04-12 14:07:30', NULL, 1),
(125, 93, 24, NULL, NULL, 77, NULL, NULL, NULL, NULL, NULL, 34, NULL, NULL, NULL, NULL, 35, '2026-04-12 14:06:50', '2026-04-12 14:07:32', NULL, 1),
(126, 61, 24, NULL, NULL, 66, NULL, NULL, NULL, NULL, NULL, 55, NULL, NULL, NULL, NULL, 35, '2026-04-12 14:06:51', '2026-04-12 14:07:33', NULL, 1),
(129, 205, 24, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 90, NULL, NULL, NULL, NULL, 35, '2026-04-12 14:07:37', '2026-04-12 14:07:37', NULL, 1),
(130, 409, 24, 88, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 88.00, NULL, 32, '2026-05-25 06:36:13', '2026-05-25 06:36:13', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `game_types`
--

CREATE TABLE `game_types` (
  `id` int(11) NOT NULL,
  `game_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `color_code` varchar(7) DEFAULT '#3B9DB3'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `game_types`
--

INSERT INTO `game_types` (`id`, `game_name`, `description`, `status`, `created_at`, `color_code`) VALUES
(1, 'Football', 'Soccer/Football matches', 'Active', '2026-03-31 16:51:42', '#28a745'),
(2, 'Netball', 'Netball matches', 'Active', '2026-03-31 16:51:42', '#dc3545'),
(3, 'Handball', 'Handball matches', 'Active', '2026-03-31 16:51:42', '#ffc107'),
(4, 'Volleyball', 'Volleyball matches', 'Active', '2026-03-31 16:51:42', '#17a2b8');

-- --------------------------------------------------------

--
-- Table structure for table `leaver_equipment_history`
--

CREATE TABLE `leaver_equipment_history` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `index_number` varchar(50) NOT NULL,
  `class` varchar(50) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `equipment_data` text NOT NULL,
  `left_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `left_reason` varchar(255) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `library_assignments`
--

CREATE TABLE `library_assignments` (
  `id` int(11) NOT NULL,
  `user_type` enum('staff','student') NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `book_title` varchar(255) NOT NULL,
  `book_number` varchar(50) NOT NULL,
  `quantity` varchar(20) NOT NULL,
  `assigned_date` date NOT NULL,
  `short_note` text DEFAULT NULL,
  `status` enum('borrowed','returned') DEFAULT 'borrowed',
  `return_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_assignments`
--

INSERT INTO `library_assignments` (`id`, `user_type`, `user_id`, `user_name`, `book_title`, `book_number`, `quantity`, `assigned_date`, `short_note`, `status`, `return_date`, `created_at`, `updated_at`, `school_id`) VALUES
(6, 'staff', 26, 'Franc peter', 'history', '12', '5', '2026-03-14', 'none', 'borrowed', NULL, '2026-03-14 13:07:53', '2026-03-14 13:07:53', 1),
(7, 'student', 74, 'Abdallah Mpemba', 'geography', '1', '2 books, 2 pastpaper', '2026-03-14', '', 'borrowed', NULL, '2026-03-14 13:09:58', '2026-03-14 13:09:58', 1);

-- --------------------------------------------------------

--
-- Table structure for table `login_notifications`
--

CREATE TABLE `login_notifications` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `login_time` varchar(50) NOT NULL,
  `login_date` varchar(100) NOT NULL,
  `identifier` varchar(255) DEFAULT NULL,
  `email_sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_assignments`
--

CREATE TABLE `maintenance_assignments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL COMMENT 'Reference to students table',
  `item_id` int(11) NOT NULL COMMENT 'Reference to maintenance_items table',
  `assignment_type` varchar(20) NOT NULL COMMENT 'Type of assignment: table, chair',
  `assigned_by` int(11) DEFAULT NULL COMMENT 'Admin who made the assignment',
  `assigned_date` date NOT NULL,
  `due_date` date DEFAULT NULL COMMENT 'Expected return date',
  `status` varchar(20) DEFAULT 'active' COMMENT 'active, returned, cancelled',
  `return_date` date DEFAULT NULL COMMENT 'Date when item was returned',
  `return_condition` varchar(20) DEFAULT NULL COMMENT 'Condition when returned: good, damaged, lost',
  `return_notes` text DEFAULT NULL COMMENT 'Notes about the return',
  `notes` text DEFAULT NULL COMMENT 'General notes about the assignment',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_leaver` tinyint(1) DEFAULT 0,
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_assignments`
--

INSERT INTO `maintenance_assignments` (`id`, `student_id`, `item_id`, `assignment_type`, `assigned_by`, `assigned_date`, `due_date`, `status`, `return_date`, `return_condition`, `return_notes`, `notes`, `created_at`, `updated_at`, `is_leaver`, `school_id`) VALUES
(1, 61, 1, 'table', 12, '2026-02-07', '2026-03-09', 'returned', '2026-02-07', 'good', '', '', '2026-02-07 13:18:01', '2026-02-07 13:19:46', 0, 1),
(2, 61, 2, 'chair', 12, '2026-02-07', '2026-03-09', 'returned', '2026-02-07', 'good', '', '', '2026-02-07 13:18:01', '2026-02-07 13:19:57', 0, 1),
(3, 61, 5, 'table', 14, '2026-02-07', '2026-02-07', 'returned', '2026-02-07', 'good', 'Auto-returned: Transferred from Form Five', '', '2026-02-07 14:34:04', '2026-02-07 14:34:57', 0, 1),
(4, 61, 4, 'chair', 14, '2026-02-07', '2026-02-07', 'returned', '2026-02-07', 'good', 'Auto-returned: Transferred from Form Five', '', '2026-02-07 14:34:04', '2026-02-07 14:34:57', 0, 1),
(5, 61, 1, 'table', 14, '2026-02-07', '2026-03-09', 'returned', '2026-02-07', 'good', 'Auto-returned: Transferred from Form Five', '', '2026-02-07 14:41:47', '2026-02-07 14:45:58', 0, 1),
(6, 61, 2, 'chair', 14, '2026-02-07', '2026-03-09', 'returned', '2026-02-07', 'good', 'Auto-returned: Transferred from Form Five', '', '2026-02-07 14:41:47', '2026-02-07 14:45:58', 0, 1),
(7, 18, 1, 'table', 12, '2026-02-09', '2026-03-11', 'returned', '2026-02-09', 'good', '', '', '2026-02-09 07:28:36', '2026-02-09 17:57:27', 0, 1),
(8, 18, 2, 'chair', 12, '2026-02-09', '2026-03-11', 'returned', '2026-02-09', 'good', 'Force returned by admin', '', '2026-02-09 07:28:36', '2026-02-09 17:57:05', 0, 1),
(9, 246, 1, 'table', 12, '2026-03-08', '2026-04-07', 'returned', '2026-03-08', 'good', '', '', '2026-03-08 02:35:00', '2026-03-08 02:37:27', 0, 1),
(10, 246, 2, 'chair', 12, '2026-03-08', '2026-04-07', 'returned', '2026-03-08', 'good', '', '', '2026-03-08 02:35:00', '2026-03-08 02:37:06', 0, 1),
(11, 246, 1, 'table', 12, '2026-03-08', '2027-05-10', 'returned', '2026-03-08', 'good', '', '', '2026-03-08 02:38:15', '2026-03-08 02:38:40', 0, 1),
(12, 246, 2, 'chair', 12, '2026-03-08', '2027-05-10', 'returned', '2026-03-08', 'good', '', '', '2026-03-08 02:38:15', '2026-03-08 02:38:43', 0, 1),
(13, 409, 1, 'table', 32, '2026-04-21', '2026-05-21', 'returned', '2026-04-21', 'good', 'Auto-returned: Student deactivated', '', '2026-04-21 18:26:58', '2026-04-21 18:30:59', 0, 1),
(14, 409, 2, 'chair', 32, '2026-04-21', '2026-05-21', 'returned', '2026-04-21', 'good', 'Auto-returned: Student deactivated', '', '2026-04-21 18:26:58', '2026-04-21 18:30:59', 0, 1),
(15, 18, 1, 'table', 13, '2026-05-17', '2026-06-16', 'active', NULL, NULL, NULL, '', '2026-05-17 15:08:36', '2026-05-17 15:08:36', 0, 1),
(16, 18, 2, 'chair', 13, '2026-05-17', '2026-06-16', 'active', NULL, NULL, NULL, '', '2026-05-17 15:08:36', '2026-05-17 15:08:36', 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_items`
--

CREATE TABLE `maintenance_items` (
  `id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL COMMENT 'Unique item identifier (e.g., TBL-001, CHR-001)',
  `item_type` varchar(20) NOT NULL COMMENT 'Type of item: table, chair, other',
  `description` text DEFAULT NULL COMMENT 'Item description',
  `location` varchar(100) DEFAULT NULL COMMENT 'Current location of the item',
  `status` varchar(20) DEFAULT 'available' COMMENT 'Item status: available, assigned, damaged, under_maintenance, lost',
  `signed_at` date DEFAULT NULL COMMENT 'Date when item was added to inventory',
  `last_maintenance` date DEFAULT NULL COMMENT 'Date of last maintenance',
  `notes` text DEFAULT NULL COMMENT 'Additional notes about the item',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_items`
--

INSERT INTO `maintenance_items` (`id`, `item_code`, `item_type`, `description`, `location`, `status`, `signed_at`, `last_maintenance`, `notes`, `created_at`, `updated_at`, `school_id`) VALUES
(1, 't556', 'table', '', 'dar es salaam', 'assigned', '2026-02-07', NULL, '', '2026-02-07 13:15:13', '2026-05-17 15:08:36', 1),
(2, 'c44', 'chair', '', '', 'assigned', '2026-02-07', NULL, '', '2026-02-07 13:15:39', '2026-05-17 15:08:36', 1),
(3, 't559', 'table', '', '', 'available', '2026-02-07', NULL, '', '2026-02-07 13:15:55', '2026-02-07 13:15:55', 1),
(4, 'c45', 'chair', '', '', 'available', '2026-02-07', NULL, '', '2026-02-07 13:16:09', '2026-02-09 17:57:35', 1),
(5, 't557', 'table', '', '', 'available', '2026-02-07', NULL, '', '2026-02-07 13:16:23', '2026-02-09 17:57:33', 1),
(6, 'c48', 'chair', '', '', 'available', '2026-02-07', NULL, '', '2026-02-07 13:16:44', '2026-02-07 13:16:44', 1);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_logs`
--

CREATE TABLE `maintenance_logs` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL COMMENT 'Reference to maintenance_items table',
  `log_type` varchar(50) NOT NULL COMMENT 'Type of log: assignment, return, damage, repair, maintenance',
  `user_type` varchar(20) DEFAULT NULL COMMENT 'Type of user: student, staff, admin',
  `user_id` int(11) DEFAULT NULL COMMENT 'Reference to students or admins table',
  `admin_id` int(11) NOT NULL COMMENT 'Admin who performed the action',
  `description` text NOT NULL COMMENT 'Description of the action',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_logs`
--

INSERT INTO `maintenance_logs` (`id`, `item_id`, `log_type`, `user_type`, `user_id`, `admin_id`, `description`, `created_at`, `school_id`) VALUES
(23, 1, 'assignment', 'student', 18, 12, 'Assigned t556 (table) to student: JANETH WECH', '2026-02-09 07:28:36', 1),
(24, 2, 'assignment', 'student', 18, 12, 'Assigned c44 (chair) to student: JANETH WECH', '2026-02-09 07:28:36', 1),
(25, 5, 'assignment', 'staff', 15, 12, 'Assigned t557 (table) to staff: aujenia leo', '2026-02-09 17:54:02', 1),
(26, 4, 'assignment', 'staff', 15, 12, 'Assigned c45 (chair) to staff: aujenia leo', '2026-02-09 17:54:02', 1),
(27, 2, 'return', 'student', 18, 12, 'Force returned c44 from student: JANETH WECH by admin', '2026-02-09 17:57:05', 1),
(28, 1, 'return', 'student', 18, 12, 'Returned t556 from student: JANETH WECH. Condition: good', '2026-02-09 17:57:27', 1),
(29, 5, 'return', 'staff', 15, 12, 'Returned t557 from staff: aujenia leo. Condition: good', '2026-02-09 17:57:33', 1),
(30, 4, 'return', 'staff', 15, 12, 'Returned c45 from staff: aujenia leo. Condition: good', '2026-02-09 17:57:35', 1),
(31, 1, 'assignment', 'student', 246, 12, 'Assigned t556 (table) to student: Samuel Mkumbo', '2026-03-08 02:35:00', 1),
(32, 2, 'assignment', 'student', 246, 12, 'Assigned c44 (chair) to student: Samuel Mkumbo', '2026-03-08 02:35:00', 1),
(33, 2, 'return', 'student', 246, 12, 'Returned c44 from student: Samuel Mkumbo. Condition: good', '2026-03-08 02:37:06', 1),
(34, 1, 'return', 'student', 246, 12, 'Returned t556 from student: Samuel Mkumbo. Condition: good', '2026-03-08 02:37:27', 1),
(35, 1, 'assignment', 'student', 246, 12, 'Assigned t556 (table) to student: Samuel Mkumbo', '2026-03-08 02:38:15', 1),
(36, 2, 'assignment', 'student', 246, 12, 'Assigned c44 (chair) to student: Samuel Mkumbo', '2026-03-08 02:38:15', 1),
(37, 1, 'return', 'student', 246, 12, 'Returned t556 from student: Samuel Mkumbo. Condition: good', '2026-03-08 02:38:40', 1),
(38, 2, 'return', 'student', 246, 12, 'Returned c44 from student: Samuel Mkumbo. Condition: good', '2026-03-08 02:38:43', 1),
(39, 1, 'assignment', 'student', 409, 32, 'Assigned t556 (table) to student: princess toy', '2026-04-21 18:26:58', 1),
(40, 2, 'assignment', 'student', 409, 32, 'Assigned c44 (chair) to student: princess toy', '2026-04-21 18:26:58', 1),
(41, 1, 'assignment', 'student', 18, 13, 'Assigned t556 (table) to student: JANETH WECH', '2026-05-17 15:08:36', 1),
(42, 2, 'assignment', 'student', 18, 13, 'Assigned c44 (chair) to student: JANETH WECH', '2026-05-17 15:08:36', 1);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_staff_assignments`
--

CREATE TABLE `maintenance_staff_assignments` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL COMMENT 'Reference to admins table',
  `item_id` int(11) NOT NULL COMMENT 'Reference to maintenance_items table',
  `assignment_type` varchar(20) NOT NULL COMMENT 'Type of assignment: table, chair',
  `assigned_by` int(11) DEFAULT NULL COMMENT 'Admin who made the assignment',
  `assigned_date` date NOT NULL,
  `due_date` date DEFAULT NULL COMMENT 'Expected return date',
  `status` varchar(20) DEFAULT 'active' COMMENT 'active, returned, cancelled',
  `return_date` date DEFAULT NULL COMMENT 'Date when item was returned',
  `return_condition` varchar(20) DEFAULT NULL COMMENT 'Condition when returned: good, damaged, lost',
  `return_notes` text DEFAULT NULL COMMENT 'Notes about the return',
  `notes` text DEFAULT NULL COMMENT 'General notes about the assignment',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_staff_assignments`
--

INSERT INTO `maintenance_staff_assignments` (`id`, `staff_id`, `item_id`, `assignment_type`, `assigned_by`, `assigned_date`, `due_date`, `status`, `return_date`, `return_condition`, `return_notes`, `notes`, `created_at`, `updated_at`, `school_id`) VALUES
(7, 15, 5, 'table', 12, '2026-02-09', '2026-02-09', 'returned', '2026-02-09', 'good', '', '', '2026-02-09 17:54:01', '2026-02-09 17:57:33', 1),
(8, 15, 4, 'chair', 12, '2026-02-09', '2026-02-09', 'returned', '2026-02-09', 'good', '', '', '2026-02-09 17:54:02', '2026-02-09 17:57:35', 1);

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `game_type_id` int(11) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `group_name` varchar(10) DEFAULT NULL,
  `match_number` int(11) DEFAULT NULL,
  `team1_id` int(11) NOT NULL,
  `team2_id` int(11) NOT NULL,
  `team1_score` int(11) DEFAULT 0,
  `team2_score` int(11) DEFAULT 0,
  `winner_team_id` int(11) DEFAULT NULL,
  `match_date` date NOT NULL,
  `match_time` time NOT NULL,
  `venue` varchar(100) DEFAULT NULL,
  `status` enum('Scheduled','In Progress','Completed','Postponed','Cancelled') DEFAULT 'Scheduled',
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `matches`
--

INSERT INTO `matches` (`id`, `tournament_id`, `game_type_id`, `stage_id`, `group_name`, `match_number`, `team1_id`, `team2_id`, `team1_score`, `team2_score`, `winner_team_id`, `match_date`, `match_time`, `venue`, `status`, `description`, `created_by`, `created_at`, `updated_at`, `school_id`) VALUES
(8, 4, 1, 1, 'A', NULL, 1, 8, 1, 1, NULL, '0000-00-00', '12:06:00', NULL, 'Completed', '', 32, '2026-03-31 19:06:52', '2026-03-31 19:07:06', 1),
(9, 4, 1, 1, 'B', NULL, 14, 10, 2, 4, 10, '0000-00-00', '22:28:00', NULL, 'Completed', '', 32, '2026-03-31 19:25:37', '2026-03-31 19:25:49', 1),
(10, 4, 1, 1, 'A', NULL, 5, 7, 9, 3, 5, '0000-00-00', '09:07:00', NULL, 'Completed', '', 32, '2026-04-01 06:05:44', '2026-04-01 06:06:31', 1),
(11, 4, 1, 2, '', NULL, 8, 6, 1, 1, NULL, '0000-00-00', '09:24:00', NULL, 'Completed', '', 32, '2026-04-01 06:22:47', '2026-04-01 12:13:38', 1),
(12, 4, 1, 2, '', NULL, 4, 11, 4, 5, 11, '0000-00-00', '09:00:00', NULL, 'Completed', 'goood', 32, '2026-04-01 06:58:04', '2026-04-01 09:36:51', 1),
(17, 4, 1, 3, NULL, NULL, 5, 1, 2, 0, 5, '2026-04-16', '08:00:00', NULL, 'Completed', NULL, 32, '2026-04-01 10:58:36', '2026-04-01 12:13:26', 1),
(18, 4, 1, 3, NULL, NULL, 10, 11, 3, 2, 10, '2026-04-16', '10:00:00', NULL, 'Completed', NULL, 32, '2026-04-01 10:58:36', '2026-04-01 12:13:15', 1);

-- --------------------------------------------------------

--
-- Table structure for table `matches_schedule`
--

CREATE TABLE `matches_schedule` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `group_name` varchar(10) DEFAULT NULL,
  `match_number` int(11) DEFAULT NULL,
  `team1_id` int(11) NOT NULL,
  `team2_id` int(11) NOT NULL,
  `match_date` date DEFAULT NULL,
  `match_time` time DEFAULT NULL,
  `status` enum('Scheduled','Completed') DEFAULT 'Scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `match_officials`
--

CREATE TABLE `match_officials` (
  `id` int(11) NOT NULL,
  `match_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `role` enum('Referee','Assistant Referee 1','Assistant Referee 2','Scorekeeper','Timekeeper') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `match_statistics`
--

CREATE TABLE `match_statistics` (
  `id` int(11) NOT NULL,
  `match_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `participant_id` int(11) DEFAULT NULL,
  `participant_type` enum('Student','Staff') DEFAULT NULL,
  `event_type` enum('Goal','Yellow Card','Red Card','Substitution','Injury') NOT NULL,
  `event_time` time NOT NULL,
  `event_minute` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `non_staff`
--

CREATE TABLE `non_staff` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `sex` enum('Male','Female') NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone_number` varchar(15) NOT NULL,
  `nida` varchar(20) DEFAULT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `employment_date` date NOT NULL,
  `contract_type` enum('Permanent','Contract','Temporary','Volunteer') DEFAULT 'Permanent',
  `salary_scale` varchar(50) DEFAULT NULL,
  `work_location` varchar(200) DEFAULT NULL,
  `emergency_contact_name` varchar(200) DEFAULT NULL,
  `emergency_contact_phone` varchar(15) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_image` varchar(500) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1 COMMENT '1=Active, 0=Inactive',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `non_staff`
--

INSERT INTO `non_staff` (`id`, `first_name`, `middle_name`, `last_name`, `sex`, `email`, `phone_number`, `nida`, `position`, `department`, `employment_date`, `contract_type`, `salary_scale`, `work_location`, `emergency_contact_name`, `emergency_contact_phone`, `address`, `profile_image`, `status`, `notes`, `created_at`, `updated_at`, `school_id`) VALUES
(1, 'muyovozi', 'wiston', 'muyovozi', 'Female', 'muyovozimuyovozi1@gmail.com', '255619844080', NULL, 'mlinzi', '', '2026-04-05', 'Contract', '', '', '', '', '', NULL, 1, '', '2026-04-05 05:18:31', '2026-04-09 04:41:07', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL COMMENT 'Who created the notification',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_type` enum('image','video','audio','document','archive','other') DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL COMMENT 'Size in bytes',
  `visibility` enum('public','private') DEFAULT 'public',
  `priority` enum('normal','important','starred') DEFAULT 'normal',
  `status` enum('active','archived','deleted') DEFAULT 'active',
  `is_starred` tinyint(1) DEFAULT 0,
  `views_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `admin_id`, `title`, `description`, `file_path`, `file_type`, `file_name`, `file_size`, `visibility`, `priority`, `status`, `is_starred`, `views_count`, `created_at`, `updated_at`, `school_id`) VALUES
(1, 12, 'welcome all in my views', 'nice meetings', '../uploads/notifications/695ec31b65467_muyovozi.png', 'image', '695ec31b65467_muyovozi.png', 517797, 'public', 'starred', 'active', 1, 32, '2026-01-07 14:33:31', '2026-04-04 16:35:36', 1),
(3, 11, 'hello', '', '../uploads/notifications/695ec94967511_Muyovozi_High_School_-_Google_Chrome_1_7_2026_1_05_52_PM.png', 'image', '695ec94967511_Muyovozi_High_School_-_Google_Chrome_1_7_2026_1_05_52_PM.png', 205346, 'private', 'normal', 'active', 0, 2, '2026-01-07 14:59:53', '2026-01-07 15:17:40', 1),
(8, 12, 'walimu wote tukutaane', '', '', '', '', 0, 'public', 'important', 'active', 1, 5, '2026-01-23 14:29:24', '2026-03-28 08:32:35', 1),
(1, 12, 'welcome all in my views', 'nice meetings', '../uploads/notifications/695ec31b65467_muyovozi.png', 'image', '695ec31b65467_muyovozi.png', 517797, 'public', 'starred', 'active', 1, 32, '2026-01-07 14:33:31', '2026-04-04 16:35:36', 1),
(3, 11, 'hello', '', '../uploads/notifications/695ec94967511_Muyovozi_High_School_-_Google_Chrome_1_7_2026_1_05_52_PM.png', 'image', '695ec94967511_Muyovozi_High_School_-_Google_Chrome_1_7_2026_1_05_52_PM.png', 205346, 'private', 'normal', 'active', 0, 2, '2026-01-07 14:59:53', '2026-01-07 15:17:40', 1),
(8, 12, 'walimu wote tukutaane', '', '', '', '', 0, 'public', 'important', 'active', 1, 5, '2026-01-23 14:29:24', '2026-03-28 08:32:35', 1),
(0, 32, 'hello', 'welcome all student\r\n', '', '', '', 0, 'public', 'normal', 'active', 0, 2, '2026-04-04 16:36:04', '2026-04-05 06:52:42', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notification_views`
--

CREATE TABLE `notification_views` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `viewer_id` int(11) DEFAULT NULL COMMENT 'Admin ID if logged in, NULL for guests',
  `viewer_type` enum('admin','guest') DEFAULT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_views`
--

INSERT INTO `notification_views` (`id`, `notification_id`, `viewer_id`, `viewer_type`, `viewed_at`, `school_id`) VALUES
(1, 1, 12, 'admin', '2026-01-07 14:33:56', 1),
(3, 1, 11, 'admin', '2026-01-07 14:55:37', 1),
(4, 3, 12, 'admin', '2026-01-07 15:00:40', 1),
(5, 3, 11, 'admin', '2026-01-07 15:17:40', 1),
(12, 1, 14, 'admin', '2026-01-09 11:57:23', 1),
(14, 8, 12, 'admin', '2026-01-26 10:00:54', 1),
(0, 1, 221, '', '2026-03-08 01:51:28', 1),
(0, 1, 221, '', '2026-03-08 01:51:28', 1),
(0, 8, 221, '', '2026-03-08 01:51:32', 1),
(0, 1, 246, '', '2026-03-08 02:04:01', 1),
(0, 1, 246, '', '2026-03-08 02:04:01', 1),
(0, 1, 246, '', '2026-03-08 02:30:38', 1),
(0, 1, 246, '', '2026-03-08 02:30:38', 1),
(0, 1, 53, '', '2026-03-08 05:39:25', 1),
(0, 1, 53, '', '2026-03-08 05:39:26', 1),
(0, 1, 53, '', '2026-03-08 05:39:33', 1),
(0, 1, 53, '', '2026-03-08 05:39:34', 1),
(0, 1, 53, '', '2026-03-08 05:39:54', 1),
(0, 1, 53, '', '2026-03-08 05:39:54', 1),
(0, 1, 251, '', '2026-03-10 13:32:11', 1),
(0, 1, 251, '', '2026-03-10 13:32:11', 1),
(0, 1, 251, '', '2026-03-10 13:32:17', 1),
(0, 1, 251, '', '2026-03-10 13:32:17', 1),
(0, 1, 251, '', '2026-03-10 13:33:12', 1),
(0, 1, 251, '', '2026-03-10 13:33:12', 1),
(0, 1, 408, '', '2026-03-10 15:46:17', 1),
(0, 1, 408, '', '2026-03-10 15:46:17', 1),
(0, 1, 408, '', '2026-03-10 15:47:42', 1),
(0, 1, 408, '', '2026-03-10 15:47:42', 1),
(0, 1, 251, '', '2026-03-11 08:57:10', 1),
(0, 1, 251, '', '2026-03-11 08:57:10', 1),
(0, 1, 251, '', '2026-03-11 17:42:04', 1),
(0, 1, 251, '', '2026-03-11 17:42:04', 1),
(0, 8, 29, 'admin', '2026-03-13 14:01:21', 1),
(0, 8, 14, 'admin', '2026-03-14 08:31:26', 1),
(0, 8, 32, 'admin', '2026-03-28 08:32:35', 1),
(0, 1, 408, '', '2026-04-03 21:00:27', 1),
(0, 1, 408, '', '2026-04-03 21:00:27', 1),
(0, 1, 32, 'admin', '2026-04-04 16:35:36', 1),
(0, 0, 32, 'admin', '2026-04-04 22:05:56', 1),
(0, 0, 14, 'admin', '2026-04-05 06:52:42', 1);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_type` enum('staff','student') NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `token` varchar(100) NOT NULL,
  `otp` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_type`, `user_id`, `email`, `phone`, `token`, `otp`, `expires_at`, `used`, `created_at`, `school_id`) VALUES
(32, 'staff', 15, 'jen@gmail.com', '255714343162', 'e41f088ac1b4f42e9b3a514ddd90c7dc1d4cf0aa03a1bd52d33d964e7ef0e03f', '835243', '2026-03-13 13:38:37', 0, '2026-03-13 10:28:37', 1),
(40, 'staff', 12, 'tz@gmail.com', '255619844080', 'bdac6ae760bc56d20ca64e7f04c55668b383c38f9c9d404a26801034c7cecbe1', '277241', '2026-03-13 14:41:40', 0, '2026-03-13 11:31:40', 1),
(42, 'staff', 31, 'tz@gmail.com', '255714343162', '4a407081ff0bab3f8a9b30f90caf45007466b13f2941bcdf4a4b1412943cb21f', '626597', '2026-04-03 21:24:30', 1, '2026-04-03 18:14:30', 1),
(43, '', 32, 'tzone@gmail.com', '255783626760', 'd86071b6ba81b8a8cd802ddf2097e6ab0b72229947f2ac484b8df4f2f352d109', '', '2026-06-02 12:13:12', 0, '2026-06-02 08:58:12', 1),
(44, '', 1, 'tzone1@gmail.com', '255714343162', '78ebb8d0b4b7c73c59c8d9be2e857e82bcff59a18dd7139ec67c8d3a47129dac', '', '2026-06-02 12:13:26', 0, '2026-06-02 08:58:26', 1),
(45, '', 1, 'tzone1@gmail.com', '255714343162', '8b74785562d2757efe3a9bbccf6ae0532d35a2cf23d5229d800654bdbeba0aaa', '', '2026-06-02 12:13:30', 0, '2026-06-02 08:58:30', 1),
(46, '', 1, 'tzone1@gmail.com', '255714343162', '6f2e6cdd0f01e4a77a079b0b6ee8a68f974a679a7e2159c941f64fe2c4e7c1b4', '', '2026-06-02 12:13:31', 0, '2026-06-02 08:58:31', 1),
(47, '', 1, 'tzone1@gmail.com', '255714343162', '5ae7f50d8acce05a4d5d7cacd6e33462fabeb75971dd5f14e04d9e37334ded4f', '', '2026-06-02 12:13:32', 0, '2026-06-02 08:58:32', 1),
(48, '', 1, 'tzone1@gmail.com', '255714343162', '79e226037edd97d175dfb26a7cd59e3f5739379b70a08224ab6131c4fca64c28', '', '2026-06-02 12:13:33', 0, '2026-06-02 08:58:33', 1),
(49, '', 1, 'tzone1@gmail.com', '255714343162', 'f500e0c862bd4e830672a87c33ed6370c79ce96b5ecad184efb87177e775e6e2', '', '2026-06-02 12:13:34', 0, '2026-06-02 08:58:34', 1),
(50, '', 1, 'tzone1@gmail.com', '255714343162', '00ad7bc27a9f0ee0b9a3f9466c3e9a11733ec207673dbd4a7533fe395fa0ca85', '', '2026-06-02 12:13:34', 0, '2026-06-02 08:58:34', 1),
(51, '', 1, 'tzone1@gmail.com', '255714343162', '34e610ed23ec3431aabfba0b6379accbcf415b02764a83dbde8aeec785f328e9', '', '2026-06-02 12:13:34', 0, '2026-06-02 08:58:34', 1),
(52, '', 1, 'tzone1@gmail.com', '255714343162', '054a9018adf6db14cb20ac1bdf729f11df34b1ebe974ccc0905e17103ada8409', '', '2026-06-02 12:13:36', 0, '2026-06-02 08:58:36', 1),
(53, '', 1, 'tzone1@gmail.com', '255714343162', '35641f93398aa6fec26b607bd285b64a46c1fab7f99bdf8d304c490bbb1bc65e', '', '2026-06-02 12:13:36', 0, '2026-06-02 08:58:36', 1),
(54, '', 1, 'tzone1@gmail.com', '255714343162', 'c0e0ce7230b549ccc9b28d8659a9cec5fda3f9aa9987e35ff66fba1f69e689ed', '', '2026-06-02 12:13:37', 0, '2026-06-02 08:58:37', 1),
(55, '', 1, 'tzone1@gmail.com', '255714343162', '80578a1cfbd582318710bdd01243dd2ec23cf1359c606cb262fc221713a57934', '', '2026-06-02 12:13:44', 0, '2026-06-02 08:58:44', 1),
(56, '', 1, 'tzone1@gmail.com', '255714343162', '5edac2096df260fa951a076f24370704cb4712fec20a8790b538c70503a180f3', '', '2026-06-02 12:13:45', 0, '2026-06-02 08:58:45', 1),
(57, '', 1, 'tzone1@gmail.com', '255714343162', 'c52f0a5bed7be99eb6a111ad73e9cd980e4fa0a08e02d1319766675212930b07', '', '2026-06-02 12:14:00', 0, '2026-06-02 08:59:00', 1),
(58, '', 1, 'tzone1@gmail.com', '255714343162', 'bad0ac821e32c27f008b7bbfb7ac9b4a7269a8c06fa2f39227ffbc6dbc326ee4', '', '2026-06-02 12:15:31', 0, '2026-06-02 09:00:31', 1),
(59, '', 32, 'tzone@gmail.com', '255783626760', '4f7672e6c5247e876b1cc50d949108678f5780e84dad68405844e49028253545', '', '2026-06-02 12:15:37', 0, '2026-06-02 09:00:37', 1),
(60, '', 32, 'tzone@gmail.com', '255783626760', '399ba147a07edb97dd9e0b5795d3cb5234224e86dec217f40f8fd505c6081a97', '', '2026-06-02 12:15:39', 0, '2026-06-02 09:00:39', 1),
(61, '', 32, 'tzone@gmail.com', '255783626760', 'e8565f982179908a537aa0fb30c0d942bfef2857d6ecb9e61aee46bcd6f25de8', '', '2026-06-02 12:15:40', 0, '2026-06-02 09:00:40', 1),
(62, '', 32, 'tzone@gmail.com', '255783626760', 'ea1da1a2f94fb07ee8bc03902f0c54034a1de63709b482671c727e50e8a971a5', '', '2026-06-02 12:15:46', 0, '2026-06-02 09:00:46', 1),
(63, '', 1, 'tzone1@gmail.com', '255714343162', '6deda9b4157c2341bd4e2c27c2ff40610ea8d888c6cd06a4c039c789c3e016e8', '', '2026-06-02 12:15:55', 0, '2026-06-02 09:00:55', 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `payment_summary`
-- (See below for the actual view)
--
CREATE TABLE `payment_summary` (
`student_id` int(11)
,`total_paid` decimal(32,2)
,`last_payment_date` date
,`payment_status` varchar(9)
);

-- --------------------------------------------------------

--
-- Table structure for table `productions`
--

CREATE TABLE `productions` (
  `id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `production_type` varchar(100) NOT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'TZS',
  `production_date` date NOT NULL,
  `short_note` text DEFAULT NULL,
  `uses` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_categories`
--

CREATE TABLE `production_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_categories`
--

INSERT INTO `production_categories` (`id`, `category_name`, `description`, `unit`, `status`, `created_at`, `updated_at`, `created_by`, `school_id`) VALUES
(1, 'shop', 'School Shop Products', 'items', 1, '2026-02-09 22:03:50', '2026-02-09 22:31:40', NULL, 1),
(2, 'farm', 'Farm and Plantation Products', 'kg', 1, '2026-02-09 22:03:50', '2026-02-09 22:31:40', NULL, 1),
(3, 'beekeeping', 'Honey and Bee Products', 'liters', 1, '2026-02-09 22:03:50', '2026-02-09 22:31:40', NULL, 1),
(4, 'soap', 'Soap Making Products', 'pieces', 1, '2026-02-09 22:03:50', '2026-02-09 22:31:40', NULL, 1),
(5, 'fish', 'Fish Farming', 'kg', 1, '2026-02-09 22:03:50', '2026-02-09 22:31:40', NULL, 1),
(6, 'hen', 'Poultry and Hen Products', 'pieces', 1, '2026-02-09 22:03:50', '2026-02-09 22:31:40', NULL, 1),
(7, 'garden', 'School Garden Products', 'kg', 1, '2026-02-09 22:03:50', '2026-02-09 22:31:40', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `production_logs`
--

CREATE TABLE `production_logs` (
  `id` int(11) NOT NULL,
  `production_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_uses`
--

CREATE TABLE `production_uses` (
  `id` int(11) NOT NULL,
  `production_id` int(11) NOT NULL,
  `use_description` text NOT NULL,
  `use_date` date NOT NULL,
  `used_quantity` decimal(10,2) NOT NULL,
  `used_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ps_documents`
--

CREATE TABLE `ps_documents` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `short_note` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` enum('image','video','audio','document','spreadsheet','presentation','archive','pdf','text','other') DEFAULT 'document',
  `file_extension` varchar(20) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL COMMENT 'Size in bytes',
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL COMMENT 'Admin ID who uploaded',
  `uploader_role` varchar(100) DEFAULT NULL,
  `uploader_name` varchar(200) DEFAULT NULL,
  `visibility` enum('public','private','staff_only') DEFAULT 'staff_only',
  `allow_feedback` tinyint(1) DEFAULT 1,
  `needs_ps_review` tinyint(1) NOT NULL DEFAULT 0,
  `ps_status` enum('pending','approved','rejected','changes_requested') DEFAULT 'approved',
  `ps_reviewed_by` int(11) DEFAULT NULL,
  `ps_reviewed_at` timestamp NULL DEFAULT NULL,
  `ps_comment` text DEFAULT NULL,
  `feedback_count` int(11) DEFAULT 0,
  `download_count` int(11) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `status` enum('active','archived','deleted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ps_document_feedback`
--

CREATE TABLE `ps_document_feedback` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `commenter_id` int(11) NOT NULL COMMENT 'Admin ID',
  `commenter_name` varchar(200) NOT NULL,
  `commenter_role` varchar(100) DEFAULT NULL,
  `comment` text NOT NULL,
  `parent_comment_id` int(11) DEFAULT NULL COMMENT 'For replies to comments',
  `status` enum('active','deleted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ps_document_logs`
--

CREATE TABLE `ps_document_logs` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(200) NOT NULL,
  `user_role` varchar(100) DEFAULT NULL,
  `action` enum('view','download','print','feedback') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ps_notifications`
--

CREATE TABLE `ps_notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('ps_review','document_review','feedback','system') DEFAULT 'system',
  `user_id` int(11) DEFAULT NULL COMMENT 'Target user ID',
  `target_role` varchar(100) DEFAULT NULL COMMENT 'Target role (e.g., PS)',
  `document_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `status` enum('unread','read','archived') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `results_auto_save`
--

CREATE TABLE `results_auto_save` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exam_type_id` int(11) NOT NULL,
  `subject_code` varchar(20) DEFAULT NULL,
  `marks` int(11) DEFAULT NULL,
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `session_id` varchar(100) DEFAULT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `results_entry_sessions`
--

CREATE TABLE `results_entry_sessions` (
  `id` int(11) NOT NULL,
  `exam_type_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `room_availability_view`
-- (See below for the actual view)
--
CREATE TABLE `room_availability_view` (
`room_id` int(11)
,`dorm_name` varchar(50)
,`dorm_type` enum('Male','Female')
,`room_number` varchar(10)
,`room_label` varchar(20)
,`capacity` int(11)
,`current_occupancy` int(11)
,`available_beds` bigint(12)
,`room_status` enum('Available','Full','Maintenance')
,`dormitory_status` enum('Active','Full','Maintenance','Closed')
,`occupancy_status` varchar(18)
,`active_students_in_room` bigint(21)
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `room_status_logs`
--

CREATE TABLE `room_status_logs` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `old_status` enum('Available','Full','Maintenance') DEFAULT NULL,
  `new_status` enum('Available','Full','Maintenance') NOT NULL,
  `changed_by` int(11) DEFAULT NULL COMMENT 'Admin ID',
  `notes` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE `schools` (
  `id` int(11) NOT NULL,
  `school_code` varchar(20) NOT NULL,
  `school_name` varchar(200) NOT NULL,
  `school_motto` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `status` enum('Active','Suspended','Expired','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `system_theme` text DEFAULT NULL COMMENT 'JSON of default theme colors',
  `system_preferences` text DEFAULT NULL COMMENT 'JSON of default user preferences',
  `allowed_customization` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Which settings users can customize' CHECK (json_valid(`allowed_customization`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`id`, `school_code`, `school_name`, `school_motto`, `logo_path`, `address`, `phone`, `email`, `status`, `created_at`, `updated_at`, `system_theme`, `system_preferences`, `allowed_customization`) VALUES
(1, 'MVZ001', 'Muyovozi High School', 'Education For Life', NULL, NULL, '0714343162', NULL, 'Active', '2026-06-02 08:43:38', '2026-06-02 12:11:33', '{\"primary\":\"#3B9DB3\",\"primary_dark\":\"#2d7c8f\",\"primary_light\":\"#8bc5d6\",\"text\":\"#333333\",\"text_light\":\"#666666\",\"border\":\"#e0e0e0\",\"success\":\"#28a745\",\"danger\":\"#dc3545\",\"warning\":\"#ffc107\",\"info\":\"#17a2b8\",\"coral\":\"#FF7F50\",\"forest_green\":\"#2E7D32\",\"lime_green\":\"#63E07E\",\"sky_blue\":\"#66d9ff\",\"aqua_blue\":\"#4dd2ff\"}', '{\"sidebar_collapsed\":\"0\",\"font_size\":\"16\",\"animations\":\"1\",\"compact_mode\":\"0\",\"background_opacity\":\"65\",\"background_option\":\"image\",\"animation_speed\":\"normal\"}', '{\"theme\":true,\"preferences\":true}'),
(2, 'jhfr89fu8', 'hello highi school', 'help for help', NULL, 'dar', '0765432123', 'kfwekrle@gmail.com', 'Active', '2026-06-02 10:58:55', '2026-06-02 10:58:55', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `shule_salama_comments`
--

CREATE TABLE `shule_salama_comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `commenter_id` int(11) NOT NULL,
  `commenter_type` enum('admin','teacher','student') NOT NULL,
  `comment` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shule_salama_posts`
--

CREATE TABLE `shule_salama_posts` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL COMMENT 'Who created the post',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_type` enum('image','video','audio','document','archive','other') DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL COMMENT 'Size in bytes',
  `visibility` enum('public','staff_only','students_only') DEFAULT 'public',
  `priority` enum('normal','important','critical','emergency') DEFAULT 'normal',
  `status` enum('active','archived','deleted') DEFAULT 'active',
  `views_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shule_salama_views`
--

CREATE TABLE `shule_salama_views` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `viewer_id` int(11) DEFAULT NULL COMMENT 'Admin ID if logged in, NULL for guests',
  `viewer_type` enum('admin','teacher','student','guest') DEFAULT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `recipient_count` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(20) NOT NULL,
  `response` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cost` decimal(10,0) NOT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sports_equipment`
--

CREATE TABLE `sports_equipment` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `min_quantity` int(11) NOT NULL DEFAULT 5,
  `short_note` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT 0.00,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sports_equipment`
--

INSERT INTO `sports_equipment` (`id`, `item_name`, `category`, `unit`, `quantity`, `min_quantity`, `short_note`, `image_path`, `purchase_date`, `purchase_price`, `is_archived`, `created_by`, `created_at`, `updated_at`, `school_id`) VALUES
(1, 'mipira', 'Football', 'ball', 15, 2, '', NULL, '2026-04-01', 0.00, 1, 32, '2026-04-01 11:33:34', '2026-04-23 20:44:35', 1),
(2, 'game', 'Football', 'ball', 0, 2, '', NULL, '2026-04-01', 0.00, 1, 32, '2026-04-01 11:57:33', '2026-04-01 11:58:52', 1),
(3, 'game', 'Football', 'set', 15, 5, '', NULL, '2026-04-23', 0.00, 1, 32, '2026-04-23 19:00:04', '2026-04-23 20:44:35', 1),
(4, 'game', 'Football', 'ball', 0, 5, '', NULL, NULL, 0.00, 1, 32, '2026-04-23 20:19:51', '2026-04-23 21:12:24', 1);

-- --------------------------------------------------------

--
-- Table structure for table `sports_history`
--

CREATE TABLE `sports_history` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `tournament_name` varchar(100) NOT NULL,
  `game_type_id` int(11) DEFAULT NULL,
  `season` varchar(20) DEFAULT NULL,
  `year` year(4) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_by` int(11) DEFAULT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `store_summary`
-- (See below for the actual view)
--
CREATE TABLE `store_summary` (
`id` int(11)
,`tool_name` varchar(100)
,`total_quantity` int(11)
,`issued_to_students` int(11)
,`used_quantity` int(11)
,`available_quantity` int(11)
,`unit` varchar(50)
,`usage_percentage` decimal(15,1)
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `store_tools`
--

CREATE TABLE `store_tools` (
  `id` int(11) NOT NULL,
  `tool_name` varchar(100) NOT NULL,
  `total_quantity` int(11) NOT NULL DEFAULT 0,
  `issued_to_students` int(11) NOT NULL DEFAULT 0,
  `used_quantity` int(11) NOT NULL DEFAULT 0,
  `available_quantity` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(50) DEFAULT 'piece',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_tools`
--

INSERT INTO `store_tools` (`id`, `tool_name`, `total_quantity`, `issued_to_students`, `used_quantity`, `available_quantity`, `unit`, `created_at`, `updated_at`, `school_id`) VALUES
(1, 'Learn Paper', 0, 21, 0, 25, 'papers', '2026-04-22 13:06:17', '2026-04-22 14:24:06', 1),
(2, 'Buckets', 0, 12, 2, 12, 'buckets', '2026-04-22 13:06:17', '2026-04-23 18:26:58', 1),
(3, 'Hoe', 0, 5, 0, 6, 'hoe', '2026-04-22 13:06:17', '2026-04-23 18:26:40', 1),
(4, 'Chair', 0, 5, 0, 6, 'chair', '2026-04-22 13:06:17', '2026-04-22 14:07:53', 1),
(5, 'Soft Broom', 0, 5, 0, 6, 'broom', '2026-04-22 13:06:17', '2026-04-23 18:26:40', 1),
(6, 'Hard Broom', 0, 5, 0, 6, 'broom', '2026-04-22 13:06:17', '2026-04-23 18:26:40', 1),
(7, 'Chelewa Broom', 0, 4, 0, 5, 'broom', '2026-04-22 13:06:17', '2026-04-23 18:26:40', 1),
(8, 'Slasher', 0, 5, 0, 6, 'slasher', '2026-04-22 13:06:17', '2026-04-23 18:26:40', 1),
(9, 'Lek', 0, 2, 0, 2, 'lek', '2026-04-22 13:06:17', '2026-04-22 13:06:17', 1),
(10, 'Machete', 0, 3, 0, 4, 'machete', '2026-04-22 13:06:17', '2026-04-23 18:26:40', 1);

-- --------------------------------------------------------

--
-- Table structure for table `store_tools_transactions`
--

CREATE TABLE `store_tools_transactions` (
  `id` int(11) NOT NULL,
  `tool_name` varchar(100) NOT NULL,
  `transaction_type` enum('add','used','delete','issued_to_student') NOT NULL,
  `quantity` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_tools_transactions`
--

INSERT INTO `store_tools_transactions` (`id`, `tool_name`, `transaction_type`, `quantity`, `student_id`, `reason`, `recorded_by`, `created_at`, `school_id`) VALUES
(1, 'Buckets', 'issued_to_student', 1, 221, NULL, 1, '2026-04-22 13:10:52', 1),
(2, 'Buckets', 'add', 1, NULL, '', 32, '2026-04-22 13:11:39', 1),
(3, 'Buckets', 'used', 2, NULL, '', 32, '2026-04-22 13:11:55', 1),
(4, 'Buckets', 'delete', 1, NULL, 'hello', 32, '2026-04-22 13:13:25', 1),
(6, 'Hoe', 'issued_to_student', 1, 221, NULL, 1, '2026-04-22 13:16:12', 1),
(11, 'Learn Paper', 'issued_to_student', 4, 27, NULL, 1, '2026-04-22 14:24:06', 1),
(12, 'Hoe', 'issued_to_student', 1, 27, NULL, 1, '2026-04-23 18:26:40', 1),
(13, 'Soft Broom', 'issued_to_student', 1, 27, NULL, 1, '2026-04-23 18:26:40', 1),
(14, 'Hard Broom', 'issued_to_student', 1, 27, NULL, 1, '2026-04-23 18:26:40', 1),
(15, 'Chelewa Broom', 'issued_to_student', 1, 27, NULL, 1, '2026-04-23 18:26:40', 1),
(16, 'Slasher', 'issued_to_student', 1, 27, NULL, 1, '2026-04-23 18:26:40', 1),
(17, 'Machete', 'issued_to_student', 1, 27, NULL, 1, '2026-04-23 18:26:40', 1),
(18, 'Buckets', 'issued_to_student', 2, 28, NULL, 1, '2026-04-23 18:26:58', 1);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `index_number` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `second_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `sex` enum('Male','Female') NOT NULL,
  `combination` enum('HGE','HGL','HGK','HKL','KLF','EGM','HLF','HGF') NOT NULL,
  `date_of_birth` date NOT NULL,
  `date_of_admission` date NOT NULL,
  `admission_number` varchar(50) DEFAULT NULL,
  `class` enum('Form Five','Form Six','Leavers','Graduated') NOT NULL DEFAULT 'Form Five',
  `citizenship` varchar(50) DEFAULT 'Tanzania',
  `place_of_birth` varchar(200) NOT NULL,
  `parent_name` varchar(200) NOT NULL,
  `parent_phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `parent_occupation` varchar(100) DEFAULT NULL,
  `parent_residence` text NOT NULL,
  `former_school` varchar(200) DEFAULT NULL,
  `school_transferred_to` varchar(200) DEFAULT NULL,
  `date_leaving_school` date DEFAULT NULL,
  `school_transferred_from` varchar(200) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_leaver` tinyint(1) DEFAULT 0,
  `year_left` year(4) DEFAULT NULL,
  `previous_class` enum('Form Five','Form Six','Leavers','Graduated') DEFAULT NULL,
  `class_changed_at` timestamp NULL DEFAULT NULL,
  `is_returned` tinyint(1) DEFAULT 0,
  `graduation_status` enum('Active','Form Five','Form Six','Graduated','Left') DEFAULT 'Active',
  `graduation_year` year(4) DEFAULT NULL,
  `promotion_status` enum('Not Promoted','Promoted to Form Six','Retained') DEFAULT 'Not Promoted',
  `updated_by_admin` int(11) DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_attempt` datetime DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `index_number`, `first_name`, `second_name`, `last_name`, `sex`, `combination`, `date_of_birth`, `date_of_admission`, `admission_number`, `class`, `citizenship`, `place_of_birth`, `parent_name`, `parent_phone`, `password`, `parent_occupation`, `parent_residence`, `former_school`, `school_transferred_to`, `date_leaving_school`, `school_transferred_from`, `status`, `created_at`, `updated_at`, `is_leaver`, `year_left`, `previous_class`, `class_changed_at`, `is_returned`, `graduation_status`, `graduation_year`, `promotion_status`, `updated_by_admin`, `failed_login_attempts`, `locked_until`, `last_login_attempt`, `profile_image`, `school_id`) VALUES
(1, 'S5098-0523', 'TAZE', 'JUMANNE', 'TADEO', 'Male', 'HGL', '2010-02-15', '2026-01-06', '12345', 'Form Five', 'Tanzania', 'KIGOMA', 'JUMANNE TAZE', '255745657456', '$2y$10$YourDefaultHashHere', 'MKULIMA', 'KIGOMA', '', '', '0000-00-00', '', 1, '2026-01-06 03:05:33', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(7, 'S5098-0563', 'jamary', 'hello', 'smith', 'Male', 'EGM', '2026-01-06', '2026-01-06', '1234678', 'Form Five', 'Tanzania', 'KIGOMA', 'JUMANNE TAZE', '0745657855', '$2y$10$YourDefaultHashHere', 'MKULIMA', 'KIGOMA', '', '', NULL, '', 1, '2026-01-06 05:17:36', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(9, 'S5098-0502', 'juju', 'juma', 'yusla', 'Female', 'HGE', '2026-01-06', '2026-01-06', '16589', 'Form Five', 'Tanzania', 'KIGOMA', 'JUMANNE TAZE', '255745657567', '$2y$10$YourDefaultHashHere', 'MKULIMA', 'KIGOMA', '', '', NULL, '', 1, '2026-01-06 05:52:24', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(10, 'S5098-0549', 'jamary', 'JUMANNE ', 'mussa', 'Male', 'KLF', '2026-01-02', '2026-01-06', '6788', 'Form Five', 'Tanzania', 'KIGOMA', 'JUMANNE TAZE', '255745657590', '$2y$10$YourDefaultHashHere', 'mkulima', 'KIGOMA', '', '', '0000-00-00', '', 1, '2026-01-06 07:45:52', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(11, 'S5098-0533', 'alu', 'JUMANNE ', 'mussa', 'Female', 'HKL', '2026-01-13', '2026-01-06', '877', 'Form Five', 'Tanzania', 'KIGOMA', 'JUMANNE TAZE', '255745657983', '$2y$10$YourDefaultHashHere', 'mkulima', 'KIGOMA', '', '', '0000-00-00', '', 1, '2026-01-06 07:46:57', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(13, 'S5098-0514', 'halima', 'JUMANNE ', 'mussa', 'Female', 'HGL', '2025-12-30', '2026-01-06', '12345455', 'Form Five', 'Tanzania', 'KIGOMA', 'JUMANNE TAZE', '255745657444', '$2y$10$YourDefaultHashHere', 'mkulima', 'KIGOMA', '', '', '0000-00-00', '', 1, '2026-01-06 08:41:30', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(14, 'S5098-0524', 'aujenia', 'JUMANNE ', 'TADEO', 'Female', 'HGK', '2025-12-30', '2026-01-06', '126677', 'Form Five', 'Tanzania', 'KIGOMA', 'JUMANNE TAZE', '255745657888', '$2y$10$YourDefaultHashHere', 'mkulima', 'KIGOMA', '', '', '0000-00-00', '', 1, '2026-01-06 08:47:28', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(15, 'S5098-0576', 'franc', 'peter', 'leo', 'Male', 'HGF', '2020-06-06', '2026-01-06', '456782', 'Form Five', 'Tanzania', 'KIGOMA', 'JUMANNE TAZE', '255745657765', '$2y$10$YourDefaultHashHere', 'mkulima', 'KIGOMA', 'nysrubanda', 'nyarubanda', '2026-01-06', 'muyovozi', 1, '2026-01-06 10:06:00', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(17, 'S5098-0559', 'THAZAN', 'JUMANNE ', 'TZONE', 'Male', 'HLF', '1999-02-08', '2026-01-08', '12348', 'Form Six', 'Tanzania', 'KIGOMA', 'JUMANNE TAZE', '255746457688', '$2y$10$YourDefaultHashHere', 'mkulima', 'KIGOMA', 'nyArubanda s1270/0114/2025', 'nyarubanda', '2026-01-08', 'muyovozi', 1, '2026-01-08 07:51:11', '2026-04-21 19:51:24', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(18, 'S5098-0501', 'JANETH', 'SAMSON', 'WECH', 'Female', 'HGE', '2005-07-13', '2026-01-08', '01', 'Form Five', 'Tanzania', 'KIGOMA', 'JAMARY TOPHIC', '255714343162', '$2y$10$JAImSSwHLvSjJiFkG11yyO7VUh1UaTJGdqhKl78oCX3r5RScUJ.ha', 'mkulima', 'KIGOMA', '', '', NULL, '', 1, '2026-01-08 08:07:52', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 2, NULL, '2026-03-11 12:22:26', NULL, 1),
(20, 'S5098-0506', 'Grace', 'John', 'Mkenda', 'Female', 'HGL', '2005-07-22', '2023-01-10', 'ADM002F', 'Form Six', 'Tanzania', 'Arusha', 'John Mkenda', '0755123456', '$2y$10$YourDefaultHashHere', 'Farmer', 'Arusha Municipality', 'Arusha Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:50:26', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(21, 'S5098-0513', 'Asha', 'Ali', 'Juma', 'Female', 'HGK', '2005-11-05', '2023-01-10', 'ADM003F', 'Form Six', 'Tanzania', 'Zanzibar', 'Ali Juma', '0777345678', '$2y$10$YourDefaultHashHere', 'Fisherman', 'Stone Town, Zanzibar', 'Zanzibar Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(22, 'S5098-0525', 'Fatuma', 'Ramadhani', 'Hassan', 'Female', 'HKL', '2005-01-30', '2023-01-10', 'ADM004F', 'Form Six', 'Tanzania', 'Tanga', 'Ramadhani Hassan', '0765123456', '$2y$10$YourDefaultHashHere', 'Businessman', 'Tanga City', 'Tanga Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(23, 'S5098-0533', 'Aisha', 'Mohamed', 'Said', 'Female', 'KLF', '2005-09-14', '2023-01-10', 'ADM005F', 'Form Six', 'Tanzania', 'Mwanza', 'Mohamed Said', '0789345678', '$2y$10$YourDefaultHashHere', 'Doctor', 'Ilemela, Mwanza', 'Mwanza Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(24, 'S5098-0543', 'Zainab', 'Salim', 'Abdallah', 'Female', 'EGM', '2005-04-18', '2023-01-10', 'ADM006F', 'Form Six', 'Tanzania', 'Dodoma', 'Salim Abdallah', '0711123456', '$2y$10$YourDefaultHashHere', 'Civil Servant', 'Dodoma City', 'Dodoma Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(25, 'S5098-0550', 'Mariam', 'Yusuf', 'Khamis', 'Female', 'HLF', '2005-12-25', '2023-01-10', 'ADM007F', 'Form Six', 'Tanzania', 'Mbeya', 'Yusuf Khamis', '0756123456', '$2y$10$YourDefaultHashHere', 'Engineer', 'Mbeya City', 'Mbeya Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(26, 'S5098-0560', 'Happiness', 'Paul', 'Mpenda', 'Female', 'HGF', '2005-06-08', '2023-01-10', 'ADM008F', 'Form Six', 'Tanzania', 'Morogoro', 'Paul Mpenda', '0777123456', '$2y$10$YourDefaultHashHere', 'Teacher', 'Morogoro Municipality', 'Morogoro Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(27, 'S5098-0502', 'Sarah', 'David', 'Mbowe', 'Female', 'HGE', '2005-08-19', '2023-01-10', '0001', 'Form Six', 'Tanzania', 'Dar es Salaam', 'David Mbowe', '255712345679', '$2y$10$6kf4tTAXDwL2CauJTzUmiuaNrVPUKX8NbV.T8GbtoufAiXa1SfJHC', 'Businessman', 'Ilala, Dar es Salaam', 'Azania Secondary', 'muyo', '0000-00-00', '', 1, '2026-01-08 12:34:32', '2026-04-21 19:50:26', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, '2026-03-11 12:02:44', NULL, 1),
(28, 'S5098-0505', 'Catherine', 'Peter', 'Kibona', 'Female', 'HGL', '2005-02-11', '2023-01-10', 'ADM010F', 'Form Six', 'Tanzania', 'Moshi', 'Peter Kibona', '255619844080', '$2y$10$2FJu.SJ1Lpan6vx9lvN0pOsSLnlJQiWS8jbiJdYTA8olRV/Pi7dEe', 'Hotel Manager', 'Moshi Municipality', 'Moshi Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:50:26', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(31, 'S5098-0520', 'David', 'Jacob', 'Mwingira', 'Male', 'HGK', '2005-03-25', '2023-01-10', 'ADM013M', 'Form Six', 'Tanzania', 'Dodoma', 'Jacob Mwingira', '0777345680', '$2y$10$YourDefaultHashHere', 'Farmer', 'Dodoma Rural', 'Dodoma Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(32, 'S5098-0530', 'James', 'Thomas', 'Kapinga', 'Male', 'HKL', '2005-07-30', '2023-01-10', 'ADM014M', 'Form Six', 'Tanzania', 'Mwanza', 'Thomas Kapinga', '0765123457', '$2y$10$YourDefaultHashHere', 'Fisherman', 'Mwanza City', 'Mwanza Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(33, 'S5098-0538', 'Peter', 'Andrew', 'Nyanda', 'Male', 'KLF', '2005-11-15', '2023-01-10', 'ADM015M', 'Form Six', 'Tanzania', 'Mbeya', 'Andrew Nyanda', '0789345680', '$2y$10$YourDefaultHashHere', 'Teacher', 'Mbeya City', 'Mbeya Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(34, 'S5098-0545', 'Michael', 'Christopher', 'Mpemba', 'Male', 'EGM', '2005-01-22', '2023-01-10', 'ADM016M', 'Form Six', 'Tanzania', 'Tanga', 'Christopher Mpemba', '0711123457', '$2y$10$YourDefaultHashHere', 'Businessman', 'Tanga City', 'Tanga Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(35, 'S5098-0558', 'Simon', 'Gabriel', 'Kisare', 'Male', 'HLF', '2005-09-05', '2023-01-10', 'ADM017M', 'Form Six', 'Tanzania', 'Morogoro', 'Gabriel Kisare', '0756123457', '$2y$10$YourDefaultHashHere', 'Civil Servant', 'Morogoro Municipality', 'Morogoro Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(36, 'S5098-0570', 'Paul', 'Matthew', 'Mtonga', 'Male', 'HGF', '2005-04-28', '2023-01-10', 'ADM018M', 'Form Six', 'Tanzania', 'Dar es Salaam', 'Matthew Mtonga', '0777123457', '$2y$10$YourDefaultHashHere', 'Doctor', 'Temeke, Dar es Salaam', 'Temeke Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(38, 'S5098-0510', 'Luke', 'Barnabas', 'Mosha', 'Male', 'HGL', '2005-12-10', '2023-01-10', 'ADM020M', 'Form Six', 'Tanzania', 'Moshi', 'Barnabas Mosha', '0755123459', '$2y$10$YourDefaultHashHere', 'Hotel Owner', 'Moshi Municipality', 'Moshi Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(39, 'S5098-0527', 'Rehema', 'Juma', 'Kondo', 'Female', 'HGK', '2004-03-15', '2022-01-10', 'ADM021F', 'Form Five', 'Tanzania', 'Dar es Salaam', 'Juma Kondo', '0712345682', '$2y$10$YourDefaultHashHere', 'Businesswoman', 'Kinondoni, Dar es Salaam', 'Kisutu Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(40, 'S5098-0538', 'Pendo', 'Rajabu', 'Mloka', 'Female', 'HKL', '2004-07-22', '2022-01-10', 'ADM022F', 'Form Five', 'Tanzania', 'Zanzibar', 'Rajabu Mloka', '0755123460', '$2y$10$YourDefaultHashHere', 'Teacher', 'Stone Town, Zanzibar', 'Zanzibar Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(41, 'S5098-0548', 'Tumaini', 'Salum', 'Kibwana', 'Female', 'KLF', '2004-11-05', '2022-01-10', 'ADM023F', 'Form Five', 'Tanzania', 'Tanga', 'Salum Kibwana', '0777345682', '$2y$10$YourDefaultHashHere', 'Nurse', 'Tanga City', 'Tanga Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(42, 'S5098-0555', 'Furaha', 'Hamisi', 'Mwakyembe', 'Female', 'EGM', '2004-01-30', '2022-01-10', 'ADM024F', 'Form Five', 'Tanzania', 'Mwanza', 'Hamisi Mwakyembe', '0765123458', '$2y$10$YourDefaultHashHere', 'Engineer', 'Ilemela, Mwanza', 'Mwanza Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(43, 'S5098-0568', 'Upendo', 'Issa', 'Kamala', 'Female', 'HLF', '2004-09-14', '2022-01-10', 'ADM025F', 'Form Five', 'Tanzania', 'Dodoma', 'Issa Kamala', '0789345682', '$2y$10$YourDefaultHashHere', 'Civil Servant', 'Dodoma City', 'Dodoma Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(44, 'S5098-0575', 'Imani', 'Suleiman', 'Mkumbo', 'Female', 'HGF', '2004-04-18', '2022-01-10', 'ADM026F', 'Form Five', 'Tanzania', 'Mbeya', 'Suleiman Mkumbo', '0711123458', '$2y$10$YourDefaultHashHere', 'Doctor', 'Mbeya City', 'Mbeya Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(46, 'S5098-0515', 'Mama', 'Yahya', 'Kadanya', 'Female', 'HGL', '2004-06-08', '2022-01-10', 'ADM028F', 'Form Five', 'Tanzania', 'Dar es Salaam', 'Yahya Kadanya', '0777123458', '$2y$10$YourDefaultHashHere', 'Businessman', 'Ilala, Dar es Salaam', 'Azania Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(47, 'S5098-0525', 'Dada', 'Kassim', 'Mgeni', 'Female', 'HGK', '2004-08-19', '2022-01-10', 'ADM029F', 'Form Five', 'Tanzania', 'Arusha', 'Kassim Mgeni', '0712345683', '$2y$10$YourDefaultHashHere', 'Tour Guide', 'Arusha Municipality', 'Arusha Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(48, 'S5098-0536', 'Mtoto', 'Hassan', 'Mwinyi', 'Female', 'HKL', '2004-02-11', '2022-01-10', 'ADM030F', 'Form Five', 'Tanzania', 'Moshi', 'Hassan Mwinyi', '0755123461', '$2y$10$YourDefaultHashHere', 'Hotel Manager', 'Moshi Municipality', 'Moshi Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(49, 'S5098-0552', 'Rajabu', 'Abdallah', 'Mfugale', 'Male', 'KLF', '2004-05-20', '2022-01-10', 'ADM031M', 'Form Five', 'Tanzania', 'Dar es Salaam', 'Abdallah Mfugale', '0712345684', '$2y$10$YourDefaultHashHere', 'Engineer', 'Ubungo, Dar es Salaam', 'Kisutu Boys Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(50, 'S5098-0561', 'Hamisi', 'Juma', 'Kivuyo', 'Male', 'EGM', '2004-10-12', '2022-01-10', 'ADM032M', 'Form Five', 'Tanzania', 'Arusha', 'Juma Kivuyo', '0755123462', '$2y$10$YourDefaultHashHere', 'Tour Guide', 'Arusha City', 'Arusha Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(51, 'S5098-0570', 'Kassim', 'Salim', 'Mkwizu', 'Male', 'HLF', '2004-03-25', '2022-01-10', 'ADM033M', 'Form Five', 'Tanzania', 'Dodoma', 'Salim Mkwizu', '0777345684', '$2y$10$YourDefaultHashHere', 'Farmer', 'Dodoma Rural', 'Dodoma Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(52, 'S5098-0579', 'Suleiman', 'Omar', 'Kijaji', 'Male', 'HGF', '2004-07-30', '2022-01-10', 'ADM034M', 'Form Five', 'Tanzania', 'Mwanza', 'Omar Kijaji', '0765123459', '$2y$10$YourDefaultHashHere', 'Fisherman', 'Mwanza City', 'Mwanza Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(53, 'S5098-0511', 'Yusuf', 'Ali', 'Mtei', 'Male', 'HGE', '2004-11-15', '2022-01-10', 'ADM035M', 'Form Five', 'Tanzania', 'Mbeya', 'Ali Mtei', '0789345684', '$2y$10$YourDefaultHashHere', 'Teacher', 'Mbeya City', 'Mbeya Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(54, 'S5098-0517', 'Ali', 'Mohamed', 'Mushi', 'Male', 'HGL', '2004-01-22', '2022-01-10', 'ADM036M', 'Form Five', 'Tanzania', 'Tanga', 'Mohamed Mushi', '0711123459', '$2y$10$YourDefaultHashHere', 'Businessman', 'Tanga City', 'Tanga Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(55, 'S5098-0531', 'Mohamed', 'Rashid', 'Kibwana', 'Male', 'HGK', '2004-09-05', '2022-01-10', 'ADM037M', 'Form Five', 'Tanzania', 'Morogoro', 'Rashid Kibwana', '0756123459', '$2y$10$YourDefaultHashHere', 'Civil Servant', 'Morogoro Municipality', 'Morogoro Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(56, 'S5098-0542', 'Rashid', 'Saidi', 'Mtemvu', 'Male', 'HKL', '2004-04-28', '2022-01-10', 'ADM038M', 'Form Five', 'Tanzania', 'Dar es Salaam', 'Saidi Mtemvu', '0777123459', '$2y$10$YourDefaultHashHere', 'Doctor', 'Temeke, Dar es Salaam', 'Temeke Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(57, 'S5098-0553', 'Saidi', 'Hemed', 'Kavishe', 'Male', 'KLF', '2004-08-03', '2022-01-10', 'ADM039M', 'Form Five', 'Tanzania', 'Arusha', 'Hemed Kavishe', '0712345685', '$2y$10$YourDefaultHashHere', 'Tour Operator', 'Arusha City', 'Arusha Modern Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(58, 'S5098-0562', 'Hemed', 'Mzee', 'Mariki', 'Male', 'EGM', '2004-12-10', '2022-01-10', 'ADM040M', 'Form Five', 'Tanzania', 'Moshi', 'Mzee Mariki', '0755123463', '$2y$10$YourDefaultHashHere', 'Hotel Owner', 'Moshi Municipality', 'Moshi Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(59, 'S5098-0549', 'Halima', 'Seif', 'Kishimbo', 'Female', 'HLF', '2005-03-08', '2023-01-10', 'ADM041F', 'Form Six', 'Tanzania', 'Lindi', 'Seif Kishimbo', '0712345686', '$2y$10$YourDefaultHashHere', 'Businesswoman', 'Lindi Town', 'Lindi Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(60, 'S5098-0563', 'Zuhura', 'Athumani', 'Mwambene', 'Female', 'HGF', '2005-06-21', '2023-01-10', 'ADM042F', 'Form Six', 'Tanzania', 'Mtwara', 'Athumani Mwambene', '0755123464', '$2y$10$YourDefaultHashHere', 'Teacher', 'Mtwara Mikindani', 'Mtwara Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(61, 'S5098-0506', 'Mwanahawa', 'Hassani', 'Kitwana', 'Female', 'HGE', '2005-10-04', '2023-01-10', 'ADM043F', 'Form Five', 'Tanzania', 'Pwani', 'Hassani Kitwana', '255777345686', '$2y$10$YourDefaultHashHere', 'Fisherman', 'Kibaha', 'Pwani Girls Secondary', '', '0000-00-00', '', 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(62, 'S5098-0507', 'Khadija', 'Jafari', 'Mpango', 'Female', 'HGL', '2005-01-17', '2023-01-10', 'ADM044F', 'Form Six', 'Tanzania', 'Ruvuma', 'Jafari Mpango', '0765123460', '$2y$10$YourDefaultHashHere', 'Farmer', 'Songea', 'Ruvuma Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:50:26', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(63, 'S5098-0518', 'Sauda', 'Mwinyimkuu', 'Kibiriti', 'Female', 'HGK', '2005-07-29', '2023-01-10', 'ADM045F', 'Form Six', 'Tanzania', 'Shinyanga', 'Mwinyimkuu Kibiriti', '0789345686', '$2y$10$YourDefaultHashHere', 'Miner', 'Shinyanga Town', 'Shinyanga Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(64, 'S5098-0527', 'Bakari', 'Mwinyi', 'Mwandu', 'Male', 'HKL', '2005-04-12', '2023-01-10', 'ADM046M', 'Form Six', 'Tanzania', 'Kagera', 'Mwinyi Mwandu', '0711123460', '$2y$10$YourDefaultHashHere', 'Businessman', 'Bukoba', 'Kagera Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(65, 'S5098-0551', 'Juma', 'Makame', 'Kisanga', 'Male', 'KLF', '2005-09-24', '2023-01-10', 'ADM047M', 'Form Five', 'Tanzania', 'Mara', 'Makame Kisanga', '0756123460', '$2y$10$YourDefaultHashHere', 'Teacher', 'Musoma', 'Mara Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(66, 'S5098-0546', 'Ramadhani', 'Mzee', 'Kibanda', 'Male', 'EGM', '2005-12-07', '2023-01-10', 'ADM048M', 'Form Six', 'Tanzania', 'Manyara', 'Mzee Kibanda', '0777123460', '$2y$10$YourDefaultHashHere', 'Farmer', 'Babati', 'Manyara Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(67, 'S5098-0555', 'Mwinyi', 'Kondo', 'Msangi', 'Male', 'HLF', '2005-05-19', '2023-01-10', 'ADM049M', 'Form Six', 'Tanzania', 'Geita', 'Kondo Msangi', '0712345687', '$2y$10$YourDefaultHashHere', 'Miner', 'Geita Town', 'Geita Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(68, 'S5098-0567', 'Makame', 'Hussein', 'Kijiko', 'Male', 'HGF', '2005-08-01', '2023-01-10', 'ADM050M', 'Form Six', 'Tanzania', 'Simiyu', 'Hussein Kijiko', '0755123465', '$2y$10$YourDefaultHashHere', 'Civil Servant', 'Bariadi', 'Simiyu Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(69, 'S5098-0504', 'Maimuna', 'Khamis', 'Mkubwa', 'Female', 'HGE', '2004-02-14', '2022-01-10', 'ADM051F', 'Form Five', 'Tanzania', 'Katavi', 'Khamis Mkubwa', '0777345687', '$2y$10$YourDefaultHashHere', 'Businesswoman', 'Mpanda', 'Katavi Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(70, 'S5098-0516', 'Mwanajuma', 'Sadiki', 'Kibao', 'Female', 'HGL', '2004-05-27', '2022-01-10', 'ADM052F', 'Form Five', 'Tanzania', 'Njombe', 'Sadiki Kibao', '0765123461', '$2y$10$YourDefaultHashHere', 'Teacher', 'Njombe Town', 'Njombe Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(71, 'S5098-0528', 'Tabu', 'Mzee', 'Kikwete', 'Female', 'HGK', '2004-09-09', '2022-01-10', 'ADM053F', 'Form Five', 'Tanzania', 'Kigoma', 'Mzee Kikwete', '0789345687', '$2y$10$YourDefaultHashHere', 'Fisherman', 'Kigoma Ujiji', 'Kigoma Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(72, 'S5098-0537', 'Mwajuma', 'Hamad', 'Kibona', 'Female', 'HKL', '2004-12-22', '2022-01-10', 'ADM054F', 'Form Five', 'Tanzania', 'Rukwa', 'Hamad Kibona', '0711123461', '$2y$10$YourDefaultHashHere', 'Farmer', 'Sumbawanga', 'Rukwa Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(73, 'S5098-0545', 'Jamila', 'Abdul', 'Mteule', 'Female', 'KLF', '2004-03-06', '2022-01-10', 'ADM055F', 'Form Five', 'Tanzania', 'Pemba', 'Abdul Mteule', '0756123461', '$2y$10$YourDefaultHashHere', 'Businesswoman', 'Chake Chake', 'Pemba Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(74, 'S5098-0558', 'Abdallah', 'Kombo', 'Mpemba', 'Male', 'EGM', '2004-06-19', '2022-01-10', 'ADM056M', 'Form Five', 'Tanzania', 'Unguja', 'Kombo Mpemba', '0777123461', '$2y$10$YourDefaultHashHere', 'Tour Guide', 'Zanzibar Town', 'Unguja Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(75, 'S5098-0571', 'Kombo', 'Kondo', 'Kiwia', 'Male', 'HLF', '2004-10-02', '2022-01-10', 'ADM057M', 'Form Five', 'Tanzania', 'Dar es Salaam', 'Kondo Kiwia', '0712345688', '$2y$10$YourDefaultHashHere', 'Engineer', 'Kigamboni, Dar es Salaam', 'Kigamboni Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(76, 'S5098-0578', 'Kondo', 'Mzee', 'Kilonzo', 'Male', 'HGF', '2004-01-15', '2022-01-10', 'ADM058M', 'Form Five', 'Tanzania', 'Arusha', 'Mzee Kilonzo', '0755123466', '$2y$10$YourDefaultHashHere', 'Tour Operator', 'Arusha City', 'Arusha Technical Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(78, 'S5098-0522', 'Massawe', 'Chamwela', 'Kimario', 'Male', 'HGL', '2004-08-11', '2022-01-10', 'ADM060M', 'Form Five', 'Tanzania', 'Dodoma', 'Chamwela Kimario', '0765123462', '$2y$10$YourDefaultHashHere', 'Civil Servant', 'Dodoma City', 'Dodoma Technical Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(79, 'S5098-0515', 'Ester', 'Daudi', 'Minja', 'Female', 'HGK', '2005-11-24', '2023-01-10', 'ADM061F', 'Form Six', 'Tanzania', 'Mbeya', 'Daudi Minja', '0789345688', '$2y$10$YourDefaultHashHere', 'Teacher', 'Mbeya City', 'Mbeya Technical Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(81, 'S5098-0536', 'Ruth', 'Yuda', 'Mwalukasa', 'Female', 'KLF', '2005-06-20', '2023-01-10', 'ADM063F', 'Form Six', 'Tanzania', 'Morogoro', 'Yuda Mwalukasa', '0756123462', '$2y$10$YourDefaultHashHere', 'Doctor', 'Morogoro Municipality', 'Morogoro Technical Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(82, 'S5098-0541', 'Naomi', 'Naftali', 'Mwakalinga', 'Female', 'EGM', '2005-10-03', '2023-01-10', 'ADM064F', 'Form Six', 'Tanzania', 'Mwanza', 'Naftali Mwakalinga', '0777123462', '$2y$10$YourDefaultHashHere', 'Engineer', 'Ilemela, Mwanza', 'Mwanza Technical Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(83, 'S5098-0551', 'Rachel', 'Joshua', 'Mtepa', 'Female', 'HLF', '2005-01-16', '2023-01-10', 'ADM065F', 'Form Six', 'Tanzania', 'Arusha', 'Joshua Mtepa', '0712345689', '$2y$10$YourDefaultHashHere', 'Tour Guide', 'Arusha City', 'Arusha Technical Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(84, 'S5098-0564', 'Elia', 'Samson', 'Mkumbo', 'Male', 'HGF', '2005-04-29', '2023-01-10', 'ADM066M', 'Form Six', 'Tanzania', 'Moshi', 'Samson Mkumbo', '0755123467', '$2y$10$YourDefaultHashHere', 'Hotel Owner', 'Moshi Municipality', 'Moshi Technical Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(85, 'S5098-0510', 'Samson', 'Daniel', 'Mlinga', 'Male', 'HGE', '2005-08-12', '2023-01-10', 'ADM067M', 'Form Five', 'Tanzania', 'Kinondoni, Dar es Salaam', 'Daniel Mlinga', '0777345689', '$2y$10$YourDefaultHashHere', 'Businessman', 'Kinondoni, Dar es Salaam', 'Kinondoni Technical', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(87, 'S5098-0522', 'Nathan', 'Isaac', 'Mkama', 'Male', 'HGK', '2005-03-09', '2023-01-10', 'ADM069M', 'Form Six', 'Tanzania', 'Tanga', 'Isaac Mkama', '0789345689', '$2y$10$YourDefaultHashHere', 'Teacher', 'Tanga City', 'Tanga Technical Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(88, 'S5098-0529', 'Isaac', 'Abraham', 'Mkwawa', 'Male', 'HKL', '2005-06-22', '2023-01-10', 'ADM070M', 'Form Six', 'Tanzania', 'Iringa', 'Abraham Mkwawa', '0711123463', '$2y$10$YourDefaultHashHere', 'Civil Servant', 'Iringa Municipality', 'Iringa Technical', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(89, 'S5098-0546', 'Lydia', 'Zakaria', 'Mabula', 'Female', 'KLF', '2004-10-05', '2022-01-10', 'ADM071F', 'Form Five', 'Tanzania', 'Songwe', 'Zakaria Mabula', '0756123463', '$2y$10$YourDefaultHashHere', 'Businesswoman', 'Vwawa', 'Songwe Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(90, 'S5098-0556', 'Miriam', 'Hosea', 'Mgimwa', 'Female', 'EGM', '2004-01-18', '2022-01-10', 'ADM072F', 'Form Five', 'Tanzania', 'Tabora', 'Hosea Mgimwa', '0777123463', '$2y$10$YourDefaultHashHere', 'Teacher', 'Tabora Municipality', 'Tabora Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(91, 'S5098-0566', 'Hannah', 'Amos', 'Mwakalukwa', 'Female', 'HLF', '2004-05-01', '2022-01-10', 'ADM073F', 'Form Five', 'Tanzania', 'Singida', 'Amos Mwakalukwa', '0712345690', '$2y$10$YourDefaultHashHere', 'Nurse', 'Singida Town', 'Singida Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(92, 'S5098-0573', 'Elizabeth', 'Jeremiah', 'Mfupi', 'Female', 'HGF', '2004-08-14', '2022-01-10', 'ADM074F', 'Form Five', 'Tanzania', 'Mara', 'Jeremiah Mfupi', '0755123468', '$2y$10$YourDefaultHashHere', 'Doctor', 'Musoma', 'Mara Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(93, 'S5098-0505', 'Mary', 'Ezekiel', 'Mkumbo', 'Female', 'HGE', '2004-11-27', '2022-01-10', 'ADM075F', 'Form Five', 'Tanzania', 'Kagera', 'Ezekiel Mkumbo', '0777345690', '$2y$10$YourDefaultHashHere', 'Engineer', 'Bukoba', 'Kagera Girls Secondary', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(94, 'S5098-0521', 'Ezekiel', 'Isaiah', 'Mnyampala', 'Male', 'HGL', '2004-03-11', '2022-01-10', 'ADM076M', 'Form Five', 'Tanzania', 'Shinyanga', 'Isaiah Mnyampala', '0765123464', '$2y$10$YourDefaultHashHere', 'Miner', 'Shinyanga Town', 'Shinyanga Technical', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(95, 'S5098-0530', 'Isaiah', 'Malachi', 'Mwakipesile', 'Male', 'HGK', '2004-06-24', '2022-01-10', 'ADM077M', 'Form Five', 'Tanzania', 'Geita', 'Malachi Mwakipesile', '0789345690', '$2y$10$YourDefaultHashHere', 'Miner', 'Geita Town', 'Geita Technical', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(96, 'S5098-0541', 'Malachi', 'Jonah', 'Mwagike', 'Male', 'HKL', '2004-10-07', '2022-01-10', 'ADM078M', 'Form Five', 'Tanzania', 'Simiyu', 'Jonah Mwagike', '0711123464', '$2y$10$YourDefaultHashHere', 'Farmer', 'Bariadi', 'Simiyu Technical', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(97, 'S5098-0550', 'Jonah', 'Obadiah', 'Mkude', 'Male', 'KLF', '2004-01-20', '2022-01-10', 'ADM079M', 'Form Five', 'Tanzania', 'Katavi', 'Obadiah Mkude', '0756123464', '$2y$10$YourDefaultHashHere', 'Businessman', 'Mpanda', 'Katavi Technical', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(98, 'S5098-0564', 'Obadiah', 'Micah', 'Mwalongo', 'Male', 'EGM', '2004-05-03', '2022-01-10', 'ADM080M', 'Form Five', 'Tanzania', 'Njombe', 'Micah Mwalongo', '0777123464', '$2y$10$YourDefaultHashHere', 'Teacher', 'Njombe Town', 'Njombe Technical', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(179, 'S5098-0552', 'Rose', 'Moses', 'Mwandosya', 'Female', 'HLF', '2005-09-16', '2023-01-10', 'ADM081F', 'Form Six', 'Tanzania', 'Kigoma', 'Moses Mwandosya', '0712345691', '$2y$10$YourDefaultHashHere', 'Fisherman', 'Kigoma Ujiji', 'Kigoma Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(180, 'S5098-0561', 'Joyce', 'Aaron', 'Mkenda', 'Female', 'HGF', '2005-12-29', '2023-01-10', 'ADM082F', 'Form Six', 'Tanzania', 'Rukwa', 'Aaron Mkenda', '0755123469', '$2y$10$YourDefaultHashHere', 'Farmer', 'Sumbawanga', 'Rukwa Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(182, 'S5098-0509', 'Teresia', 'Joshua', 'Mwangoka', 'Female', 'HGL', '2005-07-24', '2023-01-10', 'ADM084F', 'Form Six', 'Tanzania', 'Unguja', 'Joshua Mwangoka', '0765123465', '$2y$10$YourDefaultHashHere', 'Tour Guide', 'Zanzibar Town', 'Unguja Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(183, 'S5098-0514', 'Consolata', 'Benjamin', 'Mkude', 'Female', 'HGK', '2005-11-06', '2023-01-10', 'ADM085F', 'Form Six', 'Tanzania', 'Dar es Salaam', 'Benjamin Mkude', '0789345691', '$2y$10$YourDefaultHashHere', 'Engineer', 'Kigamboni', 'Kigamboni Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(184, 'S5098-0540', 'Benjamin', 'Samuel', 'Mwangosi', 'Male', 'HKL', '2005-02-19', '2023-01-10', 'ADM086M', 'Form Five', 'Tanzania', 'Arusha', 'Samuel Mwangosi', '255711123465', '$2y$10$YourDefaultHashHere', 'Tour Operator', 'Arusha City', 'Arusha Boys', '', '0000-00-00', '', 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(185, 'S5098-0539', 'Samuel', 'Solomon', 'Mkuchika', 'Male', 'KLF', '2005-06-02', '2023-01-10', 'ADM087M', 'Form Six', 'Tanzania', 'Moshi', 'Solomon Mkuchika', '0756123465', '$2y$10$YourDefaultHashHere', 'Hotel Manager', 'Moshi Rural', 'Moshi Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(186, 'S5098-0547', 'Solomon', 'Reuben', 'Mwaibula', 'Male', 'EGM', '2005-09-15', '2023-01-10', 'ADM088M', 'Form Six', 'Tanzania', 'Dodoma', 'Reuben Mwaibula', '0777123465', '$2y$10$YourDefaultHashHere', 'Civil Servant', 'Dodoma City', 'Dodoma Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(187, 'S5098-0556', 'Reuben', 'Levi', 'Mwinuka', 'Male', 'HLF', '2005-12-28', '2023-01-10', 'ADM089M', 'Form Six', 'Tanzania', 'Mbeya', 'Levi Mwinuka', '0712345692', '$2y$10$YourDefaultHashHere', 'Teacher', 'Mbeya City', 'Mbeya Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(188, 'S5098-0566', 'Levi', 'Judah', 'Mwakapalala', 'Male', 'HGF', '2005-04-10', '2023-01-10', 'ADM090M', 'Form Six', 'Tanzania', 'Tanga', 'Judah Mwakapalala', '0755123470', '$2y$10$YourDefaultHashHere', 'Businessman', 'Tanga City', 'Tanga Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(189, 'S5098-0503', 'Magdalena', 'Simeon', 'Mteule', 'Female', 'HGE', '2004-07-23', '2022-01-10', 'ADM091F', 'Form Five', 'Tanzania', 'Morogoro', 'Simeon Mteule', '0777345692', '$2y$10$YourDefaultHashHere', 'Doctor', 'Morogoro Municipality', 'Morogoro Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(190, 'S5098-0512', 'Agnes', 'Gad', 'Mkumbo', 'Female', 'HGL', '2004-11-05', '2022-01-10', 'ADM092F', 'Form Five', 'Tanzania', 'Mwanza', 'Gad Mkumbo', '0765123466', '$2y$10$YourDefaultHashHere', 'Engineer', 'Ilemela, Mwanza', 'Mwanza Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(191, 'S5098-0529', 'Veronica', 'Asher', 'Mwagikana', 'Female', 'HGK', '2004-02-18', '2022-01-10', 'ADM093F', 'Form Five', 'Tanzania', 'Arusha', 'Asher Mwagikana', '0789345692', '$2y$10$YourDefaultHashHere', 'Tour Guide', 'Arusha City', 'Arusha Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(192, 'S5098-0534', 'Christina', 'Naphtali', 'Mkumbo', 'Female', 'HKL', '2004-06-01', '2022-01-10', 'ADM094F', 'Form Five', 'Tanzania', 'Moshi', 'Naphtali Mkumbo', '0711123466', '$2y$10$YourDefaultHashHere', 'Hotel Owner', 'Moshi Municipality', 'Moshi Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(193, 'S5098-0547', 'Monica', 'Joseph', 'Mwakalukwa', 'Female', 'KLF', '2004-09-14', '2022-01-10', 'ADM095F', 'Form Five', 'Tanzania', 'Dar es Salaam', 'Joseph Mwakalukwa', '0756123466', '$2y$10$YourDefaultHashHere', 'Businessman', 'Kinondoni', 'Kinondoni Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(194, 'S5098-0560', 'Gideon', 'Dan', 'Mkumbo', 'Male', 'EGM', '2004-12-27', '2022-01-10', 'ADM096M', 'Form Five', 'Tanzania', 'Zanzibar', 'Dan Mkumbo', '0777123466', '$2y$10$YourDefaultHashHere', 'Fisherman', 'Stone Town', 'Zanzibar Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(195, 'S5098-0569', 'Dan', 'Zebulun', 'Mtepa', 'Male', 'HLF', '2004-04-09', '2022-01-10', 'ADM097M', 'Form Five', 'Tanzania', 'Tanga', 'Zebulun Mtepa', '0712345693', '$2y$10$YourDefaultHashHere', 'Teacher', 'Tanga City', 'Tanga Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(196, 'S5098-0580', 'Zebulun', 'Issachar', 'Mwangoka', 'Male', 'HGF', '2004-07-22', '2022-01-10', 'ADM098M', 'Form Five', 'Tanzania', 'Iringa', 'Issachar Mwangoka', '0755123471', '$2y$10$YourDefaultHashHere', 'Civil Servant', 'Iringa Municipality', 'Iringa Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(198, 'S5098-0520', 'Ephraim', 'Manasseh', 'Mwakasaka', 'Male', 'HGL', '2004-02-17', '2022-01-10', 'ADM100M', 'Form Five', 'Tanzania', 'Tabora', 'Manasseh Mwakasaka', '0765123467', '$2y$10$YourDefaultHashHere', 'Teacher', 'Tabora Municipality', 'Tabora Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(199, 'S5098-0516', 'Patricia', 'Ephraim', 'Mngumi', 'Female', 'HGK', '2005-05-31', '2023-01-10', 'ADM101F', 'Form Six', 'Tanzania', 'Singida', 'Ephraim Mngumi', '0789345693', '$2y$10$YourDefaultHashHere', 'Nurse', 'Singida Town', 'Singida Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(200, 'S5098-0524', 'Eunice', 'Manasseh', 'Mkumbo', 'Female', 'HKL', '2005-09-13', '2023-01-10', 'ADM102F', 'Form Six', 'Tanzania', 'Mara', 'Manasseh Mkumbo', '0711123467', '$2y$10$YourDefaultHashHere', 'Doctor', 'Musoma', 'Mara Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(201, 'S5098-0534', 'Beatrice', 'Reuben', 'Mwanga', 'Female', 'KLF', '2005-12-26', '2023-01-10', 'ADM103F', 'Form Six', 'Tanzania', 'Kagera', 'Reuben Mwanga', '0756123467', '$2y$10$YourDefaultHashHere', 'Engineer', 'Bukoba', 'Kagera Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(202, 'S5098-0540', 'Leticia', 'Simeon', 'Mtepa', 'Female', 'EGM', '2005-04-08', '2023-01-10', 'ADM104F', 'Form Six', 'Tanzania', 'Shinyanga', 'Simeon Mtepa', '0777123467', '$2y$10$YourDefaultHashHere', 'Miner', 'Shinyanga Town', 'Shinyanga Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(203, 'S5098-0554', 'Victoria', 'Levi', 'Mwakalinga', 'Female', 'HLF', '2005-07-21', '2023-01-10', 'ADM105F', 'Form Six', 'Tanzania', 'Geita', 'Levi Mwakalinga', '0712345694', '$2y$10$YourDefaultHashHere', 'Miner', 'Geita Town', 'Geita Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(204, 'S5098-0568', 'Manasseh', 'Judah', 'Mwakalukwa', 'Male', 'HGF', '2005-11-03', '2023-01-10', 'ADM106M', 'Form Six', 'Tanzania', 'Simiyu', 'Judah Mwakalukwa', '0755123472', '$2y$10$YourDefaultHashHere', 'Farmer', 'Bariadi', 'Simiyu Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(205, 'S5098-0508', 'Judah', 'Zebulun', 'Mwagike', 'Male', 'HGE', '2005-02-16', '2023-01-10', 'ADM107M', 'Form Five', 'Tanzania', 'Katavi', 'Zebulun Mwagike', '0777345694', '$2y$10$YourDefaultHashHere', 'Businessman', 'Mpanda', 'Katavi Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(206, 'S5098-0512', 'Zebulun', 'Issachar', 'Mkude', 'Male', 'HGL', '2005-05-30', '2023-01-10', 'ADM108M', 'Form Six', 'Tanzania', 'Njombe', 'Issachar Mkude', '0765123468', '$2y$10$YourDefaultHashHere', 'Teacher', 'Njombe Town', 'Njombe Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:50:26', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(207, 'S5098-0521', 'Issachar', 'Gad', 'Mwalongo', 'Male', 'HGK', '2005-09-12', '2023-01-10', 'ADM109M', 'Form Six', 'Tanzania', 'Kigoma', 'Gad Mwalongo', '0789345694', '$2y$10$YourDefaultHashHere', 'Fisherman', 'Kigoma Ujiji', 'Kigoma Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(208, 'S5098-0528', 'Gad', 'Asher', 'Mwandosya', 'Male', 'HKL', '2005-12-25', '2023-01-10', 'ADM110M', 'Form Six', 'Tanzania', 'Rukwa', 'Asher Mwandosya', '0711123468', '$2y$10$YourDefaultHashHere', 'Farmer', 'Sumbawanga', 'Rukwa Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(209, 'S5098-0544', 'Jackline', 'Dan', 'Mtega', 'Female', 'KLF', '2004-04-07', '2022-01-10', 'ADM111F', 'Form Five', 'Tanzania', 'Pemba', 'Dan Mtega', '0756123468', '$2y$10$YourDefaultHashHere', 'Businesswoman', 'Chake Chake', 'Pemba Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(210, 'S5098-0557', 'Vivian', 'Naphtali', 'Mwangoka', 'Female', 'EGM', '2004-07-20', '2022-01-10', 'ADM112F', 'Form Five', 'Tanzania', 'Unguja', 'Naphtali Mwangoka', '0777123468', '$2y$10$YourDefaultHashHere', 'Tour Guide', 'Zanzibar Town', 'Unguja Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(211, 'S5098-0567', 'Sylvia', 'Benjamin', 'Mkude', 'Female', 'HLF', '2004-11-02', '2022-01-10', 'ADM113F', 'Form Five', 'Tanzania', 'Dar es Salaam', 'Benjamin Mkude', '0712345695', '$2y$10$YourDefaultHashHere', 'Engineer', 'Kigamboni', 'Kigamboni Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1);
INSERT INTO `students` (`id`, `index_number`, `first_name`, `second_name`, `last_name`, `sex`, `combination`, `date_of_birth`, `date_of_admission`, `admission_number`, `class`, `citizenship`, `place_of_birth`, `parent_name`, `parent_phone`, `password`, `parent_occupation`, `parent_residence`, `former_school`, `school_transferred_to`, `date_leaving_school`, `school_transferred_from`, `status`, `created_at`, `updated_at`, `is_leaver`, `year_left`, `previous_class`, `class_changed_at`, `is_returned`, `graduation_status`, `graduation_year`, `promotion_status`, `updated_by_admin`, `failed_login_attempts`, `locked_until`, `last_login_attempt`, `profile_image`, `school_id`) VALUES
(212, 'S5098-0574', 'Gloria', 'Samuel', 'Mwangosi', 'Female', 'HGF', '2004-02-15', '2022-01-10', 'ADM114F', 'Form Five', 'Tanzania', 'Arusha', 'Samuel Mwangosi', '0755123473', '$2y$10$YourDefaultHashHere', 'Tour Operator', 'Arusha City', 'Arusha Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(214, 'S5098-0518', 'Asher', 'Reuben', 'Mwaibula', 'Male', 'HGL', '2004-09-11', '2022-01-10', 'ADM116M', 'Form Five', 'Tanzania', 'Dodoma', 'Reuben Mwaibula', '0765123469', '$2y$10$YourDefaultHashHere', 'Civil Servant', 'Dodoma City', 'Dodoma Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(215, 'S5098-0532', 'Naphtali', 'Levi', 'Mwinuka', 'Male', 'HGK', '2004-12-24', '2022-01-10', 'ADM117M', 'Form Five', 'Tanzania', 'Mbeya', 'Levi Mwinuka', '0789345695', '$2y$10$YourDefaultHashHere', 'Teacher', 'Mbeya City', 'Mbeya Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(216, 'S5098-0539', 'Benjamin', 'Judah', 'Mwakapalala', 'Male', 'HKL', '2004-04-06', '2022-01-10', 'ADM118M', 'Form Five', 'Tanzania', 'Tanga', 'Judah Mwakapalala', '0711123469', '$2y$10$YourDefaultHashHere', 'Businessman', 'Tanga City', 'Tanga Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(217, 'S5098-0554', 'Samuel', 'Simeon', 'Mteule', 'Male', 'KLF', '2004-07-19', '2022-01-10', 'ADM119M', 'Form Five', 'Tanzania', 'Morogoro', 'Simeon Mteule', '0756123469', '$2y$10$YourDefaultHashHere', 'Doctor', 'Morogoro Municipality', 'Morogoro Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(218, 'S5098-0565', 'Solomon', 'Gad', 'Mkumbo', 'Male', 'EGM', '2004-11-01', '2022-01-10', 'ADM120M', 'Form Five', 'Tanzania', 'Mwanza', 'Gad Mkumbo', '0777123469', '$2y$10$YourDefaultHashHere', 'Engineer', 'Ilemela, Mwanza', 'Mwanza Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(219, 'S5098-0548', 'Flora', 'Asher', 'Mwagikana', 'Female', 'HLF', '2005-02-14', '2023-01-10', 'ADM121F', 'Form Six', 'Tanzania', 'Arusha', 'Asher Mwagikana', '0712345696', '$2y$10$YourDefaultHashHere', 'Tour Guide', 'Arusha City', 'Arusha Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(220, 'S5098-0562', 'Linda', 'Naphtali', 'Mkumbo', 'Female', 'HGF', '2005-05-28', '2023-01-10', 'ADM122F', 'Form Six', 'Tanzania', 'Moshi', 'Naphtali Mkumbo', '0755123474', '$2y$10$YourDefaultHashHere', 'Hotel Owner', 'Moshi Municipality', 'Moshi Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(221, 'S5098-0503', 'Tatu', 'Joseph', 'Mwakalukwa', 'Female', 'HGE', '2005-09-10', '2023-01-10', 'ADM123F', 'Form Six', 'Tanzania', 'Dar es Salaam', 'Joseph Mwakalukwa', '0777345696', '$2y$10$YourDefaultHashHere', 'Businessman', 'Kinondoni', 'Kinondoni Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:50:26', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(222, 'S5098-0508', 'Mwajuma', 'Dan', 'Mkumbo', 'Female', 'HGL', '2005-12-23', '2023-01-10', 'ADM124F', 'Form Six', 'Tanzania', 'Zanzibar', 'Dan Mkumbo', '0765123470', '$2y$10$YourDefaultHashHere', 'Fisherman', 'Stone Town', 'Zanzibar Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(223, 'S5098-0519', 'Zawadi', 'Zebulun', 'Mtepa', 'Female', 'HGK', '2005-04-05', '2023-01-10', 'ADM125F', 'Form Six', 'Tanzania', 'Tanga', 'Zebulun Mtepa', '0789345696', '$2y$10$YourDefaultHashHere', 'Teacher', 'Tanga City', 'Tanga Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(224, 'S5098-0532', 'Reuben', 'Issachar', 'Mwangoka', 'Male', 'HKL', '2005-07-18', '2023-01-10', 'ADM126M', 'Form Six', 'Tanzania', 'Iringa', 'Issachar Mwangoka', '0711123470', '$2y$10$YourDefaultHashHere', 'Civil Servant', 'Iringa Municipality', 'Iringa Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(225, 'S5098-0537', 'Levi', 'Benjamin', 'Mkinda', 'Male', 'KLF', '2005-10-31', '2023-01-10', 'ADM127M', 'Form Six', 'Tanzania', 'Songwe', 'Benjamin Mkinda', '0756123470', '$2y$10$YourDefaultHashHere', 'Businessman', 'Vwawa', 'Songwe Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(226, 'S5098-0544', 'Judah', 'Manasseh', 'Mwakasaka', 'Male', 'EGM', '2005-02-13', '2023-01-10', 'ADM128M', 'Form Six', 'Tanzania', 'Tabora', 'Manasseh Mwakasaka', '0777123470', '$2y$10$YourDefaultHashHere', 'Teacher', 'Tabora Municipality', 'Tabora Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(227, 'S5098-0557', 'Simeon', 'Ephraim', 'Mngumi', 'Male', 'HLF', '2005-05-27', '2023-01-10', 'ADM129M', 'Form Six', 'Tanzania', 'Singida', 'Ephraim Mngumi', '0712345697', '$2y$10$YourDefaultHashHere', 'Nurse', 'Singida Town', 'Singida Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(228, 'S5098-0565', 'Gad', 'Manasseh', 'Mkumbo', 'Male', 'HGF', '2005-09-09', '2023-01-10', 'ADM130M', 'Form Six', 'Tanzania', 'Mara', 'Manasseh Mkumbo', '0755123475', '$2y$10$YourDefaultHashHere', 'Doctor', 'Musoma', 'Mara Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(230, 'S5098-0513', 'Anita', 'Simeon', 'Mtepa', 'Female', 'HGL', '2004-04-04', '2022-01-10', 'ADM132F', 'Form Five', 'Tanzania', 'Shinyanga', 'Simeon Mtepa', '0765123471', '$2y$10$YourDefaultHashHere', 'Miner', 'Shinyanga Town', 'Shinyanga Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(231, 'S5098-0526', 'Judith', 'Levi', 'Mwakalinga', 'Female', 'HGK', '2004-07-17', '2022-01-10', 'ADM133F', 'Form Five', 'Tanzania', 'Geita', 'Levi Mwakalinga', '0789345697', '$2y$10$YourDefaultHashHere', 'Miner', 'Geita Town', 'Geita Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(232, 'S5098-0535', 'Martha', 'Judah', 'Mwakalukwa', 'Female', 'HKL', '2004-10-30', '2022-01-10', 'ADM134F', 'Form Five', 'Tanzania', 'Simiyu', 'Judah Mwakalukwa', '0711123471', '$2y$10$YourDefaultHashHere', 'Farmer', 'Bariadi', 'Simiyu Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(233, 'S5098-0543', 'Esther', 'Zebulun', 'Mwagike', 'Female', 'KLF', '2004-02-12', '2022-01-10', 'ADM135F', 'Form Five', 'Tanzania', 'Katavi', 'Zebulun Mwagike', '0756123471', '$2y$10$YourDefaultHashHere', 'Businesswoman', 'Mpanda', 'Katavi Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(234, 'S5098-0559', 'Dan', 'Issachar', 'Mkude', 'Male', 'EGM', '2004-05-26', '2022-01-10', 'ADM136M', 'Form Five', 'Tanzania', 'Njombe', 'Issachar Mkude', '0777123471', '$2y$10$YourDefaultHashHere', 'Teacher', 'Njombe Town', 'Njombe Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(235, 'S5098-0572', 'Zebulun', 'Gad', 'Mwalongo', 'Male', 'HLF', '2004-09-08', '2022-01-10', 'ADM137M', 'Form Five', 'Tanzania', 'Kigoma', 'Gad Mwalongo', '0712345698', '$2y$10$YourDefaultHashHere', 'Fisherman', 'Kigoma Ujiji', 'Kigoma Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(236, 'S5098-0577', 'Issachar', 'Asher', 'Mwandosya', 'Male', 'HGF', '2004-12-21', '2022-01-10', 'ADM138M', 'Form Five', 'Tanzania', 'Rukwa', 'Asher Mwandosya', '0755123476', '$2y$10$YourDefaultHashHere', 'Farmer', 'Sumbawanga', 'Rukwa Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(237, 'S5098-0507', 'Gad', 'Dan', 'Mtega', 'Male', 'HGE', '2004-04-03', '2022-01-10', 'ADM139M', 'Form Five', 'Tanzania', 'Pemba', 'Dan Mtega', '0765123472', '$2y$10$YourDefaultHashHere', 'Businessman', 'Chake Chake', 'Pemba Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(238, 'S5098-0519', 'Asher', 'Naphtali', 'Mwangoka', 'Male', 'HGL', '2004-07-16', '2022-01-10', 'ADM140M', 'Form Five', 'Tanzania', 'Unguja', 'Naphtali Mwangoka', '0789345698', '$2y$10$YourDefaultHashHere', 'Tour Guide', 'Zanzibar Town', 'Unguja Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(239, 'S5098-0517', 'Paulina', 'Benjamin', 'Mkude', 'Female', 'HGK', '2005-10-29', '2023-01-10', 'ADM141F', 'Form Six', 'Tanzania', 'Dar es Salaam', 'Benjamin Mkude', '0711123472', '$2y$10$YourDefaultHashHere', 'Engineer', 'Kigamboni', 'Kigamboni Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(240, 'S5098-0526', 'Salome', 'Samuel', 'Mwangosi', 'Female', 'HKL', '2005-02-11', '2023-01-10', 'ADM142F', 'Form Six', 'Tanzania', 'Arusha', 'Samuel Mwangosi', '0756123472', '$2y$10$YourDefaultHashHere', 'Tour Operator', 'Arusha City', 'Arusha Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(241, 'S5098-0535', 'Rehema', 'Solomon', 'Mkuchika', 'Female', 'KLF', '2005-05-25', '2023-01-10', 'ADM143F', 'Form Six', 'Tanzania', 'Moshi', 'Solomon Mkuchika', '0777123472', '$2y$10$YourDefaultHashHere', 'Hotel Manager', 'Moshi Rural', 'Moshi Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(242, 'S5098-0542', 'Pili', 'Reuben', 'Mwaibula', 'Female', 'EGM', '2005-09-07', '2023-01-10', 'ADM144F', 'Form Six', 'Tanzania', 'Dodoma', 'Reuben Mwaibula', '0712345699', '$2y$10$YourDefaultHashHere', 'Civil Servant', 'Dodoma City', 'Dodoma Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(243, 'S5098-0553', 'Sijali', 'Levi', 'Mwinuka', 'Female', 'HLF', '2005-12-20', '2023-01-10', 'ADM145F', 'Form Six', 'Tanzania', 'Mbeya', 'Levi Mwinuka', '0755123477', '$2y$10$YourDefaultHashHere', 'Teacher', 'Mbeya City', 'Mbeya Girls', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(244, 'S5098-0569', 'Naphtali', 'Judah', 'Mwakapalala', 'Male', 'HGF', '2005-04-02', '2023-01-10', 'ADM146M', 'Form Six', 'Tanzania', 'Tanga', 'Judah Mwakapalala', '0765123473', '$2y$10$YourDefaultHashHere', 'Businessman', 'Tanga City', 'Tanga Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(246, 'S5098-0511', 'Samuel', 'Gad', 'Mkumbo', 'Male', 'HGL', '2005-10-28', '2023-01-10', 'ADM148M', 'Form Six', 'Tanzania', 'Mwanza', 'Gad Mkumbo', '0711123473', '$2y$10$YourDefaultHashHere', 'Engineer', 'Ilemela, Mwanza', 'Mwanza Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:52:05', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(247, 'S5098-0523', 'Solomon', 'Asher', 'Mwagikana', 'Male', 'HGK', '2005-02-10', '2023-01-10', 'ADM149M', 'Form Six', 'Tanzania', 'Arusha', 'Asher Mwagikana', '0756123473', '$2y$10$YourDefaultHashHere', 'Tour Guide', 'Arusha City', 'Arusha Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(248, 'S5098-0531', 'Reuben', 'Naphtali', 'Mkumbo', 'Male', 'HKL', '2005-05-24', '2023-01-10', 'ADM150M', 'Form Six', 'Tanzania', 'Moshi', 'Naphtali Mkumbo', '0777123473', '$2y$10$YourDefaultHashHere', 'Hotel Owner', 'Moshi Municipality', 'Moshi Boys', NULL, NULL, NULL, 1, '2026-01-08 12:34:32', '2026-04-21 19:51:24', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(250, 'S5098-0509', 'laurent', 'jumanne', 'tadeo', 'Male', 'HGE', '2005-02-23', '2026-01-23', '12348s', 'Form Five', 'Tanzania', 'KIGOMA', 'JUMANNE TAZE', '255745607567', '$2y$10$YourDefaultHashHere', '', 'KIGOMA', '', '', '0000-00-00', '', 1, '2026-01-23 16:12:49', '2026-04-21 19:52:05', 0, NULL, NULL, '2026-04-21 10:15:14', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, NULL, NULL, 1),
(251, 'S5098-0504', 'tazan ', 'samsin', 'thazan', 'Male', 'HGE', '2021-05-07', '2026-02-07', 'e444', 'Form Six', 'Tanzania', 'kg', 'clemensia samson', '255619844080', '$2y$10$h4t5q//WxVXQDp00.TIRV.jYanT0ln.sdemzjFzeuJ0mcLADPHy56', 'teacher', 'uvinza', '', '', '0000-00-00', '', 1, '2026-02-07 17:17:01', '2026-04-21 19:50:26', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 1, 'Form Five', NULL, 'Promoted to Form Six', 32, 1, NULL, '2026-04-03 20:57:32', NULL, 1),
(409, 'S5098-0501', 'princess', 'toy', 'toy', 'Female', 'HGE', '2016-05-08', '2026-03-27', 'y78', 'Form Six', 'Tanzania', 'kigoma', 'usa', '255619844080', '$2y$10$XqDSXRgczVpT5K8rBd3OTePIX.3tDrNNbceSDENENuGLjysyaepS.', 'engineer', 'dar', '', '', '0000-00-00', '', 1, '2026-03-27 10:12:05', '2026-04-22 14:24:18', 0, NULL, 'Form Five', '2026-04-21 19:50:26', 0, 'Form Five', NULL, 'Promoted to Form Six', 32, 0, NULL, '2026-03-27 13:12:53', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `student_dormitory`
--

CREATE TABLE `student_dormitory` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `dormitory_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `bed_number` varchar(10) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL COMMENT 'Admin ID who assigned',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('Active','Changed','Left','Graduated') DEFAULT 'Active',
  `notes` text DEFAULT NULL,
  `removed_date` timestamp NULL DEFAULT NULL,
  `removal_reason` text DEFAULT NULL,
  `is_leaver` tinyint(1) DEFAULT 0,
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_dormitory`
--

INSERT INTO `student_dormitory` (`id`, `student_id`, `dormitory_id`, `room_id`, `bed_number`, `assigned_by`, `assigned_at`, `updated_at`, `status`, `notes`, `removed_date`, `removal_reason`, `is_leaver`, `school_id`) VALUES
(35, 38, 8, 112, '', 12, '2026-02-07 13:12:18', '2026-02-07 13:13:06', 'Left', 'Assigned via male.php | Removed: Removed by admin via male.php', NULL, NULL, 0, 1),
(36, 61, 1, 1, '', 14, '2026-02-07 14:45:22', '2026-02-07 14:45:58', '', 'Assigned via dormitory.php', '2026-02-07 14:45:58', 'Auto-removed: Student deleted/marked as leaver', 0, 1),
(37, 0, 8, 112, '', 12, '2026-02-08 18:14:27', '2026-02-08 18:28:23', 'Left', 'Assigned via male.php | Removed: Removed by admin via male.php', NULL, NULL, 0, 1),
(38, 38, 9, 114, '', 12, '2026-02-08 18:14:38', '2026-02-08 18:31:52', 'Left', 'Assigned via male.php | Removed: Removed by admin via male.php', NULL, NULL, 0, 1),
(39, 221, 1, 1, '', 12, '2026-02-08 18:14:50', '2026-02-08 18:33:19', 'Left', 'Assigned via female.php | Removed: Removed by admin via female.php', NULL, NULL, 0, 1),
(40, 28, 2, 17, '', 12, '2026-02-08 18:15:01', '2026-02-08 18:34:08', 'Left', 'Assigned via female.php | Removed: Removed by admin via female.php', NULL, NULL, 0, 1),
(41, 222, 1, 1, '', 12, '2026-02-08 18:15:12', '2026-02-08 18:33:38', 'Left', 'Assigned via female.php | Removed: Removed by admin via female.php', NULL, NULL, 0, 1),
(42, 182, 1, 1, '', 12, '2026-02-08 18:15:26', '2026-02-08 18:33:14', 'Left', 'Assigned via female.php | Removed: Removed by admin via female.php', NULL, NULL, 0, 1),
(43, 31, 5, 77, '', 12, '2026-02-08 18:15:54', '2026-02-08 18:32:23', 'Left', 'Assigned via male.php | Removed: Removed by admin via male.php', NULL, NULL, 0, 1),
(44, 208, 10, 116, '', 12, '2026-02-08 18:16:03', '2026-02-08 18:32:09', 'Left', 'Assigned via male.php | Removed: Removed by admin via male.php', NULL, NULL, 0, 1),
(45, 87, 7, 107, '', 12, '2026-02-08 18:16:13', '2026-02-08 18:32:01', 'Left', 'Assigned via male.php | Removed: Removed by admin via male.php', NULL, NULL, 0, 1),
(46, 27, 1, 1, '', 12, '2026-02-08 18:18:29', '2026-02-08 18:33:51', 'Left', 'Assigned via female.php | Removed: Removed by admin via female.php', NULL, NULL, 0, 1),
(47, 20, 1, 1, '', 12, '2026-02-08 18:18:38', '2026-02-08 18:34:02', 'Left', 'Assigned via female.php | Removed: Removed by admin via female.php', NULL, NULL, 0, 1),
(48, 183, 1, 1, '', 12, '2026-02-08 18:18:46', '2026-02-08 18:33:57', 'Left', 'Assigned via female.php | Removed: Removed by admin via female.php', NULL, NULL, 0, 1),
(49, 63, 1, 1, '', 12, '2026-02-08 18:18:56', '2026-02-08 18:33:25', 'Left', 'Assigned via female.php | Removed: Removed by admin via female.php', NULL, NULL, 0, 1),
(50, 199, 1, 1, '', 12, '2026-02-08 18:19:08', '2026-02-08 18:33:31', 'Left', 'Assigned via female.php | Removed: Removed by admin via female.php', NULL, NULL, 0, 1),
(51, 79, 1, 1, '', 12, '2026-02-08 18:22:00', '2026-02-08 18:33:44', 'Left', 'Assigned via female.php | Removed: Removed by admin via female.php', NULL, NULL, 0, 1),
(52, 21, 2, 17, '', 12, '2026-02-08 18:22:10', '2026-02-08 18:33:09', 'Left', 'Assigned via female.php | Removed: Removed by admin via female.php', NULL, NULL, 0, 1),
(53, 62, 1, 10, '', 12, '2026-02-08 18:22:53', '2026-02-08 18:33:02', 'Left', 'Assigned via female.php | Removed: Removed by admin via female.php', NULL, NULL, 0, 1),
(54, 240, 2, 31, '', 12, '2026-02-08 18:23:09', '2026-02-08 18:34:16', 'Left', 'Assigned via female.php | Removed: Removed by admin via female.php', NULL, NULL, 0, 1),
(55, 0, 9, 114, '', 12, '2026-02-09 17:47:40', '2026-02-09 17:49:47', 'Left', 'Assigned via male.php | Removed: Removed by admin via male.php', NULL, NULL, 0, 1),
(56, 206, 10, 116, '', 12, '2026-02-09 17:47:47', '2026-02-09 17:49:53', 'Left', 'Assigned via male.php | Removed: Removed by admin via male.php', NULL, NULL, 0, 1),
(57, 246, 5, 87, 's1230', 12, '2026-03-08 02:39:36', '2026-03-08 02:40:47', 'Left', 'Assigned via male.php | Removed: Removed by admin via male.php', NULL, NULL, 0, 1),
(58, 251, 8, 112, '', 31, '2026-03-13 22:33:30', '2026-03-13 22:37:02', 'Left', 'Assigned via male.php | Removed: Removed by admin via male.php', NULL, NULL, 0, 1),
(59, 38, 8, 112, '', 31, '2026-03-13 22:34:24', '2026-03-13 22:36:55', 'Left', 'Assigned via dormitory.php | Removed: Removed by admin via male.php', NULL, NULL, 0, 1),
(60, 251, 8, 112, '', 35, '2026-04-01 22:06:42', '2026-04-03 15:58:27', 'Left', 'Assigned via male.php | Removed: Removed by admin via dormitory.php', NULL, NULL, 0, 1),
(61, 27, 1, 1, '', 32, '2026-04-21 18:46:03', '2026-04-21 18:49:15', '', 'Assigned via female.php', '2026-04-21 18:49:15', 'Auto-removed: Student marked as leaver/deactivated', 0, 1),
(62, 237, 8, 112, '', 28, '2026-05-21 11:56:34', '2026-05-21 11:56:34', 'Active', 'Assigned via male.php', NULL, NULL, 0, 1);

--
-- Triggers `student_dormitory`
--
DELIMITER $$
CREATE TRIGGER `prevent_duplicate_active_assignment` BEFORE INSERT ON `student_dormitory` FOR EACH ROW BEGIN
    DECLARE v_active_count INT;
    
    -- If trying to insert an Active assignment
    IF NEW.status = 'Active' THEN
        -- Check if student already has an Active assignment
        SELECT COUNT(*) INTO v_active_count
        FROM student_dormitory 
        WHERE student_id = NEW.student_id 
        AND status = 'Active';
        
        IF v_active_count > 0 THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Student already has an active dormitory assignment!';
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `prevent_update_to_active_duplicate` BEFORE UPDATE ON `student_dormitory` FOR EACH ROW BEGIN
    DECLARE v_active_count INT;
    
    -- If trying to update to Active status
    IF NEW.status = 'Active' AND OLD.status != 'Active' THEN
        -- Check if student already has an Active assignment (other than this one)
        SELECT COUNT(*) INTO v_active_count
        FROM student_dormitory 
        WHERE student_id = NEW.student_id 
        AND status = 'Active'
        AND id != NEW.id;
        
        IF v_active_count > 0 THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Student already has an active dormitory assignment! Cannot have multiple active assignments.';
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_room_on_assignment` AFTER INSERT ON `student_dormitory` FOR EACH ROW BEGIN
    -- Only update if status is Active
    IF NEW.status = 'Active' THEN
        UPDATE dormitory_rooms 
        SET current_occupancy = current_occupancy + 1,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = NEW.room_id
        AND current_occupancy < capacity;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_room_on_assignment_change` AFTER UPDATE ON `student_dormitory` FOR EACH ROW BEGIN
    -- If status changed from Active to something else
    IF OLD.status = 'Active' AND NEW.status != 'Active' THEN
        UPDATE dormitory_rooms 
        SET current_occupancy = GREATEST(current_occupancy - 1, 0),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = OLD.room_id;
    END IF;
    
    -- If status changed to Active from something else
    IF OLD.status != 'Active' AND NEW.status = 'Active' THEN
        UPDATE dormitory_rooms 
        SET current_occupancy = current_occupancy + 1,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = NEW.room_id
        AND current_occupancy < capacity;
    END IF;
    
    -- If room changed
    IF OLD.room_id != NEW.room_id AND OLD.status = 'Active' THEN
        -- Decrease old room
        UPDATE dormitory_rooms 
        SET current_occupancy = GREATEST(current_occupancy - 1, 0),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = OLD.room_id;
        
        -- Increase new room
        UPDATE dormitory_rooms 
        SET current_occupancy = current_occupancy + 1,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = NEW.room_id
        AND current_occupancy < capacity;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `student_equipment`
--

CREATE TABLE `student_equipment` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `status` enum('pending','submitted','incomplete') DEFAULT 'pending',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_equipment`
--

INSERT INTO `student_equipment` (`id`, `student_id`, `item_name`, `quantity`, `status`, `submitted_at`, `updated_at`, `school_id`) VALUES
(10, 237, 'Learn Paper (4)', 4, 'submitted', '2026-04-21 15:46:59', '2026-04-21 15:46:59', 1),
(19, 18, 'Learn Paper (4)', 4, 'submitted', '2026-04-22 08:05:00', '2026-04-22 08:05:00', 1),
(20, 18, 'Buckets (2)', 0, 'pending', NULL, '2026-04-21 16:48:59', 1),
(21, 18, 'Hoe (1)', 0, 'pending', NULL, '2026-04-21 16:48:59', 1),
(22, 18, 'Chair (1)', 1, 'submitted', '2026-04-22 08:05:00', '2026-04-22 08:05:00', 1),
(23, 18, 'Soft Broom (1)', 1, 'submitted', '2026-04-22 08:05:00', '2026-04-22 08:05:00', 1),
(24, 18, 'Hard Broom (1)', 1, 'submitted', '2026-04-22 08:05:00', '2026-04-22 08:05:00', 1),
(25, 18, 'Chelewa Broom (1)', 1, 'submitted', '2026-04-22 08:05:00', '2026-04-22 08:05:00', 1),
(26, 18, 'Slasher (1)', 1, 'submitted', '2026-04-22 08:05:00', '2026-04-22 08:05:00', 1),
(27, 18, 'Machete (1)', 1, 'submitted', '2026-04-22 08:05:00', '2026-04-22 08:05:00', 1),
(28, 237, 'Buckets (2)', 2, 'submitted', '2026-04-21 15:46:59', '2026-04-21 15:46:59', 1),
(31, 237, 'Hoe (1)', 1, 'submitted', '2026-04-21 15:46:59', '2026-04-21 15:46:59', 1),
(32, 237, 'Hard Broom (1)', 1, 'submitted', '2026-04-21 15:46:59', '2026-04-21 15:46:59', 1),
(33, 237, 'Slasher (1)', 1, 'submitted', '2026-04-21 15:46:59', '2026-04-21 15:46:59', 1),
(34, 237, 'Soft Broom (1)', 1, 'submitted', '2026-04-21 15:46:59', '2026-04-21 15:46:59', 1),
(35, 237, 'Lek (1)', 1, 'submitted', '2026-04-21 15:46:59', '2026-04-21 15:46:59', 1),
(40, 205, 'Learn Paper (4)', 4, 'submitted', '2026-04-21 19:25:11', '2026-04-21 19:25:11', 1),
(41, 205, 'Buckets (2)', 2, 'submitted', '2026-04-21 19:25:11', '2026-04-21 19:25:11', 1),
(42, 205, 'Soft Broom (1)', 1, 'submitted', '2026-04-21 19:25:11', '2026-04-21 19:25:11', 1),
(43, 205, 'Hard Broom (1)', 1, 'submitted', '2026-04-21 19:25:11', '2026-04-21 19:25:11', 1),
(44, 205, 'Slasher (1)', 1, 'submitted', '2026-04-21 19:25:11', '2026-04-21 19:25:11', 1),
(45, 205, 'Lek (1)', 1, 'submitted', '2026-04-21 19:25:11', '2026-04-21 19:25:11', 1),
(46, 205, 'Hoe (1)', 1, 'submitted', '2026-04-21 19:25:11', '2026-04-21 19:25:11', 1),
(47, 409, 'Chair (1)', 1, 'submitted', '2026-04-22 14:26:04', '2026-04-22 14:26:04', 1),
(48, 409, 'Soft Broom (1)', 1, 'submitted', '2026-04-22 14:26:04', '2026-04-22 14:26:04', 1),
(49, 409, 'Hard Broom (1)', 1, 'submitted', '2026-04-22 14:26:04', '2026-04-22 14:26:04', 1),
(50, 409, 'Chelewa Broom (1)', 1, 'submitted', '2026-04-22 14:26:04', '2026-04-22 14:26:04', 1),
(51, 409, 'Slasher (1)', 1, 'submitted', '2026-04-22 14:26:04', '2026-04-22 14:26:04', 1),
(52, 409, 'Machete (1)', 1, 'submitted', '2026-04-22 14:26:04', '2026-04-22 14:26:04', 1),
(53, 409, 'Learn Paper (4)', 4, 'submitted', '2026-04-22 14:26:04', '2026-04-22 14:26:04', 1),
(54, 409, 'Buckets (2)', 2, 'submitted', '2026-04-22 14:26:04', '2026-04-22 14:26:04', 1),
(55, 409, 'Hoe (1)', 1, 'submitted', '2026-04-22 14:26:04', '2026-04-22 14:26:04', 1),
(63, 27, 'Buckets (2)', 2, 'submitted', '2026-04-23 18:26:40', '2026-04-23 18:26:40', 1),
(64, 27, 'Chair (1)', 1, 'submitted', '2026-04-23 18:26:40', '2026-04-23 18:26:40', 1),
(65, 221, 'Buckets (2)', 2, 'submitted', '2026-04-22 14:06:54', '2026-04-22 14:06:54', 1),
(66, 221, 'Learn Paper (4)', 1, 'submitted', '2026-04-22 14:06:54', '2026-04-22 14:06:54', 1),
(67, 221, 'Hoe (1)', 1, 'submitted', '2026-04-22 14:06:54', '2026-04-22 14:06:54', 1),
(68, 221, 'Chair (1)', 1, 'submitted', '2026-04-22 14:06:54', '2026-04-22 14:06:54', 1),
(69, 221, 'Chelewa Broom (1)', 1, 'submitted', '2026-04-22 14:06:54', '2026-04-22 14:06:54', 1),
(70, 251, 'Buckets (2)', 1, 'submitted', '2026-04-22 14:07:53', '2026-04-22 14:07:53', 1),
(71, 251, 'Chair (1)', 1, 'submitted', '2026-04-22 14:07:53', '2026-04-22 14:07:53', 1),
(72, 27, 'Learn Paper (4)', 4, 'submitted', '2026-04-23 18:26:40', '2026-04-23 18:26:40', 1),
(73, 27, 'Hoe (1)', 1, 'submitted', '2026-04-23 18:26:40', '2026-04-23 18:26:40', 1),
(74, 27, 'Soft Broom (1)', 1, 'submitted', '2026-04-23 18:26:40', '2026-04-23 18:26:40', 1),
(75, 27, 'Hard Broom (1)', 1, 'submitted', '2026-04-23 18:26:40', '2026-04-23 18:26:40', 1),
(76, 27, 'Chelewa Broom (1)', 1, 'submitted', '2026-04-23 18:26:40', '2026-04-23 18:26:40', 1),
(77, 27, 'Slasher (1)', 1, 'submitted', '2026-04-23 18:26:40', '2026-04-23 18:26:40', 1),
(78, 27, 'Machete (1)', 1, 'submitted', '2026-04-23 18:26:40', '2026-04-23 18:26:40', 1),
(79, 28, 'Buckets (2)', 2, 'submitted', '2026-04-23 18:26:58', '2026-04-23 18:26:58', 1);

--
-- Triggers `student_equipment`
--
DELIMITER $$
CREATE TRIGGER `after_student_equipment_delete` AFTER DELETE ON `student_equipment` FOR EACH ROW BEGIN
    DECLARE tool_id INT;
    DECLARE tool_name_clean VARCHAR(100);
    
    -- Extract tool name without quantity suffix
    SET tool_name_clean = TRIM(SUBSTRING_INDEX(OLD.item_name, ' (', 1));
    
    -- Find matching tool in store
    SELECT id INTO tool_id FROM store_tools WHERE tool_name = tool_name_clean LIMIT 1;
    
    IF tool_id IS NOT NULL THEN
        -- Update store_tools (NOT student_equipment)
        UPDATE store_tools 
        SET 
            issued_to_students = GREATEST(0, issued_to_students - OLD.quantity),
            available_quantity = total_quantity + GREATEST(0, issued_to_students - OLD.quantity) - used_quantity
        WHERE id = tool_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_student_equipment_insert` AFTER INSERT ON `student_equipment` FOR EACH ROW BEGIN
    DECLARE tool_id INT;
    DECLARE tool_name_clean VARCHAR(100);
    
    -- Extract tool name without quantity suffix
    SET tool_name_clean = TRIM(SUBSTRING_INDEX(NEW.item_name, ' (', 1));
    
    -- Find matching tool in store
    SELECT id INTO tool_id FROM store_tools WHERE tool_name = tool_name_clean LIMIT 1;
    
    IF tool_id IS NOT NULL THEN
        -- Update store_tools (NOT student_equipment)
        UPDATE store_tools 
        SET 
            issued_to_students = issued_to_students + NEW.quantity,
            available_quantity = total_quantity + (issued_to_students + NEW.quantity) - used_quantity
        WHERE id = tool_id;
        
        -- Log the transaction
        INSERT INTO store_tools_transactions (tool_name, transaction_type, quantity, student_id, recorded_by)
        VALUES (tool_name_clean, 'issued_to_student', NEW.quantity, NEW.student_id, 1);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `student_equipment_with_store`
-- (See below for the actual view)
--
CREATE TABLE `student_equipment_with_store` (
`id` int(11)
,`student_id` int(11)
,`item_name` varchar(100)
,`quantity` int(11)
,`status` enum('pending','submitted','incomplete')
,`submitted_at` timestamp
,`first_name` varchar(100)
,`last_name` varchar(100)
,`index_number` varchar(50)
,`class` enum('Form Five','Form Six','Leavers','Graduated')
,`combination` enum('HGE','HGL','HGK','HKL','KLF','EGM','HLF','HGF')
,`store_tool_name` varchar(100)
,`store_total` int(11)
,`issued_to_students` int(11)
,`used_quantity` int(11)
,`store_available` int(11)
);

-- --------------------------------------------------------

--
-- Table structure for table `student_graduation_history`
--

CREATE TABLE `student_graduation_history` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `from_class` enum('Form Five','Form Six') NOT NULL,
  `to_class` enum('Form Five','Form Six','Graduated','Left') NOT NULL,
  `academic_year` varchar(9) NOT NULL COMMENT 'Format: 2024/2025',
  `graduation_type` enum('Promotion','Graduation','Transfer','Dropout','Repeating') DEFAULT 'Promotion',
  `graduation_date` date NOT NULL,
  `final_index_number` varchar(50) DEFAULT NULL,
  `certificate_number` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL COMMENT 'Admin ID',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_graduation_history`
--

INSERT INTO `student_graduation_history` (`id`, `student_id`, `from_class`, `to_class`, `academic_year`, `graduation_type`, `graduation_date`, `final_index_number`, `certificate_number`, `remarks`, `recorded_by`, `created_at`, `school_id`) VALUES
(0, 205, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-06', 'S5098-0502', NULL, 'Returned to Form Five', 12, '2026-02-06 08:31:50', 1),
(0, 18, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-06', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:17', 1),
(0, 1, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0521', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 1, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0521', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 10, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0543', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 10, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0543', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 11, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0531', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 11, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0531', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 14, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0522', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 14, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0522', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 15, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0570', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 15, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0570', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 39, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0528', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 39, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0528', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 40, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0539', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 40, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0539', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 41, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0551', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 41, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0551', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 42, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0554', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 42, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0554', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 43, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0567', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 43, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0567', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 44, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0572', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 44, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0572', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 45, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 45, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 46, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0518', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 46, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0518', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 47, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0523', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 47, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0523', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 48, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0537', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 48, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0537', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 49, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0548', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 49, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0548', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 50, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0556', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 50, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0556', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 51, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0564', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 51, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0564', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 52, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0575', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 52, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0575', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 53, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0510', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 53, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0510', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 54, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0512', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 54, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0512', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 55, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0526', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 55, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0526', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 56, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0540', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 56, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0540', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 57, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0549', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 57, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0549', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 58, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0557', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 58, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0557', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 69, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0506', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 69, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0506', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 70, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0520', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 70, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0520', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 71, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0529', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 71, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0529', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 72, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0538', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 72, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0538', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 73, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0544', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 73, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0544', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 74, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0552', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 74, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0552', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 75, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0565', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 75, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0565', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 76, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0574', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 76, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0574', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 77, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0508', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 77, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0508', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 78, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0519', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 78, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0519', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 89, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0546', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 89, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0546', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 90, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0558', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 90, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0558', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 91, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0563', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 91, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0563', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 92, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0569', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 92, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0569', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 93, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0507', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 93, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0507', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 94, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0517', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 94, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0517', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 95, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0524', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 95, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0524', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 96, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0535', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 96, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0535', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 97, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0545', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 97, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0545', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 98, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0559', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 98, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0559', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 184, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0533', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 184, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0533', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 189, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0505', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 189, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0505', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 190, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0511', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 190, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0511', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 191, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0530', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 191, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0530', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 192, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0534', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 192, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0534', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 193, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0547', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 193, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0547', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 194, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0555', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 194, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0555', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 195, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0562', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 195, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0562', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 196, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0576', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 196, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0576', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 197, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0504', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 197, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0504', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 198, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0516', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 198, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0516', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 209, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0542', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 209, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0542', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 210, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0561', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 210, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0561', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 211, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0566', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 211, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0566', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 212, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0571', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 212, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0571', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 213, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0502', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 213, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0502', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 214, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0514', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 214, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0514', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 215, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0527', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 215, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0527', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 216, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0532', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 216, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0532', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 217, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0550', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 217, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0550', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 218, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0560', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 218, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0560', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 229, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0509', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 229, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0509', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 230, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0513', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 230, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0513', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 231, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0525', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 231, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0525', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 232, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0536', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 232, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0536', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 233, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0541', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 233, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0541', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 234, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0553', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 234, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0553', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 235, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0568', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 235, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0568', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 236, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0573', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 236, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0573', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 237, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0503', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 237, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0503', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 238, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0515', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 238, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0515', NULL, 'Returned to Form Five', 12, '2026-02-06 08:32:29', 1),
(0, 17, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0563', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 17, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0563', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 19, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0504', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 19, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0504', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 20, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0510', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 20, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0510', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 21, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0518', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 21, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0518', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 22, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0531', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 22, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0531', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 23, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0538', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 23, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0538', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 24, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0552', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 24, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0552', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 25, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0555', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 25, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0555', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 26, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0567', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 26, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0567', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 27, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0506', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 27, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0506', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 28, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0508', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 28, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0508', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 30, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0511', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 30, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0511', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 31, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0520', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 31, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0520', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 32, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0534', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 32, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0534', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 33, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0541', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 33, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0541', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 34, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0547', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 34, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0547', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 35, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0562', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 35, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0562', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 36, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0574', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 36, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0574', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 37, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0502', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 37, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0502', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 38, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0513', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 38, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0513', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 59, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0554', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 59, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0554', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 60, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0575', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 60, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0575', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 61, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0503', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 61, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0503', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 62, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0512', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 62, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0512', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 63, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0526', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 63, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0526', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 64, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0529', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 64, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0529', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 66, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0550', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 66, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0550', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 67, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0556', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 67, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0556', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 68, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0571', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 68, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0571', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 79, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0521', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 79, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0521', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 81, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0543', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 81, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0543', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 82, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0548', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 82, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0548', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 83, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0557', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 83, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0557', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 84, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0565', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 84, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0565', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 86, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0509', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 86, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0509', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 87, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0523', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 87, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0523', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 88, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0533', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 88, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0533', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 179, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0559', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 179, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0559', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 180, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0568', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 180, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0568', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 182, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0516', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 182, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0516', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 183, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0519', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 183, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0519', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 185, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0544', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 185, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0544', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 186, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0551', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 186, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0551', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 187, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0558', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 187, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0558', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 188, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0569', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 188, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0569', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 199, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0524', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 199, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0524', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 200, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0530', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 200, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0530', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 201, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0539', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 201, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0539', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 202, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0546', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 202, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0546', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 203, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0564', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 203, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0564', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 204, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0572', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 204, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0572', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 206, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0517', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 206, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0517', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 207, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0522', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 207, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0522', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 208, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0532', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 208, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0532', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 219, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0553', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 219, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0553', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 220, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0570', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 220, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0570', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 221, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0507', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 221, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0507', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 222, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0514', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 222, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0514', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 223, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0528', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 223, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0528', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 224, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0536', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 224, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0536', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 225, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0540', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 225, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0540', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 226, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0545', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 226, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0545', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 227, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0561', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 227, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0561', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 228, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0566', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 228, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0566', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 239, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0525', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 239, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0525', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 240, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0537', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 240, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0537', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 241, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0542', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 241, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0542', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 242, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0549', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 242, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0549', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 243, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0560', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 243, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0560', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 244, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0573', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 244, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0573', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 246, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0515', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 246, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0515', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 247, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0527', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 247, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0527', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 248, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0535', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 248, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0535', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 250, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0501', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 250, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0501', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 251, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0505', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 251, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0505', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:32:29', 1),
(0, 45, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0501', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 08:41:55', 1),
(0, 45, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0501', NULL, 'Returned to Form Six', 12, '2026-02-06 09:03:35', 1),
(0, 213, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-06', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-06 09:47:43', 1),
(0, 237, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-06', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-06 09:47:49', 1),
(0, 213, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-06', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-06 10:20:47', 1),
(0, 45, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-06 10:20:57', 1),
(0, 250, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-06 12:35:29', 1),
(0, 17, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0562', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 17, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0562', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 19, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0503', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 19, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0503', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 20, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0509', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 20, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0509', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 21, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0517', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 21, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0517', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 22, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0530', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 22, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0530', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 23, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0537', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 23, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0537', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 24, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0551', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 24, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0551', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 25, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0554', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 25, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0554', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 26, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0566', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 26, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0566', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 27, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0505', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 27, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0505', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 28, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0507', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 28, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0507', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1);
INSERT INTO `student_graduation_history` (`id`, `student_id`, `from_class`, `to_class`, `academic_year`, `graduation_type`, `graduation_date`, `final_index_number`, `certificate_number`, `remarks`, `recorded_by`, `created_at`, `school_id`) VALUES
(0, 30, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0510', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 30, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0510', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 31, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0519', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 31, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0519', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 32, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0533', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 32, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0533', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 33, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0540', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 33, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0540', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 34, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0546', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 34, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0546', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 35, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0561', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 35, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0561', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 36, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0573', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 36, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0573', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 37, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 37, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 38, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0512', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 38, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0512', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 59, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0553', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 59, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0553', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 60, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0574', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 60, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0574', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 61, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0502', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 61, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0502', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 62, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0511', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 62, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0511', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 63, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0525', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 63, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0525', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 64, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0528', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 64, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0528', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 66, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0549', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 66, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0549', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 67, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0555', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 67, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0555', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 68, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0570', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 68, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0570', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 79, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0520', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 79, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0520', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 81, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0542', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 81, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0542', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 82, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0547', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 82, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0547', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 83, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0556', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 83, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0556', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 84, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0564', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 84, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0564', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 86, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0508', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 86, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0508', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 87, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0522', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 87, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0522', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 88, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0532', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 88, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0532', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 179, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0558', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 179, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0558', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 180, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0567', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 180, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0567', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 182, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0515', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 182, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0515', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 183, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0518', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 183, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0518', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 185, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0543', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 185, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0543', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 186, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0550', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 186, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0550', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 187, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0557', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 187, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0557', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 188, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0568', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 188, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0568', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 199, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0523', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 199, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0523', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 200, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0529', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 200, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0529', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 201, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0538', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 201, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0538', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 202, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0545', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 202, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0545', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 203, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0563', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 203, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0563', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 204, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0571', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 204, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0571', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 206, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0516', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 206, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0516', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 207, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0521', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 207, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0521', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 208, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0531', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 208, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0531', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 219, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0552', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 219, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0552', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 220, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0569', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 220, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0569', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 221, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0506', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 221, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0506', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 222, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0513', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 222, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0513', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 223, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0527', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 223, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0527', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 224, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0535', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 224, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0535', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 225, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0539', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 225, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0539', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 226, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0544', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 226, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0544', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 227, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0560', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 227, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0560', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 228, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0565', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 228, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0565', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 239, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0524', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 239, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0524', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 240, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0536', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 240, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0536', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 241, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0541', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 241, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0541', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 242, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0548', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 242, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0548', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 243, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0559', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 243, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0559', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 244, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0572', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 244, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0572', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 246, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0514', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 246, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0514', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 247, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0526', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 247, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0526', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 248, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0534', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 248, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0534', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 251, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0504', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 251, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0504', NULL, 'Returned to Form Five', 12, '2026-02-06 12:40:23', 1),
(0, 1, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0525', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 1, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0525', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 2, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0502', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 2, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0502', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 7, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0563', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 7, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0563', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 9, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0506', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 9, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0506', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 10, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0547', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 10, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0547', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 11, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0535', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 11, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0535', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 13, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0521', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 13, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0521', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 14, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0526', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 14, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0526', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 15, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0576', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 15, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0576', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 18, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0504', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 18, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0504', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 39, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0532', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 39, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0532', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 40, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0543', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 40, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0543', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 41, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0556', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 41, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0556', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 42, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0559', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 42, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0559', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 43, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0573', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 43, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0573', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 44, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0578', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 44, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0578', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 46, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0522', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 46, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0522', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 47, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0527', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 47, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0527', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 48, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0541', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 48, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0541', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 49, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0553', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 49, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0553', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 50, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0561', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 50, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0561', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 51, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0570', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 51, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0570', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 52, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0581', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 52, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0581', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 53, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0513', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 53, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0513', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 54, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0515', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 54, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0515', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 55, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0530', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 55, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0530', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 56, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0544', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 56, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0544', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 57, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0554', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 57, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0554', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 58, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0562', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 58, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0562', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 65, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0550', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 65, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0550', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 69, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0508', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 69, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0508', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 70, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0524', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 70, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0524', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 71, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0533', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 71, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0533', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 72, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0542', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 72, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0542', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 73, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0548', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 73, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0548', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 74, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0557', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 74, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0557', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 75, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0571', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 75, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0571', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 76, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0580', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 76, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0580', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 77, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0510', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 77, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0510', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 78, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0523', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 78, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0523', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 85, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0511', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 85, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0511', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 89, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0551', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 89, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0551', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 90, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0564', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 90, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0564', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 91, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0569', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 91, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0569', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 92, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0575', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 92, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0575', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 93, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0509', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 93, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0509', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 94, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0520', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 94, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0520', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 95, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0528', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 95, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0528', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 96, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0539', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 96, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0539', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 97, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0549', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 97, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0549', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 98, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0565', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 98, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0565', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 184, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0537', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 184, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0537', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 189, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0507', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 189, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0507', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 190, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0514', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 190, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0514', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 191, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0534', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 191, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0534', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 192, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0538', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 192, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0538', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 193, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0552', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 193, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0552', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 194, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0560', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 194, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0560', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 195, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0568', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 195, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0568', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 196, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0582', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 196, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0582', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 197, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0503', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 197, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0503', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 198, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0519', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 198, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0519', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 205, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0505', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 205, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0505', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 209, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0546', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 209, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0546', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 210, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0567', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 210, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0567', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 211, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0572', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 211, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0572', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 212, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0577', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 212, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0577', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 214, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0517', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 214, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0517', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 215, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0531', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 215, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0531', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 216, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0536', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 216, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0536', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 217, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0555', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 217, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0555', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 218, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0566', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 218, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0566', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 229, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0512', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 229, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0512', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 230, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0516', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 230, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0516', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 231, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0529', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 231, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0529', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 232, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0540', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 232, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0540', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 233, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0545', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 233, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0545', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 234, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0558', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 234, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0558', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 235, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0574', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 235, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0574', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 236, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0579', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 236, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0579', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 237, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0501', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 237, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0501', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 238, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0518', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 238, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-06', 'S5098-0518', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-06 12:40:23', 1),
(0, 45, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-06', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-06 12:45:15', 1),
(0, 213, 'Form Five', 'Left', '2025/2026', 'Dropout', '2026-02-06', 'S5098-0501', NULL, 'Left school from Form Five - Reason: Not specified', 12, '2026-02-06 15:38:16', 1),
(0, 250, 'Form Five', 'Left', '2025/2026', 'Dropout', '2026-02-06', 'S5098-0501', NULL, 'Left school from Form Five - Reason: ', 12, '2026-02-06 15:38:20', 1),
(0, 37, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-06', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-06 15:38:27', 1),
(0, 237, 'Form Six', 'Graduated', '2025/2026', 'Graduation', '2026-02-06', 'S5098-0501', NULL, 'Form Six graduation - Completed', 12, '2026-02-06 15:38:49', 1),
(0, 86, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-07', 'S5098-0501', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-07 09:12:47', 1),
(0, 86, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-07', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-07 09:36:30', 1),
(0, 86, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-07', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-07 09:44:48', 1),
(0, 86, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-07', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-07 10:18:31', 1),
(0, 86, 'Form Five', 'Left', '2025/2026', 'Dropout', '2026-02-07', 'S5098-0501', NULL, 'Left school from Form Five - Reason: ', 12, '2026-02-07 10:22:20', 1),
(0, 37, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-07', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-07 10:31:46', 1),
(0, 37, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-07', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-07 10:32:39', 1),
(0, 37, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-07', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-07 10:36:28', 1),
(0, 37, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-07', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-07 10:37:50', 1),
(0, 61, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-07', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-07 10:57:36', 1),
(0, 61, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-07', 'S5098-0501', NULL, 'Returned to Form Five', 12, '2026-02-07 10:59:25', 1),
(0, 19, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-07', 'S5098-0501', NULL, 'Promoted from Form Five to Form Six', 12, '2026-02-07 10:59:50', 1),
(0, 197, 'Form Six', '', '2025/2026', 'Graduation', '2026-02-07', 'S5098-0501', NULL, 'Returned to Form Six', 12, '2026-02-07 11:10:08', 1),
(0, 61, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-07', 'S5098-0501', NULL, 'Returned to Form Five', 14, '2026-02-07 14:34:57', 1),
(0, 61, 'Form Five', '', '2025/2026', '', '2026-02-07', 'S5098-0501', NULL, 'Returned to Form Five', 14, '2026-02-07 14:34:57', 1),
(0, 61, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-07', 'S5098-0501', NULL, 'Returned to Form Five', 14, '2026-02-07 14:45:58', 1),
(0, 61, 'Form Five', '', '2025/2026', '', '2026-02-07', 'S5098-0501', NULL, 'Returned to Form Five', 14, '2026-02-07 14:45:58', 1),
(0, 61, 'Form Five', 'Form Six', '2025/2026', 'Promotion', '2026-02-07', 'S5098-0501', NULL, 'Promoted from Form Five to Form Six', 14, '2026-02-07 14:51:41', 1),
(0, 0, 'Form Five', '', '2025/2026', 'Dropout', '2026-02-07', 'S5098-0503', NULL, 'Returned to Form Five', 14, '2026-02-07 17:17:47', 1),
(0, 0, 'Form Five', '', '2025/2026', '', '2026-02-07', 'S5098-0503', NULL, 'Returned to Form Five', 14, '2026-02-07 17:17:47', 1),
(0, 213, 'Form Six', 'Graduated', '2025/2026', '', '2026-04-21', 'S5098-0501', NULL, 'Graduated from Form Six', 32, '2026-04-21 10:15:27', 1),
(0, 237, 'Form Six', 'Graduated', '2025/2026', '', '2026-04-21', 'S5098-0508', NULL, 'Graduated from Form Six', 32, '2026-04-21 16:08:00', 1),
(0, 213, 'Form Five', 'Left', '2025/2026', '', '2026-04-21', 'S5098-0501', NULL, 'Transferred from Form Five', 32, '2026-04-21 18:22:54', 1);

-- --------------------------------------------------------

--
-- Table structure for table `student_leavers`
--

CREATE TABLE `student_leavers` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `index_number` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `combination` varchar(10) NOT NULL,
  `class_left` enum('Form Five','Form Six') NOT NULL,
  `year_left` year(4) NOT NULL,
  `reason` varchar(200) DEFAULT 'Graduation',
  `left_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `returned` tinyint(1) DEFAULT 0,
  `returned_at` timestamp NULL DEFAULT NULL,
  `leaver_type` enum('Graduated','Transferred','Dismissed','Other') DEFAULT 'Transferred',
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_leavers`
--

INSERT INTO `student_leavers` (`id`, `student_id`, `index_number`, `first_name`, `last_name`, `combination`, `class_left`, `year_left`, `reason`, `left_at`, `returned`, `returned_at`, `leaver_type`, `school_id`) VALUES
(0, 205, 'S5098-0502', 'Judah', 'Mwagike', 'HGE', 'Form Five', '2026', 'Transferred from Form Five', '2026-02-06 08:31:50', 1, '2026-02-06 08:33:08', 'Transferred', 1),
(0, 18, 'S5098-0501', 'JANETH', 'WECH', 'HGE', 'Form Five', '2026', 'Transferred from Form Five', '2026-02-06 08:32:17', 1, '2026-02-06 08:33:08', 'Transferred', 1),
(0, 1, 'S5098-0521', 'TAZE', 'TADEO', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 10, 'S5098-0543', 'jamary', 'mussa', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 11, 'S5098-0531', 'alu', 'mussa', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 14, 'S5098-0522', 'aujenia', 'TADEO', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 15, 'S5098-0570', 'franc', 'leo', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 39, 'S5098-0528', 'Rehema', 'Kondo', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 40, 'S5098-0539', 'Pendo', 'Mloka', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 41, 'S5098-0551', 'Tumaini', 'Kibwana', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 42, 'S5098-0554', 'Furaha', 'Mwakyembe', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 43, 'S5098-0567', 'Upendo', 'Kamala', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 44, 'S5098-0572', 'Imani', 'Mkumbo', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 45, 'S5098-0501', 'Bibi', 'Shekimweri', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 46, 'S5098-0518', 'Mama', 'Kadanya', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 47, 'S5098-0523', 'Dada', 'Mgeni', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 48, 'S5098-0537', 'Mtoto', 'Mwinyi', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 49, 'S5098-0548', 'Rajabu', 'Mfugale', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 50, 'S5098-0556', 'Hamisi', 'Kivuyo', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 51, 'S5098-0564', 'Kassim', 'Mkwizu', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 52, 'S5098-0575', 'Suleiman', 'Kijaji', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 53, 'S5098-0510', 'Yusuf', 'Mtei', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:08', 'Graduated', 1),
(0, 54, 'S5098-0512', 'Ali', 'Mushi', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 55, 'S5098-0526', 'Mohamed', 'Kibwana', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 56, 'S5098-0540', 'Rashid', 'Mtemvu', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 57, 'S5098-0549', 'Saidi', 'Kavishe', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 58, 'S5098-0557', 'Hemed', 'Mariki', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 69, 'S5098-0506', 'Maimuna', 'Mkubwa', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 70, 'S5098-0520', 'Mwanajuma', 'Kibao', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 71, 'S5098-0529', 'Tabu', 'Kikwete', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 72, 'S5098-0538', 'Mwajuma', 'Kibona', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 73, 'S5098-0544', 'Jamila', 'Mteule', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 74, 'S5098-0552', 'Abdallah', 'Mpemba', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 75, 'S5098-0565', 'Kombo', 'Kiwia', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 76, 'S5098-0574', 'Kondo', 'Kilonzo', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 77, 'S5098-0508', 'Mzee', 'Kiwelu', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 78, 'S5098-0519', 'Massawe', 'Kimario', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 89, 'S5098-0546', 'Lydia', 'Mabula', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 90, 'S5098-0558', 'Miriam', 'Mgimwa', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 91, 'S5098-0563', 'Hannah', 'Mwakalukwa', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 92, 'S5098-0569', 'Elizabeth', 'Mfupi', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 93, 'S5098-0507', 'Mary', 'Mkumbo', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 94, 'S5098-0517', 'Ezekiel', 'Mnyampala', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 95, 'S5098-0524', 'Isaiah', 'Mwakipesile', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 96, 'S5098-0535', 'Malachi', 'Mwagike', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 97, 'S5098-0545', 'Jonah', 'Mkude', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 98, 'S5098-0559', 'Obadiah', 'Mwalongo', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 184, 'S5098-0533', 'Benjamin', 'Mwangosi', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 189, 'S5098-0505', 'Magdalena', 'Mteule', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 190, 'S5098-0511', 'Agnes', 'Mkumbo', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 191, 'S5098-0530', 'Veronica', 'Mwagikana', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 192, 'S5098-0534', 'Christina', 'Mkumbo', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 193, 'S5098-0547', 'Monica', 'Mwakalukwa', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 194, 'S5098-0555', 'Gideon', 'Mkumbo', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 195, 'S5098-0562', 'Dan', 'Mtepa', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 196, 'S5098-0576', 'Zebulun', 'Mwangoka', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 197, 'S5098-0504', 'Issachar', 'Mkinda', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 198, 'S5098-0516', 'Ephraim', 'Mwakasaka', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 209, 'S5098-0542', 'Jackline', 'Mtega', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 210, 'S5098-0561', 'Vivian', 'Mwangoka', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 211, 'S5098-0566', 'Sylvia', 'Mkude', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 212, 'S5098-0571', 'Gloria', 'Mwangosi', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 214, 'S5098-0514', 'Asher', 'Mwaibula', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 215, 'S5098-0527', 'Naphtali', 'Mwinuka', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 216, 'S5098-0532', 'Benjamin', 'Mwakapalala', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 217, 'S5098-0550', 'Samuel', 'Mteule', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 218, 'S5098-0560', 'Solomon', 'Mkumbo', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 229, 'S5098-0509', 'Stella', 'Mwanga', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 230, 'S5098-0513', 'Anita', 'Mtepa', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 231, 'S5098-0525', 'Judith', 'Mwakalinga', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 232, 'S5098-0536', 'Martha', 'Mwakalukwa', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 233, 'S5098-0541', 'Esther', 'Mwagike', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 234, 'S5098-0553', 'Dan', 'Mkude', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 235, 'S5098-0568', 'Zebulun', 'Mwalongo', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 236, 'S5098-0573', 'Issachar', 'Mwandosya', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 237, 'S5098-0503', 'Gad', 'Mtega', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 238, 'S5098-0515', 'Asher', 'Mwangoka', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 08:32:29', 1, '2026-02-06 08:33:09', 'Graduated', 1),
(0, 250, 'S5098-0501', 'laurent', 'tadeo', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:35:29', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 17, 'S5098-0562', 'THAZAN', 'TZONE', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 19, 'S5098-0503', 'Neema', 'Mrema', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 20, 'S5098-0509', 'Grace', 'Mkenda', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 21, 'S5098-0517', 'Asha', 'Juma', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 22, 'S5098-0530', 'Fatuma', 'Hassan', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 23, 'S5098-0537', 'Aisha', 'Said', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 24, 'S5098-0551', 'Zainab', 'Abdallah', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 25, 'S5098-0554', 'Mariam', 'Khamis', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 26, 'S5098-0566', 'Happiness', 'Mpenda', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 27, 'S5098-0505', 'Sarah', 'Mbowe', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 28, 'S5098-0507', 'Catherine', 'Kibona', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 30, 'S5098-0510', 'Joseph', 'Chamwela', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 31, 'S5098-0519', 'David', 'Mwingira', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 32, 'S5098-0533', 'James', 'Kapinga', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 33, 'S5098-0540', 'Peter', 'Nyanda', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 34, 'S5098-0546', 'Michael', 'Mpemba', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 35, 'S5098-0561', 'Simon', 'Kisare', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 36, 'S5098-0573', 'Paul', 'Mtonga', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:06', 'Graduated', 1),
(0, 37, 'S5098-0501', 'Mark', 'Lyimo', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 38, 'S5098-0512', 'Luke', 'Mosha', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 59, 'S5098-0553', 'Halima', 'Kishimbo', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 60, 'S5098-0574', 'Zuhura', 'Mwambene', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 61, 'S5098-0502', 'Mwanahawa', 'Kitwana', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 62, 'S5098-0511', 'Khadija', 'Mpango', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 63, 'S5098-0525', 'Sauda', 'Kibiriti', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 64, 'S5098-0528', 'Bakari', 'Mwandu', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 66, 'S5098-0549', 'Ramadhani', 'Kibanda', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 67, 'S5098-0555', 'Mwinyi', 'Msangi', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 68, 'S5098-0570', 'Makame', 'Kijiko', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 79, 'S5098-0520', 'Ester', 'Minja', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 81, 'S5098-0542', 'Ruth', 'Mwalukasa', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 82, 'S5098-0547', 'Naomi', 'Mwakalinga', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 83, 'S5098-0556', 'Rachel', 'Mtepa', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 84, 'S5098-0564', 'Elia', 'Mkumbo', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 87, 'S5098-0522', 'Nathan', 'Mkama', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 88, 'S5098-0532', 'Isaac', 'Mkwawa', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 179, 'S5098-0558', 'Rose', 'Mwandosya', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 180, 'S5098-0567', 'Joyce', 'Mkenda', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 182, 'S5098-0515', 'Teresia', 'Mwangoka', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 183, 'S5098-0518', 'Consolata', 'Mkude', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 185, 'S5098-0543', 'Samuel', 'Mkuchika', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 186, 'S5098-0550', 'Solomon', 'Mwaibula', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 187, 'S5098-0557', 'Reuben', 'Mwinuka', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 188, 'S5098-0568', 'Levi', 'Mwakapalala', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 199, 'S5098-0523', 'Patricia', 'Mngumi', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 200, 'S5098-0529', 'Eunice', 'Mkumbo', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 201, 'S5098-0538', 'Beatrice', 'Mwanga', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 202, 'S5098-0545', 'Leticia', 'Mtepa', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 203, 'S5098-0563', 'Victoria', 'Mwakalinga', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 204, 'S5098-0571', 'Manasseh', 'Mwakalukwa', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 206, 'S5098-0516', 'Zebulun', 'Mkude', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 207, 'S5098-0521', 'Issachar', 'Mwalongo', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 208, 'S5098-0531', 'Gad', 'Mwandosya', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 219, 'S5098-0552', 'Flora', 'Mwagikana', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 220, 'S5098-0569', 'Linda', 'Mkumbo', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 221, 'S5098-0506', 'Tatu', 'Mwakalukwa', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 222, 'S5098-0513', 'Mwajuma', 'Mkumbo', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 223, 'S5098-0527', 'Zawadi', 'Mtepa', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 224, 'S5098-0535', 'Reuben', 'Mwangoka', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 225, 'S5098-0539', 'Levi', 'Mkinda', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 226, 'S5098-0544', 'Judah', 'Mwakasaka', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 227, 'S5098-0560', 'Simeon', 'Mngumi', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 228, 'S5098-0565', 'Gad', 'Mkumbo', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 239, 'S5098-0524', 'Paulina', 'Mkude', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 240, 'S5098-0536', 'Salome', 'Mwangosi', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 241, 'S5098-0541', 'Rehema', 'Mkuchika', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 242, 'S5098-0548', 'Pili', 'Mwaibula', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 243, 'S5098-0559', 'Sijali', 'Mwinuka', 'HLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 244, 'S5098-0572', 'Naphtali', 'Mwakapalala', 'HGF', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 246, 'S5098-0514', 'Samuel', 'Mkumbo', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 247, 'S5098-0526', 'Solomon', 'Mwagikana', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 248, 'S5098-0534', 'Reuben', 'Mkumbo', 'HKL', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 251, 'S5098-0504', 'patrick', 'camara', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-02-06 12:40:23', 1, '2026-02-06 12:41:07', 'Graduated', 1),
(0, 0, 'S5098-0503', 'tazan ', 'thazan', 'HGE', 'Form Five', '2026', 'Transferred from Form Five', '2026-02-07 17:17:47', 1, '2026-02-07 17:18:28', 'Transferred', 1),
(0, 7, 'S5098-0563', 'jamary', 'smith', 'EGM', 'Form Six', '2026', 'Graduated from Form Six', '2026-04-21 10:11:44', 1, '2026-04-21 10:12:47', 'Graduated', 1),
(0, 9, 'S5098-0502', 'juju', 'yusla', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-04-21 10:11:44', 1, '2026-04-21 10:12:47', 'Graduated', 1),
(0, 13, 'S5098-0514', 'halima', 'mussa', 'HGL', 'Form Six', '2026', 'Graduated from Form Six', '2026-04-21 10:11:44', 1, '2026-04-21 10:12:47', 'Graduated', 1),
(0, 65, 'S5098-0551', 'Juma', 'Kisanga', 'KLF', 'Form Six', '2026', 'Graduated from Form Six', '2026-04-21 10:11:44', 1, '2026-04-21 10:12:47', 'Graduated', 1),
(0, 85, 'S5098-0510', 'Samson', 'Mlinga', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-04-21 10:11:44', 1, '2026-04-21 10:12:47', 'Graduated', 1),
(0, 409, 'S5098-0503', 'princess', 'toy', 'HGE', 'Form Six', '2026', 'Graduated from Form Six', '2026-04-21 10:15:14', 1, '2026-04-21 10:18:07', 'Graduated', 1),
(0, 410, 'S5098-0515', 'agness', 'world', 'HGK', 'Form Six', '2026', 'Graduated from Form Six', '2026-04-21 10:15:14', 1, '2026-04-21 10:18:07', 'Graduated', 1);

-- --------------------------------------------------------

--
-- Table structure for table `student_login_attempts`
--

CREATE TABLE `student_login_attempts` (
  `id` int(11) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `success` tinyint(1) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_login_attempts`
--

INSERT INTO `student_login_attempts` (`id`, `identifier`, `success`, `ip_address`, `user_agent`, `attempt_time`, `school_id`) VALUES
(3, 'ADM123F', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 00:15:22', 1),
(4, 'ADM148M', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 01:56:00', 1),
(5, 'ADM148M', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 01:59:19', 1),
(6, 'ADM035M', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 05:39:17', 1),
(7, 'tz@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 06:37:43', 1),
(8, 'tz@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 06:37:51', 1),
(9, 'tz@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 06:38:37', 1),
(10, 'franc@gail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 06:39:07', 1),
(11, 'franc@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 06:39:51', 1),
(12, 'franc@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 06:50:52', 1),
(13, 'franc@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 06:51:12', 1),
(14, 'franc@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 06:53:16', 1),
(15, 'franc@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 06:53:29', 1),
(16, 'tz@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-08 06:57:26', 1),
(17, 'tz@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 07:03:04', 1),
(18, 'tz@gmail.com', 0, '192.168.1.128', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 14:41:51', 1),
(19, 'franc@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 11:46:19', 1),
(20, 'franc@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 11:46:24', 1),
(21, 'franc@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 11:46:27', 1),
(22, 'franc@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 11:46:30', 1),
(23, 'franc@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 11:46:33', 1),
(24, 'ashuu@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 12:15:20', 1),
(25, 'ashuu@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 12:15:48', 1),
(26, 'ashuu@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 12:16:04', 1),
(27, 'ashuu@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 12:16:34', 1),
(28, 'ashuu@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 12:16:45', 1),
(29, 'bbamfu@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 12:47:11', 1),
(30, 'admin@muyovozi.ac.tz', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 13:13:05', 1),
(31, 'admin@muyovozi.ac.tz', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 13:13:15', 1),
(32, 'sam@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 13:14:08', 1),
(33, 'sam@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 13:19:21', 1),
(34, 'sam@gmail.com', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 13:20:17', 1),
(36, 'e4445', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 13:34:54', 1),
(45, 'e444', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 15:55:22', 1),
(46, 'e444', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 16:05:54', 1),
(47, 'e444', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 16:08:45', 1),
(48, 'e444', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 16:08:57', 1),
(49, 'e444', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 16:09:26', 1),
(50, 'e444', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 19:30:30', 1),
(51, 'e444', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 08:57:02', 1),
(53, '01', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 09:01:49', 1),
(54, '0001', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 09:02:44', 1),
(55, '01', 0, '192.168.1.110', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 09:22:26', 1),
(56, 'e444', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 17:41:51', 1),
(57, 'e444', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 10:56:11', 1),
(58, 'e444', 1, '192.168.1.110', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 11:06:33', 1),
(59, 'y78', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:12:41', 1),
(60, 'y78', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:12:53', 1),
(61, 'e444', 0, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 17:57:32', 1);

-- --------------------------------------------------------

--
-- Table structure for table `student_login_logs`
--

CREATE TABLE `student_login_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `action` varchar(225) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_login_logs`
--

INSERT INTO `student_login_logs` (`id`, `student_id`, `ip_address`, `action`, `user_agent`, `login_time`, `school_id`) VALUES
(1, 221, '::1', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 00:15:22', 1),
(2, 246, '::1', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 01:56:00', 1),
(3, 246, '::1', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 01:59:19', 1),
(4, 53, '::1', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 05:39:17', 1),
(5, 251, '::1', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 13:21:28', 1),
(6, 251, '::1', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 14:19:26', 1),
(8, 251, '::1', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 15:55:22', 1),
(9, 251, '::1', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 16:05:54', 1),
(10, 251, '::1', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 16:09:26', 1),
(11, 251, '::1', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 19:30:30', 1),
(12, 251, '::1', 'Password Reset', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 21:24:23', 1),
(13, 251, '::1', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 08:57:02', 1),
(14, 27, '::1', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 09:02:44', 1),
(15, 251, '::1', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-11 17:41:51', 1),
(16, 251, '::1', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', '2026-03-13 10:56:11', 1),
(17, 251, '192.168.1.110', '', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 11:06:33', 1),
(18, 409, '::1', '', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:12:53', 1);

-- --------------------------------------------------------

--
-- Table structure for table `student_payments`
--

CREATE TABLE `student_payments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','bank_transfer','mobile_money') DEFAULT 'cash',
  `reference_number` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_payments`
--

INSERT INTO `student_payments` (`id`, `student_id`, `amount`, `payment_date`, `payment_method`, `reference_number`, `status`, `notes`, `recorded_by`, `created_at`, `school_id`) VALUES
(10, 237, 1000.00, '2026-04-21', 'cash', '', 'completed', '', 32, '2026-04-21 16:28:03', 1),
(11, 18, 50000.00, '2026-04-21', 'cash', '', 'completed', '', 32, '2026-04-21 17:00:16', 1),
(12, 205, 30000.00, '2026-04-21', 'cash', '', 'completed', '', 32, '2026-04-21 19:24:04', 1),
(13, 409, 5500.00, '2026-04-22', 'cash', '', 'completed', '', 32, '2026-04-22 07:40:26', 1),
(14, 409, 74000.00, '2026-04-22', 'cash', '', 'completed', '', 32, '2026-04-22 07:43:20', 1),
(15, 409, 500.00, '2026-04-22', 'cash', '', 'completed', '', 32, '2026-04-22 07:43:54', 1),
(16, 205, 30000.00, '2026-04-22', 'cash', '78987654', 'completed', 'very good', 32, '2026-04-22 08:03:39', 1),
(17, 27, 40000.00, '2026-04-22', 'cash', '', 'completed', '', 32, '2026-04-22 12:34:26', 1),
(18, 28, 10000.00, '2026-04-23', 'cash', '', 'completed', '', 32, '2026-04-23 18:27:12', 1);

-- --------------------------------------------------------

--
-- Table structure for table `subject_result_entry_log`
--

CREATE TABLE `subject_result_entry_log` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject` varchar(20) NOT NULL,
  `exam_type_id` int(11) NOT NULL,
  `form_level` enum('Form Five','Form Six') NOT NULL,
  `entry_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subject_result_entry_log`
--

INSERT INTO `subject_result_entry_log` (`id`, `teacher_id`, `subject`, `exam_type_id`, `form_level`, `entry_date`, `ip_address`, `school_id`) VALUES
(1, 35, 'b_math', 20, 'Form Five', '2026-04-03 06:47:20', '::1', 1),
(2, 35, 'b_math', 20, 'Form Five', '2026-04-03 06:49:28', '::1', 1),
(3, 35, 'b_math', 20, 'Form Five', '2026-04-03 06:49:31', '::1', 1),
(4, 35, 'b_math', 20, 'Form Five', '2026-04-03 06:49:35', '::1', 1),
(5, 35, 'b_math', 20, 'Form Five', '2026-04-03 06:50:33', '::1', 1),
(6, 35, 'b_math', 20, 'Form Five', '2026-04-03 09:21:14', '::1', 1),
(7, 35, 'b_math', 20, 'Form Five', '2026-04-03 09:27:27', '::1', 1),
(8, 35, 'b_math', 20, 'Form Five', '2026-04-03 09:55:00', '::1', 1);

-- --------------------------------------------------------

--
-- Table structure for table `subject_teacher_assignments`
--

CREATE TABLE `subject_teacher_assignments` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject` varchar(20) NOT NULL,
  `form_level` enum('Form Five','Form Six') NOT NULL,
  `academic_year` year(4) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `can_enter_results` tinyint(1) DEFAULT 1,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subject_teacher_assignments`
--

INSERT INTO `subject_teacher_assignments` (`id`, `teacher_id`, `subject`, `form_level`, `academic_year`, `is_primary`, `can_enter_results`, `assigned_by`, `assigned_at`, `updated_at`, `school_id`) VALUES
(13, 35, 'eco', 'Form Five', '2026', 0, 1, 32, '2026-04-12 14:04:41', '2026-04-12 14:04:41', 1),
(14, 35, 'eco', 'Form Six', '2026', 0, 1, 32, '2026-04-12 14:04:58', '2026-04-12 14:04:58', 1),
(15, 35, 'b_math', 'Form Six', '2026', 0, 1, 32, '2026-04-12 14:05:15', '2026-04-12 14:05:15', 1);

-- --------------------------------------------------------

--
-- Table structure for table `super_admins`
--

CREATE TABLE `super_admins` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('Super Admin','Account Manager','Support','Developer') NOT NULL DEFAULT 'Super Admin',
  `password` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `super_admins`
--

INSERT INTO `super_admins` (`id`, `first_name`, `last_name`, `email`, `phone`, `role`, `password`, `profile_image`, `last_login`, `last_login_ip`, `status`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'Tzone', 'IT', 'tzone1@gmail.com', '255714343162', 'Super Admin', '$2y$10$ySOHa0diO137FUZdOCXXee6yQZlQ4Lg.FEBJIT4m0iFoODTqzTH.u', NULL, '2026-06-02 15:23:34', '::1', 1, '2026-06-02 08:31:43', '2026-06-02 12:23:34', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `support_messages`
--

CREATE TABLE `support_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('admin','student') NOT NULL DEFAULT 'admin',
  `user_name` varchar(200) NOT NULL,
  `user_role` varchar(100) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','replied','closed') DEFAULT 'pending',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'Admin ID who is handling this',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_messages`
--

INSERT INTO `support_messages` (`id`, `user_id`, `user_type`, `user_name`, `user_role`, `subject`, `message`, `status`, `priority`, `assigned_to`, `created_at`, `updated_at`, `school_id`) VALUES
(1, 29, 'admin', 'bamfu bamfu', 'PS', 'ninashida ya system', 'naomba msaada', 'closed', 'normal', NULL, '2026-03-14 15:14:23', '2026-04-02 10:39:12', 1),
(2, 35, 'admin', 'agness taze', 'Dormitory Teacher', 'hello', 'How can I view my results?', 'pending', 'normal', NULL, '2026-04-01 22:09:58', '2026-04-01 22:09:58', 1);

-- --------------------------------------------------------

--
-- Table structure for table `support_replies`
--

CREATE TABLE `support_replies` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `reply_by` int(11) NOT NULL COMMENT 'Admin ID who replied',
  `reply_by_name` varchar(200) NOT NULL,
  `reply_by_role` varchar(100) DEFAULT NULL,
  `reply_message` text NOT NULL,
  `is_private` tinyint(1) DEFAULT 0 COMMENT '1 = private note, 0 = public reply',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_replies`
--

INSERT INTO `support_replies` (`id`, `message_id`, `reply_by`, `reply_by_name`, `reply_by_role`, `reply_message`, `is_private`, `created_at`, `school_id`) VALUES
(1, 1, 31, 'Tzone TZ', 'Head Master', 'msaada gani huo', 0, '2026-03-14 15:15:22', 1);

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` int(11) NOT NULL,
  `team_name` varchar(100) NOT NULL,
  `team_type` enum('Form Five Combination','Form Six Combination','Staff') NOT NULL,
  `combination_code` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`id`, `team_name`, `team_type`, `combination_code`, `is_active`, `created_at`, `school_id`) VALUES
(1, 'HGE Form Five', 'Form Five Combination', 'HGE', 1, '2026-03-31 16:51:42', 1),
(2, 'HGL Form Five', 'Form Five Combination', 'HGL', 1, '2026-03-31 16:51:42', 1),
(3, 'HGK Form Five', 'Form Five Combination', 'HGK', 1, '2026-03-31 16:51:42', 1),
(4, 'HKL Form Five', 'Form Five Combination', 'HKL', 1, '2026-03-31 16:51:42', 1),
(5, 'KLF Form Five', 'Form Five Combination', 'KLF', 1, '2026-03-31 16:51:42', 1),
(6, 'EGM Form Five', 'Form Five Combination', 'EGM', 1, '2026-03-31 16:51:42', 1),
(7, 'HLF Form Five', 'Form Five Combination', 'HLF', 1, '2026-03-31 16:51:42', 1),
(8, 'HGF Form Five', 'Form Five Combination', 'HGF', 1, '2026-03-31 16:51:42', 1),
(9, 'HGE Form Six', 'Form Six Combination', 'HGE', 1, '2026-03-31 16:51:42', 1),
(10, 'HGL Form Six', 'Form Six Combination', 'HGL', 1, '2026-03-31 16:51:42', 1),
(11, 'HGK Form Six', 'Form Six Combination', 'HGK', 1, '2026-03-31 16:51:42', 1),
(12, 'HKL Form Six', 'Form Six Combination', 'HKL', 1, '2026-03-31 16:51:42', 1),
(13, 'KLF Form Six', 'Form Six Combination', 'KLF', 1, '2026-03-31 16:51:42', 1),
(14, 'EGM Form Six', 'Form Six Combination', 'EGM', 1, '2026-03-31 16:51:42', 1),
(15, 'HLF Form Six', 'Form Six Combination', 'HLF', 1, '2026-03-31 16:51:42', 1),
(16, 'HGF Form Six', 'Form Six Combination', 'HGF', 1, '2026-03-31 16:51:42', 1),
(17, 'Staff Team', 'Staff', NULL, 1, '2026-03-31 16:51:42', 1);

-- --------------------------------------------------------

--
-- Table structure for table `team_participants`
--

CREATE TABLE `team_participants` (
  `id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `participant_type` enum('Student','Staff') NOT NULL,
  `participant_id` int(11) NOT NULL,
  `position` varchar(50) DEFAULT NULL,
  `jersey_number` varchar(10) DEFAULT NULL,
  `is_captain` tinyint(1) DEFAULT 0,
  `joined_date` date DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `theme_settings`
--

CREATE TABLE `theme_settings` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tournaments`
--

CREATE TABLE `tournaments` (
  `id` int(11) NOT NULL,
  `tournament_name` varchar(100) NOT NULL,
  `game_type_id` int(11) NOT NULL,
  `season` varchar(20) DEFAULT NULL,
  `year` year(4) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Upcoming','Ongoing','Completed','Cancelled') DEFAULT 'Upcoming',
  `is_archived` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tournaments`
--

INSERT INTO `tournaments` (`id`, `tournament_name`, `game_type_id`, `season`, `year`, `start_date`, `end_date`, `description`, `status`, `is_archived`, `created_by`, `created_at`, `updated_at`, `school_id`) VALUES
(4, 'Mbuzi cup 2026', 1, '2026', '2026', '2026-04-01', '2026-04-11', '', 'Upcoming', 0, 32, '2026-03-31 19:06:34', '2026-03-31 19:06:34', 1),
(6, 'Mbuzi cup 2026 net', 2, '2026', '2026', '2026-04-01', '2026-04-24', '', 'Upcoming', 0, 32, '2026-04-01 09:47:23', '2026-04-01 09:47:23', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tournament_stages`
--

CREATE TABLE `tournament_stages` (
  `id` int(11) NOT NULL,
  `stage_name` varchar(50) NOT NULL,
  `stage_order` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `color_code` varchar(7) DEFAULT '#6c757d',
  `bg_color` varchar(7) DEFAULT '#e9ecef'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tournament_stages`
--

INSERT INTO `tournament_stages` (`id`, `stage_name`, `stage_order`, `description`, `created_at`, `color_code`, `bg_color`) VALUES
(1, 'Group Stage', 1, 'Group stage matches', '2026-03-31 16:51:42', '#ffc107', '#fff3cd'),
(2, 'Quarter Finals', 2, 'Quarter final matches', '2026-03-31 16:51:42', '#fd7e14', '#fff0e6'),
(3, 'Semi Finals', 3, 'Semi final matches', '2026-03-31 16:51:42', '#20c997', '#d1f7e9'),
(4, 'Final', 4, 'Championship final match', '2026-03-31 16:51:42', '#dc3545', '#f8d7da'),
(5, '3rd Place Playoff', 5, 'Third place playoff match', '2026-03-31 16:51:42', '#6c757d', '#e9ecef');

-- --------------------------------------------------------

--
-- Table structure for table `tournament_teams`
--

CREATE TABLE `tournament_teams` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `group_name` varchar(10) DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `matches_played` int(11) DEFAULT 0,
  `wins` int(11) DEFAULT 0,
  `draws` int(11) DEFAULT 0,
  `losses` int(11) DEFAULT 0,
  `goals_for` int(11) DEFAULT 0,
  `goals_against` int(11) DEFAULT 0,
  `goal_difference` int(11) DEFAULT 0,
  `status` enum('Active','Eliminated','Winner','RunnerUp') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tournament_teams`
--

INSERT INTO `tournament_teams` (`id`, `tournament_id`, `team_id`, `group_name`, `points`, `matches_played`, `wins`, `draws`, `losses`, `goals_for`, `goals_against`, `goal_difference`, `status`, `created_at`) VALUES
(7, 4, 1, NULL, 1, 2, 0, 1, 1, 1, 3, -2, 'Active', '2026-03-31 19:07:06'),
(8, 4, 8, NULL, 2, 2, 0, 2, 0, 2, 2, 0, 'Active', '2026-03-31 19:07:06'),
(9, 4, 14, NULL, 0, 1, 0, 0, 1, 2, 4, -2, 'Active', '2026-03-31 19:25:49'),
(10, 4, 10, NULL, 6, 2, 2, 0, 0, 7, 4, 3, 'Active', '2026-03-31 19:25:49'),
(11, 4, 5, NULL, 6, 2, 2, 0, 0, 11, 3, 8, 'Active', '2026-04-01 06:06:31'),
(12, 4, 7, NULL, 0, 1, 0, 0, 1, 3, 9, -6, 'Active', '2026-04-01 06:06:31'),
(15, 4, 4, NULL, 0, 1, 0, 0, 1, 4, 5, -1, 'Active', '2026-04-01 09:36:51'),
(16, 4, 11, NULL, 3, 2, 1, 0, 1, 7, 7, 0, 'Active', '2026-04-01 09:36:51'),
(17, 4, 6, NULL, 1, 1, 0, 1, 0, 1, 1, 0, 'Active', '2026-04-01 12:13:38'),
(18, 6, 6, NULL, 0, 2, 0, 0, 2, 62, 76, -14, 'Active', '2026-04-01 12:31:12'),
(19, 6, 4, NULL, 3, 1, 1, 0, 0, 73, 60, 13, 'Active', '2026-04-01 12:31:12'),
(20, 6, 1, NULL, 3, 1, 1, 0, 0, 3, 2, 1, 'Active', '2026-04-01 13:41:46'),
(21, 6, 8, 'A', 0, 0, 0, 0, 0, 0, 0, 0, 'Active', '2026-04-23 18:54:18');

-- --------------------------------------------------------

--
-- Stand-in structure for view `unassigned_students_view`
-- (See below for the actual view)
--
CREATE TABLE `unassigned_students_view` (
`id` int(11)
,`index_number` varchar(50)
,`student_name` varchar(302)
,`sex` enum('Male','Female')
,`class` enum('Form Five','Form Six','Leavers','Graduated')
,`combination` enum('HGE','HGL','HGK','HKL','KLF','EGM','HLF','HGF')
,`is_leaver` tinyint(1)
,`student_status` tinyint(1)
);

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `preference_key` varchar(100) NOT NULL,
  `preference_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `school_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `dormitory_occupancy_summary`
--
DROP TABLE IF EXISTS `dormitory_occupancy_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `dormitory_occupancy_summary`  AS SELECT `d`.`id` AS `id`, `d`.`dorm_name` AS `dorm_name`, `d`.`dorm_type` AS `dorm_type`, `d`.`rooms_count` AS `rooms_count`, `d`.`capacity_per_room` AS `capacity_per_room`, `d`.`total_capacity` AS `total_capacity`, `d`.`current_occupancy` AS `current_occupancy`, greatest(`d`.`total_capacity` - `d`.`current_occupancy`,0) AS `available_beds`, round(`d`.`current_occupancy` * 100.0 / nullif(`d`.`total_capacity`,0),2) AS `occupancy_percentage`, `d`.`status` AS `dormitory_status`, `d`.`description` AS `description`, count(distinct `dr`.`id`) AS `total_rooms`, count(distinct case when `dr`.`status` = 'Available' then `dr`.`id` end) AS `available_rooms`, count(distinct case when `dr`.`status` = 'Full' then `dr`.`id` end) AS `full_rooms`, count(distinct case when `dr`.`status` = 'Maintenance' then `dr`.`id` end) AS `maintenance_rooms`, (select count(distinct `sd`.`id`) from (`student_dormitory` `sd` join `students` `s` on(`sd`.`student_id` = `s`.`id`)) where `sd`.`dormitory_id` = `d`.`id` and `sd`.`status` = 'Active' and `s`.`is_leaver` = 0 and `s`.`class` in ('Form Five','Form Six')) AS `active_student_count` FROM (`dormitories` `d` left join `dormitory_rooms` `dr` on(`d`.`id` = `dr`.`dormitory_id`)) GROUP BY `d`.`id`, `d`.`dorm_name`, `d`.`dorm_type`, `d`.`rooms_count`, `d`.`capacity_per_room`, `d`.`total_capacity`, `d`.`current_occupancy`, `d`.`status`, `d`.`description` ORDER BY `d`.`dorm_type` ASC, `d`.`dorm_name` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `payment_summary`
--
DROP TABLE IF EXISTS `payment_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `payment_summary`  AS SELECT `student_payments`.`student_id` AS `student_id`, sum(case when `student_payments`.`status` = 'completed' then `student_payments`.`amount` else 0 end) AS `total_paid`, max(`student_payments`.`payment_date`) AS `last_payment_date`, CASE WHEN sum(case when `student_payments`.`status` = 'completed' then `student_payments`.`amount` else 0 end) >= 80000 THEN 'completed' WHEN sum(case when `student_payments`.`status` = 'completed' then `student_payments`.`amount` else 0 end) > 0 THEN 'partial' ELSE 'pending' END AS `payment_status` FROM `student_payments` GROUP BY `student_payments`.`student_id` ;

-- --------------------------------------------------------

--
-- Structure for view `room_availability_view`
--
DROP TABLE IF EXISTS `room_availability_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `room_availability_view`  AS SELECT `dr`.`id` AS `room_id`, `d`.`dorm_name` AS `dorm_name`, `d`.`dorm_type` AS `dorm_type`, `dr`.`room_number` AS `room_number`, `dr`.`room_label` AS `room_label`, `dr`.`capacity` AS `capacity`, `dr`.`current_occupancy` AS `current_occupancy`, greatest(`dr`.`capacity` - `dr`.`current_occupancy`,0) AS `available_beds`, `dr`.`status` AS `room_status`, `d`.`status` AS `dormitory_status`, CASE WHEN `dr`.`current_occupancy` = 0 THEN 'Empty' WHEN `dr`.`current_occupancy` < `dr`.`capacity` THEN 'Partially Occupied' WHEN `dr`.`current_occupancy` >= `dr`.`capacity` THEN 'Full' ELSE 'Unknown' END AS `occupancy_status`, (select count(0) from (`student_dormitory` `sd` join `students` `s` on(`sd`.`student_id` = `s`.`id`)) where `sd`.`room_id` = `dr`.`id` and `sd`.`status` = 'Active' and `s`.`is_leaver` = 0 and `s`.`class` in ('Form Five','Form Six')) AS `active_students_in_room`, `dr`.`created_at` AS `created_at`, `dr`.`updated_at` AS `updated_at` FROM (`dormitory_rooms` `dr` join `dormitories` `d` on(`dr`.`dormitory_id` = `d`.`id`)) WHERE `d`.`status` in ('Active','Full') ORDER BY `d`.`dorm_type` ASC, `d`.`dorm_name` ASC, cast(substr(`dr`.`room_number`,2) as unsigned) ASC, `dr`.`room_number` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `store_summary`
--
DROP TABLE IF EXISTS `store_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `store_summary`  AS SELECT `st`.`id` AS `id`, `st`.`tool_name` AS `tool_name`, `st`.`total_quantity` AS `total_quantity`, `st`.`issued_to_students` AS `issued_to_students`, `st`.`used_quantity` AS `used_quantity`, `st`.`available_quantity` AS `available_quantity`, `st`.`unit` AS `unit`, round(`st`.`used_quantity` / nullif(`st`.`total_quantity` + `st`.`issued_to_students`,0) * 100,1) AS `usage_percentage`, `st`.`created_at` AS `created_at`, `st`.`updated_at` AS `updated_at` FROM `store_tools` AS `st` ;

-- --------------------------------------------------------

--
-- Structure for view `student_equipment_with_store`
--
DROP TABLE IF EXISTS `student_equipment_with_store`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `student_equipment_with_store`  AS SELECT `se`.`id` AS `id`, `se`.`student_id` AS `student_id`, `se`.`item_name` AS `item_name`, `se`.`quantity` AS `quantity`, `se`.`status` AS `status`, `se`.`submitted_at` AS `submitted_at`, `s`.`first_name` AS `first_name`, `s`.`last_name` AS `last_name`, `s`.`index_number` AS `index_number`, `s`.`class` AS `class`, `s`.`combination` AS `combination`, `st`.`tool_name` AS `store_tool_name`, `st`.`total_quantity` AS `store_total`, `st`.`issued_to_students` AS `issued_to_students`, `st`.`used_quantity` AS `used_quantity`, `st`.`available_quantity` AS `store_available` FROM ((`student_equipment` `se` left join `students` `s` on(`se`.`student_id` = `s`.`id`)) left join `store_tools` `st` on(trim(substring_index(`se`.`item_name`,' (',1)) = `st`.`tool_name`)) ;

-- --------------------------------------------------------

--
-- Structure for view `unassigned_students_view`
--
DROP TABLE IF EXISTS `unassigned_students_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `unassigned_students_view`  AS SELECT `s`.`id` AS `id`, `s`.`index_number` AS `index_number`, concat(`s`.`first_name`,' ',coalesce(`s`.`second_name`,''),' ',`s`.`last_name`) AS `student_name`, `s`.`sex` AS `sex`, `s`.`class` AS `class`, `s`.`combination` AS `combination`, `s`.`is_leaver` AS `is_leaver`, `s`.`status` AS `student_status` FROM `students` AS `s` WHERE `s`.`status` = 1 AND `s`.`is_leaver` = 0 AND !(`s`.`id` in (select `student_dormitory`.`student_id` from `student_dormitory` where `student_dormitory`.`status` = 'Active')) AND `s`.`class` in ('Form Five','Form Six') ORDER BY `s`.`sex` ASC, `s`.`class` ASC, `s`.`last_name` ASC, `s`.`first_name` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_school_id` (`school_id`),
  ADD KEY `idx_super_admin` (`is_super_admin`);

--
-- Indexes for table `admin_login_attempts`
--
ALTER TABLE `admin_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_identifier` (`identifier`),
  ADD KEY `idx_attempt_time` (`attempt_time`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_number` (`application_number`),
  ADD KEY `idx_application_number` (`application_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_program` (`program_applying`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `discipline_records`
--
ALTER TABLE `discipline_records`
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `dormitories`
--
ALTER TABLE `dormitories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dorm_name` (`dorm_name`),
  ADD KEY `idx_dorm_type` (`dorm_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dormitories_type_status` (`dorm_type`,`status`),
  ADD KEY `idx_dormitories_occupancy` (`current_occupancy`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `dormitory_rooms`
--
ALTER TABLE `dormitory_rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dorm_room_unique` (`dormitory_id`,`room_number`),
  ADD KEY `dormitory_id` (`dormitory_id`),
  ADD KEY `idx_room_status` (`status`),
  ADD KEY `idx_room_number` (`room_number`),
  ADD KEY `idx_dormitory_rooms_dormitory_status` (`dormitory_id`,`status`),
  ADD KEY `idx_dormitory_rooms_occupancy` (`current_occupancy`,`capacity`),
  ADD KEY `idx_dormitory_rooms_number_status` (`room_number`,`status`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `exam_types`
--
ALTER TABLE `exam_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `exam_code` (`exam_code`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `food_stock`
--
ALTER TABLE `food_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `food_stock_history`
--
ALTER TABLE `food_stock_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `food_id` (`food_id`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `form_five_results`
--
ALTER TABLE `form_five_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `exam_type_id` (`exam_type_id`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `form_six_results`
--
ALTER TABLE `form_six_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_exam` (`student_id`,`exam_type_id`),
  ADD KEY `exam_type_id` (`exam_type_id`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `game_types`
--
ALTER TABLE `game_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `game_name` (`game_name`);

--
-- Indexes for table `leaver_equipment_history`
--
ALTER TABLE `leaver_equipment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `library_assignments`
--
ALTER TABLE `library_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `login_notifications`
--
ALTER TABLE `login_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `maintenance_assignments`
--
ALTER TABLE `maintenance_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_assigned_date` (`assigned_date`),
  ADD KEY `idx_return_date` (`return_date`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `maintenance_items`
--
ALTER TABLE `maintenance_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD KEY `idx_item_type` (`item_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_location` (`location`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_log_type` (`log_type`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `maintenance_staff_assignments`
--
ALTER TABLE `maintenance_staff_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_assigned_date` (`assigned_date`),
  ADD KEY `idx_return_date` (`return_date`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tournament_id` (`tournament_id`),
  ADD KEY `game_type_id` (`game_type_id`),
  ADD KEY `stage_id` (`stage_id`),
  ADD KEY `team1_id` (`team1_id`),
  ADD KEY `team2_id` (`team2_id`),
  ADD KEY `winner_team_id` (`winner_team_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `matches_schedule`
--
ALTER TABLE `matches_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tournament_id` (`tournament_id`),
  ADD KEY `stage_id` (`stage_id`),
  ADD KEY `team1_id` (`team1_id`),
  ADD KEY `team2_id` (`team2_id`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `match_officials`
--
ALTER TABLE `match_officials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_match_official` (`match_id`,`admin_id`,`role`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `match_statistics`
--
ALTER TABLE `match_statistics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `team_id` (`team_id`),
  ADD KEY `idx_match_id` (`match_id`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `non_staff`
--
ALTER TABLE `non_staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone_number` (`phone_number`),
  ADD UNIQUE KEY `nida` (`nida`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_position` (`position`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `notification_views`
--
ALTER TABLE `notification_views`
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user` (`user_type`,`user_id`),
  ADD KEY `idx_expiry` (`expires_at`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `productions`
--
ALTER TABLE `productions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_production_category` (`category`),
  ADD KEY `idx_production_date` (`production_date`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `production_categories`
--
ALTER TABLE `production_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_category_status` (`status`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `production_logs`
--
ALTER TABLE `production_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_id` (`production_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `production_uses`
--
ALTER TABLE `production_uses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_id` (`production_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `ps_documents`
--
ALTER TABLE `ps_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_file_type` (`file_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_ps_status` (`ps_status`),
  ADD KEY `idx_needs_review` (`needs_ps_review`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `ps_document_feedback`
--
ALTER TABLE `ps_document_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document_id` (`document_id`),
  ADD KEY `idx_commenter` (`commenter_id`),
  ADD KEY `idx_parent` (`parent_comment_id`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `ps_document_logs`
--
ALTER TABLE `ps_document_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document_id` (`document_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `ps_notifications`
--
ALTER TABLE `ps_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_target_role` (`target_role`),
  ADD KEY `idx_document_id` (`document_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `results_auto_save`
--
ALTER TABLE `results_auto_save`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_student_exam` (`student_id`,`exam_type_id`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `results_entry_sessions`
--
ALTER TABLE `results_entry_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `exam_type_id` (`exam_type_id`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `room_status_logs`
--
ALTER TABLE `room_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_changed_at` (`changed_at`),
  ADD KEY `idx_room_status_logs_room_date` (`room_id`,`changed_at`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `school_code` (`school_code`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `shule_salama_comments`
--
ALTER TABLE `shule_salama_comments`
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `shule_salama_posts`
--
ALTER TABLE `shule_salama_posts`
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `shule_salama_views`
--
ALTER TABLE `shule_salama_views`
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `sports_equipment`
--
ALTER TABLE `sports_equipment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_quantity` (`quantity`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `sports_history`
--
ALTER TABLE `sports_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tournament_id` (`tournament_id`),
  ADD KEY `archived_by` (`archived_by`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `store_tools`
--
ALTER TABLE `store_tools`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tool_name` (`tool_name`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `store_tools_transactions`
--
ALTER TABLE `store_tools_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tool_name` (`tool_name`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_students_name` (`first_name`,`last_name`),
  ADD KEY `idx_students_parent` (`parent_phone`),
  ADD KEY `idx_student_status` (`status`,`is_leaver`,`class`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `student_dormitory`
--
ALTER TABLE `student_dormitory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dormitory_id` (`dormitory_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_assignment_status` (`status`),
  ADD KEY `idx_student_dormitory_student_status` (`student_id`,`status`),
  ADD KEY `idx_student_dormitory_dormitory_status` (`dormitory_id`,`status`),
  ADD KEY `idx_student_dormitory_room_status` (`room_id`,`status`),
  ADD KEY `idx_student_dormitory_assigned_at` (`assigned_at`),
  ADD KEY `idx_student_dormitory_bed_number` (`bed_number`),
  ADD KEY `idx_student_status` (`student_id`,`status`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `student_equipment`
--
ALTER TABLE `student_equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_item` (`student_id`,`item_name`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `student_graduation_history`
--
ALTER TABLE `student_graduation_history`
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `student_leavers`
--
ALTER TABLE `student_leavers`
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `student_login_attempts`
--
ALTER TABLE `student_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_identifier` (`identifier`),
  ADD KEY `idx_attempt_time` (`attempt_time`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `student_login_logs`
--
ALTER TABLE `student_login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `student_payments`
--
ALTER TABLE `student_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `subject_result_entry_log`
--
ALTER TABLE `subject_result_entry_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `exam_type_id` (`exam_type_id`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `subject_teacher_assignments`
--
ALTER TABLE `subject_teacher_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment` (`teacher_id`,`subject`,`form_level`,`academic_year`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `super_admins`
--
ALTER TABLE `super_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_assigned_to` (`assigned_to`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `support_replies`
--
ALTER TABLE `support_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_message_id` (`message_id`),
  ADD KEY `idx_reply_by` (`reply_by`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_combination_team` (`team_type`,`combination_code`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `team_participants`
--
ALTER TABLE `team_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_participant` (`team_id`,`participant_type`,`participant_id`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `theme_settings`
--
ALTER TABLE `theme_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_admin_setting` (`admin_id`,`setting_key`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `tournaments`
--
ALTER TABLE `tournaments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `game_type_id` (`game_type_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_year` (`year`),
  ADD KEY `idx_is_archived` (`is_archived`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- Indexes for table `tournament_stages`
--
ALTER TABLE `tournament_stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stage_name` (`stage_name`);

--
-- Indexes for table `tournament_teams`
--
ALTER TABLE `tournament_teams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tournament_team` (`tournament_id`,`team_id`),
  ADD KEY `team_id` (`team_id`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_admin_preference` (`admin_id`,`preference_key`),
  ADD KEY `idx_school_id` (`school_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `admin_login_attempts`
--
ALTER TABLE `admin_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=219;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `dormitories`
--
ALTER TABLE `dormitories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `dormitory_rooms`
--
ALTER TABLE `dormitory_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT for table `exam_types`
--
ALTER TABLE `exam_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `food_stock`
--
ALTER TABLE `food_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `food_stock_history`
--
ALTER TABLE `food_stock_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `form_five_results`
--
ALTER TABLE `form_five_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT for table `form_six_results`
--
ALTER TABLE `form_six_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT for table `game_types`
--
ALTER TABLE `game_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `leaver_equipment_history`
--
ALTER TABLE `leaver_equipment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `library_assignments`
--
ALTER TABLE `library_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `login_notifications`
--
ALTER TABLE `login_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance_assignments`
--
ALTER TABLE `maintenance_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `maintenance_items`
--
ALTER TABLE `maintenance_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `maintenance_staff_assignments`
--
ALTER TABLE `maintenance_staff_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `matches`
--
ALTER TABLE `matches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `matches_schedule`
--
ALTER TABLE `matches_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `match_officials`
--
ALTER TABLE `match_officials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `match_statistics`
--
ALTER TABLE `match_statistics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `non_staff`
--
ALTER TABLE `non_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `productions`
--
ALTER TABLE `productions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_categories`
--
ALTER TABLE `production_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `production_logs`
--
ALTER TABLE `production_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_uses`
--
ALTER TABLE `production_uses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ps_documents`
--
ALTER TABLE `ps_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ps_document_feedback`
--
ALTER TABLE `ps_document_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ps_document_logs`
--
ALTER TABLE `ps_document_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ps_notifications`
--
ALTER TABLE `ps_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `results_auto_save`
--
ALTER TABLE `results_auto_save`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `results_entry_sessions`
--
ALTER TABLE `results_entry_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `room_status_logs`
--
ALTER TABLE `room_status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sports_equipment`
--
ALTER TABLE `sports_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sports_history`
--
ALTER TABLE `sports_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_tools`
--
ALTER TABLE `store_tools`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `store_tools_transactions`
--
ALTER TABLE `store_tools_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=412;

--
-- AUTO_INCREMENT for table `student_dormitory`
--
ALTER TABLE `student_dormitory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `student_equipment`
--
ALTER TABLE `student_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `student_login_attempts`
--
ALTER TABLE `student_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `student_login_logs`
--
ALTER TABLE `student_login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `student_payments`
--
ALTER TABLE `student_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `subject_result_entry_log`
--
ALTER TABLE `subject_result_entry_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `subject_teacher_assignments`
--
ALTER TABLE `subject_teacher_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `super_admins`
--
ALTER TABLE `super_admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `support_replies`
--
ALTER TABLE `support_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `team_participants`
--
ALTER TABLE `team_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `theme_settings`
--
ALTER TABLE `theme_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- AUTO_INCREMENT for table `tournaments`
--
ALTER TABLE `tournaments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tournament_stages`
--
ALTER TABLE `tournament_stages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tournament_teams`
--
ALTER TABLE `tournament_teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admins_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_login_attempts`
--
ALTER TABLE `admin_login_attempts`
  ADD CONSTRAINT `admin_login_attempts_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_login_attempts_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_logs_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD CONSTRAINT `contact_messages_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contact_messages_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `discipline_records`
--
ALTER TABLE `discipline_records`
  ADD CONSTRAINT `discipline_records_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `discipline_records_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dormitories`
--
ALTER TABLE `dormitories`
  ADD CONSTRAINT `dormitories_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dormitories_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dormitory_rooms`
--
ALTER TABLE `dormitory_rooms`
  ADD CONSTRAINT `dormitory_rooms_ibfk_1` FOREIGN KEY (`dormitory_id`) REFERENCES `dormitories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dormitory_rooms_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dormitory_rooms_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_types`
--
ALTER TABLE `exam_types`
  ADD CONSTRAINT `exam_types_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_types_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `food_stock`
--
ALTER TABLE `food_stock`
  ADD CONSTRAINT `food_stock_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `food_stock_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `food_stock_history`
--
ALTER TABLE `food_stock_history`
  ADD CONSTRAINT `food_stock_history_ibfk_1` FOREIGN KEY (`food_id`) REFERENCES `food_stock` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `food_stock_history_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `food_stock_history_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `form_five_results`
--
ALTER TABLE `form_five_results`
  ADD CONSTRAINT `form_five_results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `form_five_results_ibfk_2` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `form_five_results_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `form_five_results_ibfk_4` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `form_six_results`
--
ALTER TABLE `form_six_results`
  ADD CONSTRAINT `form_six_results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `form_six_results_ibfk_2` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `form_six_results_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `form_six_results_ibfk_4` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leaver_equipment_history`
--
ALTER TABLE `leaver_equipment_history`
  ADD CONSTRAINT `leaver_equipment_history_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leaver_equipment_history_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `leaver_equipment_history_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leaver_equipment_history_ibfk_4` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `library_assignments`
--
ALTER TABLE `library_assignments`
  ADD CONSTRAINT `library_assignments_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `library_assignments_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_assignments`
--
ALTER TABLE `maintenance_assignments`
  ADD CONSTRAINT `maintenance_assignments_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_assignments_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_items`
--
ALTER TABLE `maintenance_items`
  ADD CONSTRAINT `maintenance_items_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_items_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  ADD CONSTRAINT `maintenance_logs_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_logs_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_staff_assignments`
--
ALTER TABLE `maintenance_staff_assignments`
  ADD CONSTRAINT `maintenance_staff_assignments_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_staff_assignments_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `matches_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `matches_ibfk_2` FOREIGN KEY (`game_type_id`) REFERENCES `game_types` (`id`),
  ADD CONSTRAINT `matches_ibfk_3` FOREIGN KEY (`stage_id`) REFERENCES `tournament_stages` (`id`),
  ADD CONSTRAINT `matches_ibfk_4` FOREIGN KEY (`team1_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `matches_ibfk_5` FOREIGN KEY (`team2_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `matches_ibfk_6` FOREIGN KEY (`winner_team_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `matches_ibfk_7` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `matches_ibfk_8` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `matches_ibfk_9` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `matches_schedule`
--
ALTER TABLE `matches_schedule`
  ADD CONSTRAINT `matches_schedule_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `matches_schedule_ibfk_2` FOREIGN KEY (`stage_id`) REFERENCES `tournament_stages` (`id`),
  ADD CONSTRAINT `matches_schedule_ibfk_3` FOREIGN KEY (`team1_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `matches_schedule_ibfk_4` FOREIGN KEY (`team2_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `matches_schedule_ibfk_5` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `matches_schedule_ibfk_6` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `match_officials`
--
ALTER TABLE `match_officials`
  ADD CONSTRAINT `match_officials_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `match_officials_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `match_officials_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `match_officials_ibfk_4` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `match_statistics`
--
ALTER TABLE `match_statistics`
  ADD CONSTRAINT `match_statistics_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `match_statistics_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `match_statistics_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `match_statistics_ibfk_4` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `non_staff`
--
ALTER TABLE `non_staff`
  ADD CONSTRAINT `non_staff_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `non_staff_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_views`
--
ALTER TABLE `notification_views`
  ADD CONSTRAINT `notification_views_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notification_views_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `password_resets_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `productions`
--
ALTER TABLE `productions`
  ADD CONSTRAINT `productions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `productions_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `productions_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `production_categories`
--
ALTER TABLE `production_categories`
  ADD CONSTRAINT `production_categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `production_categories_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `production_categories_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `production_logs`
--
ALTER TABLE `production_logs`
  ADD CONSTRAINT `production_logs_ibfk_1` FOREIGN KEY (`production_id`) REFERENCES `productions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `production_logs_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `production_logs_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `production_logs_ibfk_4` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `production_uses`
--
ALTER TABLE `production_uses`
  ADD CONSTRAINT `production_uses_ibfk_1` FOREIGN KEY (`production_id`) REFERENCES `productions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `production_uses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `production_uses_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `production_uses_ibfk_4` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ps_documents`
--
ALTER TABLE `ps_documents`
  ADD CONSTRAINT `ps_documents_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ps_documents_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ps_document_feedback`
--
ALTER TABLE `ps_document_feedback`
  ADD CONSTRAINT `fk_feedback_document` FOREIGN KEY (`document_id`) REFERENCES `ps_documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_feedback_parent` FOREIGN KEY (`parent_comment_id`) REFERENCES `ps_document_feedback` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ps_document_feedback_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ps_document_feedback_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ps_document_logs`
--
ALTER TABLE `ps_document_logs`
  ADD CONSTRAINT `fk_log_document` FOREIGN KEY (`document_id`) REFERENCES `ps_documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ps_document_logs_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ps_document_logs_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ps_notifications`
--
ALTER TABLE `ps_notifications`
  ADD CONSTRAINT `fk_notification_document` FOREIGN KEY (`document_id`) REFERENCES `ps_documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ps_notifications_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ps_notifications_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `results_auto_save`
--
ALTER TABLE `results_auto_save`
  ADD CONSTRAINT `results_auto_save_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_auto_save_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `results_entry_sessions`
--
ALTER TABLE `results_entry_sessions`
  ADD CONSTRAINT `results_entry_sessions_ibfk_1` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`),
  ADD CONSTRAINT `results_entry_sessions_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_entry_sessions_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_status_logs`
--
ALTER TABLE `room_status_logs`
  ADD CONSTRAINT `room_status_logs_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `dormitory_rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_status_logs_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `room_status_logs_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_status_logs_ibfk_4` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shule_salama_comments`
--
ALTER TABLE `shule_salama_comments`
  ADD CONSTRAINT `shule_salama_comments_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shule_salama_comments_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shule_salama_posts`
--
ALTER TABLE `shule_salama_posts`
  ADD CONSTRAINT `shule_salama_posts_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shule_salama_posts_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shule_salama_views`
--
ALTER TABLE `shule_salama_views`
  ADD CONSTRAINT `shule_salama_views_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shule_salama_views_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD CONSTRAINT `sms_logs_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sms_logs_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sports_equipment`
--
ALTER TABLE `sports_equipment`
  ADD CONSTRAINT `sports_equipment_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `sports_equipment_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sports_equipment_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sports_history`
--
ALTER TABLE `sports_history`
  ADD CONSTRAINT `sports_history_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sports_history_ibfk_2` FOREIGN KEY (`archived_by`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `sports_history_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sports_history_ibfk_4` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_tools`
--
ALTER TABLE `store_tools`
  ADD CONSTRAINT `store_tools_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `store_tools_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_tools_transactions`
--
ALTER TABLE `store_tools_transactions`
  ADD CONSTRAINT `store_tools_transactions_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `store_tools_transactions_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_dormitory`
--
ALTER TABLE `student_dormitory`
  ADD CONSTRAINT `student_dormitory_ibfk_2` FOREIGN KEY (`dormitory_id`) REFERENCES `dormitories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_dormitory_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `dormitory_rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_dormitory_ibfk_4` FOREIGN KEY (`assigned_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_dormitory_ibfk_5` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_dormitory_ibfk_6` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_equipment`
--
ALTER TABLE `student_equipment`
  ADD CONSTRAINT `student_equipment_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_equipment_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_equipment_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_graduation_history`
--
ALTER TABLE `student_graduation_history`
  ADD CONSTRAINT `student_graduation_history_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_graduation_history_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_leavers`
--
ALTER TABLE `student_leavers`
  ADD CONSTRAINT `student_leavers_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_leavers_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_login_attempts`
--
ALTER TABLE `student_login_attempts`
  ADD CONSTRAINT `student_login_attempts_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_login_attempts_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_login_logs`
--
ALTER TABLE `student_login_logs`
  ADD CONSTRAINT `student_login_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_login_logs_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_login_logs_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_payments`
--
ALTER TABLE `student_payments`
  ADD CONSTRAINT `student_payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_payments_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `student_payments_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_payments_ibfk_4` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subject_result_entry_log`
--
ALTER TABLE `subject_result_entry_log`
  ADD CONSTRAINT `subject_result_entry_log_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `subject_result_entry_log_ibfk_2` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subject_result_entry_log_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subject_result_entry_log_ibfk_4` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subject_teacher_assignments`
--
ALTER TABLE `subject_teacher_assignments`
  ADD CONSTRAINT `subject_teacher_assignments_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subject_teacher_assignments_ibfk_2` FOREIGN KEY (`assigned_by`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `subject_teacher_assignments_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subject_teacher_assignments_ibfk_4` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD CONSTRAINT `support_messages_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_messages_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_replies`
--
ALTER TABLE `support_replies`
  ADD CONSTRAINT `fk_reply_message` FOREIGN KEY (`message_id`) REFERENCES `support_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_replies_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_replies_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teams`
--
ALTER TABLE `teams`
  ADD CONSTRAINT `teams_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teams_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_participants`
--
ALTER TABLE `team_participants`
  ADD CONSTRAINT `team_participants_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_participants_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_participants_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `theme_settings`
--
ALTER TABLE `theme_settings`
  ADD CONSTRAINT `theme_settings_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `theme_settings_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `theme_settings_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tournaments`
--
ALTER TABLE `tournaments`
  ADD CONSTRAINT `tournaments_ibfk_1` FOREIGN KEY (`game_type_id`) REFERENCES `game_types` (`id`),
  ADD CONSTRAINT `tournaments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `tournaments_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tournaments_ibfk_4` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tournament_teams`
--
ALTER TABLE `tournament_teams`
  ADD CONSTRAINT `tournament_teams_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tournament_teams_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_preferences_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_preferences_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
