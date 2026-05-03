CREATE DATABASE IF NOT EXISTS recru CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE recru;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('jobseeker', 'company', 'admin', 'superadmin') NOT NULL DEFAULT 'jobseeker',
  full_name VARCHAR(160) NULL,
  company_name VARCHAR(180) NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  phone VARCHAR(60) NULL,
  password_hash VARCHAR(255) NOT NULL,
  skills TEXT NULL,
  industry VARCHAR(160) NULL,
  location VARCHAR(180) NULL,
  cv_file VARCHAR(255) NULL,
  cv_text MEDIUMTEXT NULL,
  cv_ai_skills TEXT NULL,
  cv_ai_years INT NULL,
  cv_ai_summary TEXT NULL,
  cv_ai_updated_at TIMESTAMP NULL DEFAULT NULL,
  logo_file VARCHAR(255) NULL,
  profile_photo VARCHAR(255) NULL,
  status ENUM('active', 'blocked') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE users MODIFY role ENUM('jobseeker', 'company', 'admin', 'superadmin') NOT NULL DEFAULT 'jobseeker';
ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_text MEDIUMTEXT NULL AFTER cv_file;
ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_ai_skills TEXT NULL AFTER cv_text;
ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_ai_years INT NULL AFTER cv_ai_skills;
ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_ai_summary TEXT NULL AFTER cv_ai_years;
ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_ai_updated_at TIMESTAMP NULL DEFAULT NULL AFTER cv_ai_summary;
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) NULL AFTER logo_file;

CREATE TABLE IF NOT EXISTS companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  name VARCHAR(180) NOT NULL,
  industry VARCHAR(160) NOT NULL,
  location VARCHAR(180) NOT NULL DEFAULT 'Remote',
  logo_file VARCHAR(255) NULL,
  description TEXT NULL,
  verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'verified',
  verified_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  recruiter_id INT NULL,
  title VARCHAR(180) NOT NULL,
  location VARCHAR(180) NOT NULL,
  salary VARCHAR(120) NOT NULL,
  type ENUM('Full-time', 'Part-time', 'Remote', 'Hybrid', 'Contract') NOT NULL DEFAULT 'Full-time',
  description TEXT NOT NULL,
  requirements TEXT NULL,
  status ENUM('pending', 'active', 'closed') NOT NULL DEFAULT 'active',
  expires_at DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  FOREIGN KEY (recruiter_id) REFERENCES users(id) ON DELETE SET NULL
);

ALTER TABLE jobs ADD COLUMN IF NOT EXISTS recruiter_id INT NULL AFTER company_id;
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS expires_at DATE NULL AFTER status;

CREATE TABLE IF NOT EXISTS job_tags (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  tag VARCHAR(80) NOT NULL,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  user_id INT NULL,
  applicant_name VARCHAR(160) NOT NULL,
  applicant_email VARCHAR(180) NOT NULL,
  applicant_phone VARCHAR(60) NULL,
  role VARCHAR(180) NOT NULL,
  cover_note TEXT NULL,
  cv_file VARCHAR(255) NULL,
  cv_ai_skills TEXT NULL,
  cv_ai_years INT NULL,
  cv_ai_summary TEXT NULL,
  cv_text MEDIUMTEXT NULL,
  status ENUM('New', 'Reviewed', 'Shortlisted', 'Interview', 'Accepted', 'Rejected') NOT NULL DEFAULT 'New',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

ALTER TABLE applications ADD COLUMN IF NOT EXISTS cv_file VARCHAR(255) NULL AFTER cover_note;
ALTER TABLE applications ADD COLUMN IF NOT EXISTS cv_ai_skills TEXT NULL AFTER cv_file;
ALTER TABLE applications ADD COLUMN IF NOT EXISTS cv_ai_years INT NULL AFTER cv_ai_skills;
ALTER TABLE applications ADD COLUMN IF NOT EXISTS cv_ai_summary TEXT NULL AFTER cv_ai_years;
ALTER TABLE applications ADD COLUMN IF NOT EXISTS cv_text MEDIUMTEXT NULL AFTER cv_ai_summary;
ALTER TABLE applications MODIFY status ENUM('New', 'Reviewed', 'Shortlisted', 'Interview', 'Accepted', 'Rejected') NOT NULL DEFAULT 'New';

CREATE TABLE IF NOT EXISTS saved_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  job_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY saved_user_job (user_id, job_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  body VARCHAR(255) NOT NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS application_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  title VARCHAR(180) NOT NULL,
  note VARCHAR(255) NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS interviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  scheduled_at DATETIME NOT NULL,
  location VARCHAR(180) NULL,
  note VARCHAR(255) NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS service_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  sender_id INT NULL,
  sender_role ENUM('jobseeker', 'admin', 'superadmin') NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS blog_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  author_id INT NULL,
  title VARCHAR(180) NOT NULL,
  excerpt VARCHAR(255) NULL,
  content MEDIUMTEXT NOT NULL,
  cover_image VARCHAR(255) NULL,
  category VARCHAR(80) NULL,
  status ENUM('draft', 'published') NOT NULL DEFAULT 'published',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Demo/sample seed data removed for production safety.
