# Student Clearance System

## Overview

The Student Clearance System is a web-based application developed to automate and simplify the student clearance process within a university environment. The system enables students to initiate clearance requests, track clearance progress, and generate clearance reports, while departmental heads and administrators can manage and approve clearance requests efficiently.

## Features

### Student Module

* Student Registration
* Student Login
* View Dashboard
* Initiate Clearance Request
* Track Clearance Progress
* View Profile
* Generate Clearance Report
* Logout

### Department Module

* Department Login
* View Pending Clearance Requests
* Approve Student Clearance
* Reject Student Clearance
* Add Comments and Feedback
* Logout

### Administrator Module

* Administrator Login
* View All Students
* View Clearance Records
* Approve Final Clearance
* Reject Clearance Requests
* Generate Reports
* Manage System Data
* Logout

## Technologies Used

### Frontend

* HTML5
* CSS3
* JavaScript

### Backend

* PHP

### Database

* MySQL

### Development Environment

* XAMPP
* Visual Studio Code

## System Requirements

### Software Requirements

* Windows 10/11
* XAMPP
* PHP 8+
* MySQL
* Visual Studio Code
* Web Browser (Google Chrome, Microsoft Edge, Firefox)

### Hardware Requirements

* Laptop/Desktop Computer
* Minimum 4 GB RAM
* Minimum 10 GB Free Storage

## Installation Guide

### Step 1: Clone the Repository

```bash
git clone https://github.com/Felloh25/student-clearance-system.git
```

### Step 2: Move Project Folder

Copy the project folder into:

```text
C:\xampp\htdocs\
```

### Step 3: Start XAMPP

Start:

* Apache
* MySQL

### Step 4: Create Database

Open phpMyAdmin and create a database named:

```sql
studentclearancesystem
```

### Step 5: Import Database

1. Open phpMyAdmin.
2. Select the database.
3. Click Import.
4. Choose:

```text
setup_db.sql
```

5. Click Go.

### Step 6: Run the Project

Open your browser and navigate to:

```text
http://localhost/studentclearancesystem
```

## Project Structure

```text
studentclearancesystem/
│
├── css/
├── js/
├── images/
├── reports/
├── dashboard/
├── includes/
├── setup_db.sql
├── index.php
├── login.php
├── register.php
└── README.md
```

## Objectives

* Automate the student clearance process.
* Reduce paperwork and manual processing.
* Improve efficiency in clearance management.
* Enable real-time tracking of clearance status.
* Generate clearance reports electronically.
* Improve record management and accessibility.

## Future Improvements

* Email notifications
* SMS notifications
* Mobile application integration
* Two-factor authentication
* Advanced reporting and analytics
* Integration with student information systems

## Author

*Felloh*

Diploma in Information Communication Technology (ICT)

Machakos University
