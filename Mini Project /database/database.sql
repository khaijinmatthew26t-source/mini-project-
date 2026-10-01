CREATE DATABASE IF NOT EXISTS enrollment_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE enrollment_system;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'tutor', 'student') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    schedule VARCHAR(100) NOT NULL,
    tutor_id INT UNSIGNED NOT NULL,
    max_seats INT UNSIGNED NOT NULL DEFAULT 20,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_courses_tutor
        FOREIGN KEY (tutor_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_courses_tutor (tutor_id)
) ENGINE=InnoDB;

CREATE TABLE enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_enrollment UNIQUE (student_id, course_id),
    CONSTRAINT fk_enrollments_student
        FOREIGN KEY (student_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_enrollments_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_enrollments_course (course_id)
) ENGINE=InnoDB;

CREATE TABLE attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT UNSIGNED NOT NULL,
    session_date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'Late') NOT NULL,
    CONSTRAINT unique_attendance UNIQUE (enrollment_id, session_date),
    CONSTRAINT fk_attendance_enrollment
        FOREIGN KEY (enrollment_id) REFERENCES enrollments(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO users (name, email, password, role) VALUES
('Alice Admin', 'admin@test.com', 'forward2026', 'admin'),
('Tom Tutor', 'tutor@test.com', 'forward2026', 'tutor'),
('Sam Student', 'student@test.com', 'forward2026', 'student'),
('Emma English', 'emma@test.com', 'forward2026', 'tutor'),
('Nora Bahasa', 'nora@test.com', 'forward2026', 'tutor');

INSERT INTO courses (title, subject, schedule, tutor_id, max_seats) VALUES
('Intro to Algebra', 'Mathematics', 'Mon & Wed, 4-6pm', 2, 2),
('Form 1 Mathematics', 'Mathematics', 'Tue & Thu, 4-6pm', 2, 20),
('Basic English Grammar', 'English', 'Mon & Wed, 6-8pm', 4, 20),
('English Speaking & Conversation', 'English', 'Sat, 10am-12pm', 4, 15),
('Bahasa Melayu SPM', 'Bahasa Melayu', 'Tue & Thu, 6-8pm', 5, 20),
('Bahasa Melayu Penulisan SPM', 'Bahasa Melayu', 'Sat, 2-4pm', 5, 15);

INSERT INTO enrollments (student_id, course_id) VALUES (3, 1);
