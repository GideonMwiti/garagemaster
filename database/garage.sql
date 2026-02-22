-- garage_management_system/database/garage.sql
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `garage_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `garage_management`;

-- Table structure for table `garages`
CREATE TABLE `garages` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `garages` (`id`, `name`, `address`, `phone`, `email`, `tax_id`, `logo_path`, `created_at`, `status`) VALUES
(1, 'AutoCare Pro Garage', '123 Main St, City', '+1-555-0123', 'contact@autocarepro.com', 'TAX-001-2024', NULL, '2024-01-01 00:00:00', 'active');

-- Table structure for table `roles`
CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'super_admin', 'System Owner - Full Access', '2024-01-01 00:00:00'),
(2, 'admin', 'Garage Owner/Manager', '2024-01-01 00:00:00'),
(3, 'employee', 'Service Technician/Mechanic', '2024-01-01 00:00:00'),
(4, 'accountant', 'Financial Manager', '2024-01-01 00:00:00'),
(5, 'customer', 'Vehicle Owner', '2024-01-01 00:00:00'),
(6, 'support_staff', 'Reception/Support Staff', '2024-01-01 00:00:00');

-- Table structure for table `users`
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `garage_id` int(11) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`id`, `garage_id`, `role_id`, `username`, `email`, `password`, `first_name`, `last_name`, `phone`, `profile_image`, `last_login`, `failed_attempts`, `locked_until`, `status`, `created_at`, `updated_at`) VALUES
(1, NULL, 1, 'superadmin', 'superadmin@garagesystem.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Owner', '+1-555-0001', NULL, NULL, 0, NULL, 'active', '2024-01-01 00:00:00', NULL),
(2, 1, 2, 'admin1', 'admin@autocarepro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'GarageOwner', '+1-555-0002', NULL, NULL, 0, NULL, 'active', '2024-01-01 00:00:00', NULL);

-- Table structure for table `permissions`
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `module` varchar(100) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_create` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `permissions` (`id`, `role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES
(1, 1, 'all', 1, 1, 1, 1),
(2, 2, 'users', 1, 1, 1, 0),
(3, 2, 'dashboard', 1, 0, 0, 0),
(4, 2, 'vehicles', 1, 1, 1, 1),
(5, 2, 'services', 1, 1, 1, 1),
(6, 2, 'inventory', 1, 1, 1, 1),
(7, 2, 'job_cards', 1, 1, 1, 1),
(8, 2, 'quotations', 1, 1, 1, 1),
(9, 2, 'invoices', 1, 1, 1, 1),
(10, 2, 'reports', 1, 0, 0, 0),
(11, 3, 'job_cards', 1, 0, 1, 0),
(12, 3, 'dashboard', 1, 0, 0, 0),
(13, 4, 'invoices', 1, 1, 1, 0),
(14, 4, 'payments', 1, 1, 1, 0),
(15, 4, 'reports', 1, 0, 0, 0),
(16, 4, 'dashboard', 1, 0, 0, 0),
(17, 5, 'vehicles', 1, 0, 0, 0),
(18, 5, 'job_history', 1, 0, 0, 0),
(19, 5, 'invoices', 1, 0, 0, 0),
(20, 6, 'gate_pass', 1, 1, 1, 0),
(21, 6, 'service_status', 1, 0, 1, 0);

-- Table structure for table `login_attempts`
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `customers`
CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `garage_id` int(11) NOT NULL,
  `customer_code` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `tax_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `customers` (`id`, `garage_id`, `customer_code`, `first_name`, `last_name`, `email`, `phone`, `address`, `company`, `tax_number`, `created_at`) VALUES
(1, 1, 'CUST-001', 'Michael', 'Johnson', 'michael@email.com', '+1-555-1001', '456 Oak Ave, City', 'Johnson Corp', 'TAX-CUST-001', '2024-01-01 00:00:00');

-- Table structure for table `vehicles`
CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `garage_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `registration_number` varchar(50) NOT NULL,
  `make` varchar(100) NOT NULL,
  `model` varchar(100) NOT NULL,
  `year` int(4) NOT NULL,
  `vin` varchar(50) DEFAULT NULL,
  `engine_number` varchar(50) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `fuel_type` enum('petrol','diesel','electric','hybrid','cng') DEFAULT 'petrol',
  `last_service_date` date DEFAULT NULL,
  `next_service_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `vehicles` (`id`, `garage_id`, `customer_id`, `registration_number`, `make`, `model`, `year`, `vin`, `engine_number`, `color`, `fuel_type`, `last_service_date`, `next_service_date`, `created_at`) VALUES
(1, 1, 1, 'ABC-1234', 'Toyota', 'Camry', 2022, '1HGCM82633A123456', 'ENG-001-2022', 'Silver', 'petrol', '2024-01-15', '2024-07-15', '2024-01-01 00:00:00');

-- Table structure for table `services`
CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `garage_id` int(11) NOT NULL,
  `service_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('routine','repair','diagnostic','bodywork','electrical','tire','battery','ac') DEFAULT 'routine',
  `duration_hours` decimal(4,2) DEFAULT 1.00,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `services` (`id`, `garage_id`, `service_code`, `name`, `description`, `category`, `duration_hours`, `price`, `created_at`) VALUES
(1, 1, 'SVC-001', 'Oil Change', 'Complete oil and filter change', 'routine', 0.50, 49.99, '2024-01-01 00:00:00'),
(2, 1, 'SVC-002', 'Brake Pad Replacement', 'Replace front/rear brake pads', 'repair', 2.00, 129.99, '2024-01-01 00:00:00'),
(3, 1, 'SVC-003', 'Tire Rotation', 'Rotate all four tires', 'tire', 0.75, 29.99, '2024-01-01 00:00:00');

-- Table structure for table `inventory`
CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `garage_id` int(11) NOT NULL,
  `part_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) DEFAULT 10,
  `unit_price` decimal(10,2) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `inventory` (`id`, `garage_id`, `part_code`, `name`, `description`, `category`, `quantity`, `reorder_level`, `unit_price`, `selling_price`, `supplier`, `created_at`) VALUES
