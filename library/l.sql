CREATE TABLE IF NOT EXISTS library_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('staff', 'student') NOT NULL,
    user_id INT NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    book_title VARCHAR(255) NOT NULL,
    book_number VARCHAR(50) NOT NULL,
    quantity VARCHAR(20) NOT NULL,
    assigned_date DATE NOT NULL,
    short_note TEXT,
    status ENUM('borrowed', 'returned') DEFAULT 'borrowed',
    return_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_type (user_type),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_assigned_date (assigned_date)
);

