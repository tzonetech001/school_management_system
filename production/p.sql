
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
  `profile_image` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_notification_check` timestamp NULL DEFAULT NULL,
  `address` text DEFAULT NULL,
  `updated_by_admin` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- productions.sql
CREATE TABLE IF NOT EXISTS productions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    production_type VARCHAR(100) NOT NULL,
    quantity DECIMAL(10,2) DEFAULT NULL,
    unit VARCHAR(20) DEFAULT NULL,
    amount DECIMAL(10,2) DEFAULT NULL,
    currency VARCHAR(10) DEFAULT 'TZS',
    production_date DATE NOT NULL,
    short_note TEXT,
    uses TEXT,
    created_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE CASCADE
);

-- Create production uses table
CREATE TABLE IF NOT EXISTS production_uses (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    production_id INT(11) NOT NULL,
    use_description TEXT NOT NULL,
    use_date DATE NOT NULL,
    used_quantity DECIMAL(10,2) NOT NULL,
    used_by VARCHAR(100),
    notes TEXT,
    created_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE CASCADE
);

-- Create production categories table
CREATE TABLE IF NOT EXISTS production_categories (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    unit VARCHAR(20),
    status BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default categories
INSERT INTO production_categories (category_name, description, unit) VALUES
('shop', 'School Shop Products', 'items'),
('farm', 'Farm and Plantation Products', 'kg'),
('beekeeping', 'Honey and Bee Products', 'liters'),
('soap', 'Soap Making Products', 'pieces'),
('fish', 'Fish Farming', 'kg'),
('hen', 'Poultry and Hen Products', 'pieces'),
('garden', 'School Garden Products', 'kg');

-- Add this table if not exists
CREATE TABLE IF NOT EXISTS production_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    production_id INT(11),
    action VARCHAR(50) NOT NULL,
    admin_id INT(11) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

-- Update production_categories table to include created_at and updated_at
ALTER TABLE production_categories 
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add created_by to production_categories if needed
ALTER TABLE production_categories 
ADD COLUMN IF NOT EXISTS created_by INT(11) DEFAULT NULL,
ADD FOREIGN KEY IF NOT EXISTS (created_by) REFERENCES admins(id) ON DELETE SET NULL;

-- Add is_active column to productions for soft delete capability
ALTER TABLE productions 
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT 1;

-- Create index for better performance
CREATE INDEX IF NOT EXISTS idx_production_category ON productions(category);
CREATE INDEX IF NOT EXISTS idx_production_date ON productions(production_date);
CREATE INDEX IF NOT EXISTS idx_category_status ON production_categories(status);