(1, 1, 'PART-001', 'Engine Oil 5W-30', 'Synthetic engine oil 5W-30 grade', 'Lubricants', 50, 10, 25.00, 39.99, 'OilCo Inc.', '2024-01-01 00:00:00'),
(2, 1, 'PART-002', 'Oil Filter', 'Standard oil filter', 'Filters', 30, 5, 8.50, 14.99, 'FilterTech', '2024-01-01 00:00:00'),
(3, 1, 'PART-003', 'Brake Pads Set', 'Front brake pads set', 'Brakes', 20, 5, 45.00, 79.99, 'BrakeMaster', '2024-01-01 00:00:00');

-- Table structure for table `job_cards`
CREATE TABLE `job_cards` (
  `id` int(11) NOT NULL,
  `garage_id` int(11) NOT NULL,
  `job_number` varchar(50) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','waiting_parts','completed','delivered','cancelled') DEFAULT 'pending',
  `problem_description` text NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `estimated_hours` decimal(4,2) DEFAULT NULL,
  `actual_hours` decimal(4,2) DEFAULT NULL,
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `job_cards` (`id`, `garage_id`, `job_number`, `vehicle_id`, `customer_id`, `assigned_to`, `status`, `problem_description`, `diagnosis`, `estimated_hours`, `actual_hours`, `estimated_cost`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'JOB-2024-001', 1, 1, 2, 'completed', 'Regular maintenance service', 'Oil change and tire rotation required', 1.50, 1.25, 89.98, 2, '2024-01-15 09:00:00', '2024-01-15 12:30:00');

-- Table structure for table `job_services`
CREATE TABLE `job_services` (
  `id` int(11) NOT NULL,
  `job_card_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `job_services` (`id`, `job_card_id`, `service_id`, `quantity`, `price`, `notes`, `completed_at`) VALUES
(1, 1, 1, 1, 49.99, 'Used synthetic oil', '2024-01-15 11:30:00'),
(2, 1, 3, 1, 29.99, 'Standard rotation pattern', '2024-01-15 11:45:00');

-- Table structure for table `job_parts`
CREATE TABLE `job_parts` (
  `id` int(11) NOT NULL,
  `job_card_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `job_parts` (`id`, `job_card_id`, `inventory_id`, `quantity`, `price`, `notes`) VALUES
(1, 1, 1, 1, 39.99, '5W-30 synthetic'),
(2, 1, 2, 1, 14.99, 'Standard filter');

-- Table structure for table `quotations`
CREATE TABLE `quotations` (
  `id` int(11) NOT NULL,
  `garage_id` int(11) NOT NULL,
  `quotation_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','accepted','rejected','expired') DEFAULT 'pending',
  `valid_until` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `invoices`
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `garage_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `job_card_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `invoice_items`
CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `invoices` (`id`, `garage_id`, `invoice_number`, `job_card_id`, `customer_id`, `vehicle_id`, `subtotal`, `tax_rate`, `tax_amount`, `discount`, `total_amount`, `status`, `due_date`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 'INV-2024-001', 1, 1, 1, 134.96, 10.00, 13.50, 0.00, 148.46, 'paid', '2024-02-14', 'Thank you for your business!', 2, '2024-01-15 12:00:00');

-- Table structure for table `payments`
CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `garage_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `payment_number` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','credit_card','debit_card','bank_transfer','check','online') DEFAULT 'cash',
  `reference` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `payments` (`id`, `garage_id`, `invoice_id`, `payment_number`, `amount`, `payment_method`, `reference`, `notes`, `received_by`, `created_at`) VALUES
(1, 1, 1, 'PAY-2024-001', 148.46, 'credit_card', 'CC-123456', 'Payment received', 2, '2024-01-15 14:30:00');

-- Table structure for table `gate_pass`
CREATE TABLE `gate_pass` (
  `id` int(11) NOT NULL,
  `garage_id` int(11) NOT NULL,
  `pass_number` varchar(50) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `purpose` enum('service','delivery','pickup','inspection') DEFAULT 'service',
  `entry_time` datetime NOT NULL,
  `exit_time` datetime DEFAULT NULL,
  `security_notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `email_templates`
CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `variables` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `email_templates` (`id`, `name`, `subject`, `content`, `variables`, `created_at`) VALUES
(1, 'service_reminder', 'Upcoming Service Reminder', '<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"UTF-8\">\n    <title>Service Reminder</title>\n</head>\n<body>\n    <div style=\"font-family: \'Montserrat\', \'Poppins\', Arial, sans-serif; max-width: 600px; margin: 0 auto;\">\n        <div style=\"background-color: #00A8CE; padding: 20px; text-align: center;\">\n            <img src=\"[LOGO_URL]\" alt=\"Company Logo\" style=\"max-height: 60px;\">\n        </div>\n        <div style=\"padding: 30px; background-color: #f9f9f9;\">\n            <h2 style=\"color: #0E2033;\">Service Reminder</h2>\n            <p>Dear [CUSTOMER_NAME],</p>\n            <p>This is a reminder that your vehicle [VEHICLE_DETAILS] is due for service on [SERVICE_DATE].</p>\n            <div style=\"background-color: white; padding: 20px; border-left: 4px solid #FFA629; margin: 20px 0;\">\n                <p><strong>Vehicle:</strong> [VEHICLE_MAKE] [VEHICLE_MODEL] ([REGISTRATION])</p>\n                <p><strong>Next Service Due:</strong> [SERVICE_DATE]</p>\n                <p><strong>Recommended Service:</strong> [SERVICE_TYPE]</p>\n            </div>\n            <p>Please contact us at [GARAGE_PHONE] or email [GARAGE_EMAIL] to schedule your appointment.</p>\n            <p>Best regards,<br>The [GARAGE_NAME] Team</p>\n        </div>\n        <div style=\"background-color: #0E2033; color: white; padding: 20px; text-align: center;\">\n            <p>[GARAGE_NAME] | [GARAGE_ADDRESS] | [GARAGE_PHONE]</p>\n        </div>\n    </div>\n</body>\n</html>', 'LOGO_URL,CUSTOMER_NAME,VEHICLE_DETAILS,SERVICE_DATE,VEHICLE_MAKE,VEHICLE_MODEL,REGISTRATION,SERVICE_TYPE,GARAGE_PHONE,GARAGE_EMAIL,GARAGE_NAME,GARAGE_ADDRESS', '2024-01-01 00:00:00');

-- Table structure for table `settings`
CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `garage_id` int(11) DEFAULT NULL,
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `type` enum('string','number','boolean','json') DEFAULT 'string',
  `category` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`id`, `garage_id`, `key`, `value`, `type`, `category`, `updated_at`) VALUES
(1, NULL, 'system_name', 'Garage Master', 'string', 'general', NULL),
(2, NULL, 'default_tax_rate', '10', 'number', 'financial', NULL),
(3, NULL, 'currency', 'USD', 'string', 'financial', NULL),
(4, NULL, 'currency_symbol', '$', 'string', 'financial', NULL),
(5, NULL, 'service_reminder_days', '7', 'number', 'notifications', NULL),
(6, 1, 'business_hours', '{\"open\":\"08:00\",\"close\":\"18:00\"}', 'json', 'general', NULL);

-- Indexes for dumped tables
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`,`garage_id`),
  ADD KEY `garage_id` (`garage_id`);

ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `garages`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `gate_pass`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pass_number` (`pass_number`,`garage_id`),
  ADD KEY `garage_id` (`garage_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `customer_id` (`customer_id`);

ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `part_code` (`part_code`,`garage_id`),
  ADD KEY `garage_id` (`garage_id`);

ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`,`garage_id`),
  ADD KEY `garage_id` (`garage_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `job_card_id` (`job_card_id`);

ALTER TABLE `job_cards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_number` (`job_number`,`garage_id`),
  ADD KEY `garage_id` (`garage_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `assigned_to` (`assigned_to`);

ALTER TABLE `job_parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_card_id` (`job_card_id`),
  ADD KEY `inventory_id` (`inventory_id`);

ALTER TABLE `job_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_card_id` (`job_card_id`),
  ADD KEY `service_id` (`service_id`);

ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`),
  ADD KEY `ip_address` (`ip_address`);

ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_number` (`payment_number`,`garage_id`),
  ADD KEY `garage_id` (`garage_id`),
  ADD KEY `invoice_id` (`invoice_id`);

ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role_id` (`role_id`);

ALTER TABLE `quotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quotation_number` (`quotation_number`,`garage_id`),
  ADD KEY `garage_id` (`garage_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `vehicle_id` (`vehicle_id`);

ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_code` (`service_code`,`garage_id`),
  ADD KEY `garage_id` (`garage_id`);

ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_garage` (`key`,`garage_id`),
  ADD KEY `garage_id` (`garage_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `garage_id` (`garage_id`),
  ADD KEY `role_id` (`role_id`);

ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_number` (`registration_number`,`garage_id`),
  ADD KEY `garage_id` (`garage_id`),
  ADD KEY `customer_id` (`customer_id`);

-- AUTO_INCREMENT for dumped tables
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `email_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `garages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `gate_pass`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `job_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `job_parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `job_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

ALTER TABLE `quotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

-- Constraints for dumped tables
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`garage_id`) REFERENCES `garages` (`id`) ON DELETE CASCADE;

ALTER TABLE `gate_pass`
  ADD CONSTRAINT `gate_pass_ibfk_1` FOREIGN KEY (`garage_id`) REFERENCES `garages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gate_pass_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gate_pass_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`garage_id`) REFERENCES `garages` (`id`) ON DELETE CASCADE;

ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`garage_id`) REFERENCES `garages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoices_ibfk_4` FOREIGN KEY (`job_card_id`) REFERENCES `job_cards` (`id`) ON DELETE SET NULL;

ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_items_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE SET NULL;

ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `job_cards`
  ADD CONSTRAINT `job_cards_ibfk_1` FOREIGN KEY (`garage_id`) REFERENCES `garages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_cards_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_cards_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_cards_ibfk_4` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `job_parts`
  ADD CONSTRAINT `job_parts_ibfk_1` FOREIGN KEY (`job_card_id`) REFERENCES `job_cards` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_parts_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE;

ALTER TABLE `job_services`
  ADD CONSTRAINT `job_services_ibfk_1` FOREIGN KEY (`job_card_id`) REFERENCES `job_cards` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_services_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`garage_id`) REFERENCES `garages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

ALTER TABLE `quotations`
  ADD CONSTRAINT `quotations_ibfk_1` FOREIGN KEY (`garage_id`) REFERENCES `garages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quotations_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quotations_ibfk_3` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`garage_id`) REFERENCES `garages` (`id`) ON DELETE CASCADE;

ALTER TABLE `settings`
  ADD CONSTRAINT `settings_ibfk_1` FOREIGN KEY (`garage_id`) REFERENCES `garages` (`id`) ON DELETE CASCADE;

ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`garage_id`) REFERENCES `garages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`garage_id`) REFERENCES `garages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vehicles_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;
COMMIT;