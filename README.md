# 🎓 Student Attendance Management System (SAMS)

**A Web-Based Application for Seamless Student Attendance Tracking**

SAMS is a comprehensive and user-friendly web application designed to digitalize the attendance management process for schools, colleges, and universities. It provides secure dashboards for administrators, teachers, and students with real-time tracking and detailed reports.

---

## 👥 Team Members

- Jannatul Ferdues Kely  
- Tanjina Akter  
- Amir Hamza  

---

## 🚀 Live Demo

🌐 [Click here to access the live version](http://localhost/sams/)  
> *(Replace with actual deployed URL when live)*

---

## 🧰 Tech Stack

| Technology             | Purpose                                      |
|------------------------|----------------------------------------------|
| HTML5, CSS3, JavaScript | Frontend design and interactivity            |
| PHP                   | Backend logic and server-side scripting       |
| MySQL                 | Database management                           |
| XAMPP                 | Local development environment (Apache + MySQL) |

---

## 🔑 Core Features

### 🛡️ Admin Panel
- Secure admin login
- Teacher and student account management
- Course creation and updates
- Attendance overview and export
- Monitor low-attendance patterns

### 👨‍🏫 Teacher Panel
- Secure login
- Manage students and courses
- Daily attendance tracking
- Attendance history by student or course
- CSV/Excel report export
- Low-attendance alerts

### 👩‍🎓 Student Panel
- Login with student ID
- Profile overview
- Daily/monthly attendance view
- Subject-wise statistics
- Alerts for <75% attendance
- Export personal attendance reports

---

## 🖥️ System Modules

### 🔐 Admin Module

| Feature            | Description                                |
|--------------------|--------------------------------------------|
| Login/Register     | Secure login for admin users               |
| Manage Teachers    | Add, update, delete teacher accounts       |
| Manage Students    | Add, update, delete student records        |
| Manage Courses     | Create/edit course listings                |
| Attendance Reports | View/export detailed attendance reports    |
| Monitoring         | Track low attendance & system metrics      |

### 👨‍🏫 Teacher Module

| Feature            | Description                                |
|--------------------|--------------------------------------------|
| Login              | Secure login for teachers                  |
| Manage Students    | Add/edit/delete students                   |
| Manage Courses     | Handle subjects and course details         |
| Take Attendance    | Daily attendance by course                 |
| Attendance History | Filter attendance logs                     |
| Reports            | Export attendance in CSV/PDF formats       |

### 👨‍🎓 Student Module

| Feature            | Description                                |
|--------------------|--------------------------------------------|
| Login              | Secure login with student ID               |
| Profile            | View profile and academic details          |
| Attendance View    | Track daily, monthly, and subject-wise     |
| Alerts             | Notification for low attendance (<75%)     |
| Download Report    | Export attendance report (PDF/CSV)         |

---

## 🗃️ Database Structure (Simplified)

- `admins` — Stores admin login credentials  
- `teachers` — Stores teacher credentials and profiles  
- `students` — Student records with course linkage  
- `courses` — Course metadata (code, title)  
- `attendance` — Attendance logs (student, date, status, course)

---

## ⚙️ Setup Instructions

### ✅ Prerequisites

- Install [XAMPP](https://www.apachefriends.org/)
- Basic knowledge of PHP and MySQL

### 🛠️ Local Installation

1. Clone or download this repository  
2. Move the folder to `C:/xampp/htdocs/`  
3. Start **Apache** and **MySQL** from XAMPP Control Panel  
4. Open **phpMyAdmin** and import `sams_db.sql`  
5. Visit `http://localhost/sams/` in your browser

### 🔐 Sample Login Credentials

| Role    | Email                     | Password   |
|---------|---------------------------|------------|
| Admin   | admin@example.com         | admin123   |
| Teacher | teacher@example.com       | 123456     |
| Student | student01@example.com     | studen123  |

---

## 🎯 Future Improvements

- Biometric/QR code attendance tracking  
- Email/SMS notifications for low attendance  
- Live attendance charts and dashboards  
- Mobile-friendly interface or Android app  
- Enhanced analytics for admin

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).

---

> 🔧 *Contributions and suggestions are welcome to improve this project!*
✅ What’s Improved:
Better use of spacing and markdown formatting

Tables and headings aligned

Code blocks and links made more consistent

Easier to scan and understand