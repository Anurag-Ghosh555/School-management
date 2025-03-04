CREATE DATABASE SchoolDB;
USE SchoolDB;

CREATE TABLE Class (
    class_id INT PRIMARY KEY AUTO_INCREMENT,
    class_name VARCHAR(50),
    section VARCHAR(10),
    year INT
);
INSERT INTO Class (class_name, section, year) VALUES 
('10th', 'A', 2025),
('10th', 'B', 2025),
('12th', 'A', 2025);

CREATE TABLE Student (
    student_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    date_of_birth DATE,
    gender ENUM('Male', 'Female', 'Other'),
    contact_number VARCHAR(15),
    address TEXT,
    email VARCHAR(100),
    enrollment_date DATE,
    class_id INT,
    FOREIGN KEY (class_id) REFERENCES Class(class_id)
);
INSERT INTO Student (first_name, last_name, date_of_birth, gender, contact_number, address, email, enrollment_date, class_id) VALUES 
('Amit', 'Sharma', '2008-05-10', 'Male', '9876543210', 'Delhi, India', 'amit.sharma@gmail.com', '2023-06-01', 1),
('Priya', 'Verma', '2007-11-15', 'Female', '9876543211', 'Mumbai, India', 'priya.verma@gmail.com', '2023-06-01', 2),
('Rahul', 'Singh', '2006-09-20', 'Male', '9876543212', 'Kolkata, India', 'rahul.singh@gmail.com', '2023-06-01', 3);

CREATE TABLE Teacher (
    teacher_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    date_of_birth DATE,
    gender ENUM('Male', 'Female', 'Other'),
    contact_number VARCHAR(15),
    email VARCHAR(100),
    hire_date DATE
);
INSERT INTO Teacher (first_name, last_name, date_of_birth, gender, contact_number, email, hire_date) VALUES 
('Sunita', 'Mishra', '1980-07-15', 'Female', '9876543213', 'sunita.mishra@gmail.com', '2010-08-15'),
('Rajesh', 'Gupta', '1975-04-22', 'Male', '9876543214', 'rajesh.gupta@gmail.com', '2008-03-10');

CREATE TABLE Extracurricular_Activity (
    activity_id INT PRIMARY KEY AUTO_INCREMENT,
    activity_name VARCHAR(100),
    description TEXT
);
INSERT INTO Extracurricular_Activity (activity_name, description) VALUES 
('Football', 'Inter-school football competition'),
('Dance', 'Annual dance fest');

CREATE TABLE Student_Extracurricular (
    student_id INT,
    activity_id INT,
    PRIMARY KEY (student_id, activity_id),
    FOREIGN KEY (student_id) REFERENCES Student(student_id),
    FOREIGN KEY (activity_id) REFERENCES Extracurricular_Activity(activity_id)
);
INSERT INTO Student_Extracurricular (student_id, activity_id) VALUES 
(1, 1),
(2, 2);

CREATE TABLE Event (
    event_id INT PRIMARY KEY AUTO_INCREMENT,
    event_name VARCHAR(100),
    event_date DATE,
    description TEXT
);
INSERT INTO Event (event_name, event_date, description) VALUES 
('Science Fair', '2025-02-20', 'Annual school science exhibition'),
('Sports Day', '2025-03-10', 'Inter-house sports competition');

CREATE TABLE Student_Event (
    student_id INT,
    event_id INT,
    PRIMARY KEY (student_id, event_id),
    FOREIGN KEY (student_id) REFERENCES Student(student_id),
    FOREIGN KEY (event_id) REFERENCES Event(event_id)
);
INSERT INTO Student_Event (student_id, event_id) VALUES 
(1, 1),
(2, 2);

CREATE TABLE Transport (
    transport_id INT PRIMARY KEY AUTO_INCREMENT,
    bus_number VARCHAR(10),
    route VARCHAR(255),
    capacity INT
);
INSERT INTO Transport (bus_number, route, capacity) VALUES 
('B001', 'Route 1: City Center to School', 40),
('B002', 'Route 2: East Zone to School', 50);

CREATE TABLE Student_Transport (
    student_id INT,
    transport_id INT,
    PRIMARY KEY (student_id, transport_id),
    FOREIGN KEY (student_id) REFERENCES Student(student_id),
    FOREIGN KEY (transport_id) REFERENCES Transport(transport_id)
);
INSERT INTO Student_Transport (student_id, transport_id) VALUES 
(1, 1),
(3, 2);

CREATE TABLE Complaint (
    complaint_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    complaint_text TEXT,
    complaint_date DATE,
    status ENUM('Pending', 'Resolved', 'Closed'),
    FOREIGN KEY (student_id) REFERENCES Student(student_id)
);
INSERT INTO Complaint (student_id, complaint_text, complaint_date, status) VALUES 
(1, 'Issue with school bus timing', '2025-01-15', 'Pending'),
(2, 'Classroom projector not working', '2025-01-18', 'Resolved');

CREATE TABLE Feedback (
    feedback_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    feedback_text TEXT,
    feedback_date DATE,
    type ENUM('Teacher', 'Event', 'General'),
    FOREIGN KEY (student_id) REFERENCES Student(student_id)
);
INSERT INTO Feedback (student_id, feedback_text, feedback_date, type) VALUES 
(1, 'Great teaching by Mr. Rajesh!', '2025-02-01', 'Teacher'),
(3, 'Loved the sports day event!', '2025-02-05', 'Event');

CREATE TABLE Holiday (
    holiday_id INT PRIMARY KEY AUTO_INCREMENT,
    holiday_name VARCHAR(100),
    holiday_date DATE,
    description TEXT
);
INSERT INTO Holiday (holiday_name, holiday_date, description) VALUES 
('Republic Day', '2025-01-26', 'National holiday in India'),
('Holi', '2025-03-14', 'Festival of colors');