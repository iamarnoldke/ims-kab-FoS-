-- ============================================================
-- INVENTORY MANAGEMENT SYSTEM (IMS)
-- Faculty of Science, Kabale University
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS ims_db;
USE ims_db;

-- ------------------------------------------------------------
-- DEPARTMENTS
-- ------------------------------------------------------------
CREATE TABLE departments (
    dept_id     INT AUTO_INCREMENT PRIMARY KEY,
    dept_name   VARCHAR(100) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- USERS
-- ------------------------------------------------------------
CREATE TABLE users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    profile_picture VARCHAR(255) NULL,
    role        ENUM('admin','store_keeper','hod','staff','faculty_admin') NOT NULL,
    dept_id     INT,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id)
);

-- ------------------------------------------------------------
-- STOCK ITEMS
-- ------------------------------------------------------------
CREATE TABLE stock_items (
    stock_id        INT AUTO_INCREMENT PRIMARY KEY,
    item_name       VARCHAR(150) NOT NULL,
    category        VARCHAR(100) NOT NULL,
    unit_of_measure VARCHAR(50)  NOT NULL,
    qty_on_hand     INT          NOT NULL DEFAULT 0,
    reorder_point   INT          NOT NULL DEFAULT 5,
    location        VARCHAR(100),
    is_active       TINYINT(1)   DEFAULT 1,
    created_by      INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- ------------------------------------------------------------
-- STOCK REQUESTS
-- ------------------------------------------------------------
CREATE TABLE stock_requests (
    request_id  INT AUTO_INCREMENT PRIMARY KEY,
    ref_number  VARCHAR(20)  NOT NULL UNIQUE,
    requester_id INT         NOT NULL,
    dept_id     INT          NOT NULL,
    purpose     TEXT,
    status      ENUM('pending','approved','rejected','issued','cancelled') DEFAULT 'pending',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(user_id),
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id)
);

-- ------------------------------------------------------------
-- STOCK REQUEST ITEMS
-- ------------------------------------------------------------
CREATE TABLE stock_request_items (
    req_item_id     INT AUTO_INCREMENT PRIMARY KEY,
    request_id      INT NOT NULL,
    stock_id        INT NOT NULL,
    qty_requested   INT NOT NULL,
    FOREIGN KEY (request_id) REFERENCES stock_requests(request_id),
    FOREIGN KEY (stock_id) REFERENCES stock_items(stock_id)
);

-- ------------------------------------------------------------
-- APPROVALS
-- ------------------------------------------------------------
CREATE TABLE approvals (
    approval_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id  INT NOT NULL,
    approver_id INT NOT NULL,
    decision    ENUM('approved','rejected','returned') NOT NULL,
    comments    TEXT,
    actioned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES stock_requests(request_id),
    FOREIGN KEY (approver_id) REFERENCES users(user_id)
);

-- ------------------------------------------------------------
-- STOCK ISSUANCES
-- ------------------------------------------------------------
CREATE TABLE stock_issuances (
    issuance_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id  INT NOT NULL,
    issued_by   INT NOT NULL,
    issued_date DATE NOT NULL,
    notes       TEXT,
    FOREIGN KEY (request_id) REFERENCES stock_requests(request_id),
    FOREIGN KEY (issued_by) REFERENCES users(user_id)
);

-- ------------------------------------------------------------
-- STOCK ISSUANCE ITEMS
-- ------------------------------------------------------------
CREATE TABLE stock_issuance_items (
    iss_item_id INT AUTO_INCREMENT PRIMARY KEY,
    issuance_id INT NOT NULL,
    stock_id    INT NOT NULL,
    qty_issued  INT NOT NULL,
    FOREIGN KEY (issuance_id) REFERENCES stock_issuances(issuance_id),
    FOREIGN KEY (stock_id) REFERENCES stock_items(stock_id)
);

-- ------------------------------------------------------------
-- GOODS RECEIVED
-- ------------------------------------------------------------
CREATE TABLE goods_received (
    gr_id           INT AUTO_INCREMENT PRIMARY KEY,
    grn_number      VARCHAR(20) NOT NULL UNIQUE,
    received_by     INT NOT NULL,
    received_date   DATE NOT NULL,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (received_by) REFERENCES users(user_id)
);

-- ------------------------------------------------------------
-- GOODS RECEIVED ITEMS
-- ------------------------------------------------------------
CREATE TABLE goods_received_items (
    gr_item_id  INT AUTO_INCREMENT PRIMARY KEY,
    gr_id       INT NOT NULL,
    stock_id    INT NOT NULL,
    qty_received INT NOT NULL,
    FOREIGN KEY (gr_id) REFERENCES goods_received(gr_id),
    FOREIGN KEY (stock_id) REFERENCES stock_items(stock_id)
);

-- ------------------------------------------------------------
-- STOCK ADJUSTMENTS
-- ------------------------------------------------------------
CREATE TABLE stock_adjustments (
    adj_id      INT AUTO_INCREMENT PRIMARY KEY,
    stock_id    INT NOT NULL,
    adjusted_by INT NOT NULL,
    qty_change  INT NOT NULL,
    reason      TEXT NOT NULL,
    adj_date    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_id) REFERENCES stock_items(stock_id),
    FOREIGN KEY (adjusted_by) REFERENCES users(user_id)
);

-- ------------------------------------------------------------
-- AUDIT LOG
-- ------------------------------------------------------------
CREATE TABLE audit_log (
    log_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    action_type VARCHAR(50) NOT NULL,
    details     TEXT,
    ip_address  VARCHAR(45),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Departments
INSERT INTO departments (dept_name) VALUES
('Biology'),('Chemistry'),('Physics'),('Mathematics'),('Faculty Administration');

-- Admin user (password: admin123)
INSERT INTO users (full_name, email, username, password, role, dept_id) VALUES
('System Administrator','admin@kab.ac.ug','admin',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin',5),
('Store Keeper','storekeeper@kab.ac.ug','storekeeper',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','store_keeper',5),
('Dr. James Mugisha','hod.chemistry@kab.ac.ug','hod_chem',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','hod',2),
('Lab Technician','labtech@kab.ac.ug','labtech',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','staff',2),
('Faculty Administrator','facadmin@kab.ac.ug','facadmin',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','faculty_admin',5);

-- Sample stock items
INSERT INTO stock_items (item_name, category, unit_of_measure, qty_on_hand, reorder_point, location, created_by) VALUES
('Hydrochloric Acid (HCl)','Chemicals','Litres',20,5,'Shelf A1',1),
('Sodium Hydroxide (NaOH)','Chemicals','Kg',15,3,'Shelf A2',1),
('Beakers (250ml)','Glassware','Pieces',40,10,'Cabinet B1',1),
('Bunsen Burners','Equipment','Pieces',10,3,'Cabinet C1',1),
('Filter Paper','Consumables','Packs',8,3,'Shelf D1',1),
('Ethanol 95%','Chemicals','Litres',12,4,'Shelf A3',1),
('Microscope Slides','Consumables','Box',5,2,'Cabinet B2',1),
('Pipettes (10ml)','Glassware','Pieces',30,8,'Cabinet B3',1),
('A4 Printing Paper','Stationery','Reams',25,5,'Store Room',1),
('Marker Pens (Whiteboard)','Stationery','Box',6,2,'Store Room',1);
