-- ============================================================
-- PHARMACY MANAGEMENT SYSTEM - DATABASE SETUP
-- Developed by: Md Shamim Hossain Ridoy | Developer Portfolio
-- ============================================================

CREATE DATABASE IF NOT EXISTS pharmacy_db CHARACTER SET utf8 COLLATE utf8_general_ci;
USE pharmacy_db;

-- Users / Staff Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin','pharmacist','cashier') DEFAULT 'cashier',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Suppliers
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Medicines
CREATE TABLE IF NOT EXISTS medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    generic_name VARCHAR(150),
    category_id INT,
    supplier_id INT,
    unit VARCHAR(50) DEFAULT 'pcs',
    purchase_price DECIMAL(10,2) DEFAULT 0.00,
    sale_price DECIMAL(10,2) DEFAULT 0.00,
    stock_qty INT DEFAULT 0,
    min_stock INT DEFAULT 10,
    batch_number VARCHAR(50),
    manufacture_date DATE,
    expiry_date DATE,
    description TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

-- Customers
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sales
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(20) NOT NULL UNIQUE,
    customer_id INT,
    user_id INT,
    subtotal DECIMAL(10,2) DEFAULT 0.00,
    discount DECIMAL(10,2) DEFAULT 0.00,
    tax DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) DEFAULT 0.00,
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    change_amount DECIMAL(10,2) DEFAULT 0.00,
    payment_method ENUM('cash','card','mobile') DEFAULT 'cash',
    notes TEXT,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Sale Items
CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
);

-- Purchases
CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(20) NOT NULL UNIQUE,
    supplier_id INT,
    user_id INT,
    total DECIMAL(10,2) DEFAULT 0.00,
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('received','pending','partial') DEFAULT 'received',
    notes TEXT,
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Purchase Items
CREATE TABLE IF NOT EXISTS purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default Users (password: password)
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@pharmacy.com', 'admin'),
('pharmacist1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Rahman Ali', 'rahman@pharmacy.com', 'pharmacist'),
('cashier1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Fatima Begum', 'fatima@pharmacy.com', 'cashier');

-- Categories
INSERT INTO categories (name, description) VALUES
('Antibiotics', 'Medicines used to treat bacterial infections'),
('Analgesics', 'Pain relief medications'),
('Antacids', 'Medicines for acidity and heartburn'),
('Vitamins', 'Vitamin and mineral supplements'),
('Antihistamines', 'Allergy relief medications'),
('Cardiovascular', 'Heart and blood pressure medicines'),
('Diabetes', 'Medicines for diabetes management'),
('Antiseptics', 'Wound cleaning and infection prevention');

-- Suppliers
INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES
('Square Pharmaceuticals', 'Karim Uddin', '01700000001', 'karim@square.com', 'Dhaka, Bangladesh'),
('Beximco Pharma', 'Rina Khatun', '01700000002', 'rina@beximco.com', 'Gazipur, Bangladesh'),
('ACI Limited', 'Shafiq Islam', '01700000003', 'shafiq@aci.com', 'Narayanganj, Bangladesh'),
('Incepta Pharma', 'Nadia Begum', '01700000004', 'nadia@incepta.com', 'Savar, Bangladesh');

-- Sample Medicines
INSERT INTO medicines (name, generic_name, category_id, supplier_id, unit, purchase_price, sale_price, stock_qty, min_stock, expiry_date) VALUES
('Napa 500mg', 'Paracetamol', 2, 1, 'tablet', 0.50, 1.00, 500, 50, '2026-12-31'),
('Amoxil 500mg', 'Amoxicillin', 1, 2, 'capsule', 5.00, 8.00, 200, 30, '2026-06-30'),
('Ranitidine 150mg', 'Ranitidine', 3, 3, 'tablet', 1.50, 3.00, 300, 40, '2025-12-31'),
('Vitamin C 500mg', 'Ascorbic Acid', 4, 1, 'tablet', 2.00, 4.00, 400, 50, '2027-03-31'),
('Cetirizine 10mg', 'Cetirizine HCl', 5, 4, 'tablet', 2.50, 5.00, 150, 25, '2026-09-30'),
('Metformin 500mg', 'Metformin HCl', 7, 2, 'tablet', 3.00, 6.00, 250, 30, '2026-12-31'),
('Amlodipine 5mg', 'Amlodipine', 6, 3, 'tablet', 4.00, 8.00, 180, 20, '2026-08-31'),
('Dettol 100ml', 'Chloroxylenol', 8, 1, 'bottle', 25.00, 45.00, 80, 15, '2027-01-31'),
('Flagyl 400mg', 'Metronidazole', 1, 4, 'tablet', 3.50, 7.00, 220, 30, '2026-05-31'),
('Vitamin B Complex', 'B-Vitamins', 4, 2, 'tablet', 1.50, 3.50, 350, 50, '2027-06-30');

-- Sample Customers
INSERT INTO customers (name, phone, address) VALUES
('Walk-in Customer', '0000000000', 'N/A'),
('Md. Abdullah', '01711111111', 'Mirpur, Dhaka'),
('Fatema Akter', '01811111111', 'Gulshan, Dhaka'),
('Hasan Ali', '01911111111', 'Mohammadpur, Dhaka');

-- ============================================================
-- MIGRATION: Returns Feature
-- ============================================================

-- Returns Table
CREATE TABLE IF NOT EXISTS returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(20) NOT NULL UNIQUE,
    sale_id INT NOT NULL,
    user_id INT,
    return_type ENUM('refund','exchange') DEFAULT 'refund',
    reason TEXT,
    refund_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('completed','pending','cancelled') DEFAULT 'completed',
    return_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- MIGRATION: Supplier Returns Feature
-- ============================================================

-- Supplier Returns Table (Return to Company/Supplier)
CREATE TABLE IF NOT EXISTS supplier_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(20) NOT NULL UNIQUE,
    supplier_id INT,
    medicine_id INT NOT NULL,
    user_id INT,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    reason TEXT,
    status ENUM('completed','pending') DEFAULT 'completed',
    return_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Return Items Table
CREATE TABLE IF NOT EXISTS return_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_id INT NOT NULL,
    sale_item_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
);
