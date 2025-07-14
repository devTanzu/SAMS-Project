# 🎓 Student Attendance Management System (SAMS)

**A Web-Based Application for Seamless Student Attendance Tracking**

SAMS is a comprehensive and user-friendly web application designed to digitalize the student attendance management process for schools, colleges, and universities. It provides secure dashboards for administrators, teachers, and students, offering real-time attendance tracking, management, and reporting tools.

---

## 👥 Team Members

| Name                  |
|-----------------------|
| Jannatul Ferdues Kely |
| Tanjina Akter         |
| Amir Hamza            |
---

## 🚀 Live Demo  
> 🌐 [Click here to access the live version](http://localhost/sams/) *(Replace with actual deployed URL)*

---

## 🧰 Tech Stack

| Technology | Description |
|------------|-------------|
| **HTML5, CSS3, JavaScript** | Frontend structure and interactivity |
| **PHP** | Backend logic and server-side scripting |
| **MySQL** | Database to store user, course, and attendance information |
| **XAMPP** | Local server environment for development |

---

## 🔑 Core Features

### 🛡️ Admin Panel
- Secure admin login
- Manage all teacher accounts
- Manage student records
- Create and update courses
- View and export global attendance reports
- Monitor low-attendance trends across all classes

### 👨‍🏫 For Teachers
- Secure login and authentication
- Dashboard with summary statistics
- Add, view, edit, and delete student records
- Create and manage courses/classes
- Take daily attendance by course
- View student-wise attendance history
- Export attendance reports (CSV/Excel)
- View low-attendance warnings

### 👩‍🎓 For Students
- Login using unique student ID
- View personal profile
- Check daily/monthly attendance
- Subject-wise attendance percentage
- Alerts for low attendance (below 75%)
- Export personal attendance report

---

## 🖥️ System Modules

### 🛡️ Admin Panel

| Feature | Description |
|---------|-------------|
| **Login/Register** | Secure login for admin |
| **Manage Teachers** | Add/edit/remove teacher accounts |
| **Manage Students** | Add/edit/remove student records |
| **Manage Courses** | Create and modify courses |
| **View Reports** | View/export global attendance reports |
| **System Monitoring** | See all attendance stats and low-performance alerts |

### 🧑‍🏫 Teacher Panel

| Feature | Description |
|---------|-------------|
| **Login/Register** | Secure teacher authentication |
| **Manage Students** | Add/edit/delete student records |
| **Manage Courses** | Create and manage subjects/classes |
| **Take Attendance** | Mark attendance daily by course |
| **Attendance History** | View logs by date, course, or student |
| **Reports** | Export CSV/PDF attendance reports |

### 👨‍🎓 Student Panel

| Feature | Description |
|---------|-------------|
| **Login** | Login using ID and password |
| **Profile** | View name, course, roll, and other info |
| **View Attendance** | Subject-wise and overall statistics |
| **Alerts** | Notifications for <75% attendance |
| **Download Report** | Export personal attendance report |

---

## 🗃️ Database Structure (Simplified)

- `admins`: Stores admin login credentials  
- `teachers`: Stores teacher login credentials and profiles  
- `students`: Contains student info (ID, name, course, etc.)  
- `courses`: Lists all courses with code and title  
- `attendance`: Logs student attendance (date, course, status)  

---

## ⚙️ Setup & Installation Instructions

### ✅ Prerequisites
- Install [XAMPP](https://www.apachefriends.org/)
- Basic knowledge of PHP & MySQL

### 🛠️ Steps to Run Locally
1. Download or clone this repository
2. Move the project folder to `C:/xampp/htdocs/`
3. Start **Apache** and **MySQL** from the XAMPP control panel
4. Open **phpMyAdmin** and import the `sams_db.sql` file
5. Access the app from your browser:  
   `http://localhost/sams/`

### 🔐 Sample Login Credentials
- **Admin:** `admin@example.com` / `admin123`  
- **Teacher:** `teacher@example.com` / `123456`  
- **Student:** `student01@example.com` / `studen123`

---

## 🎯 Future Improvements
- Biometric or QR code-based attendance
- Email/SMS alerts to students or guardians
- Live attendance charts and data visualization
- Mobile-responsive design or Android app
- Enhanced reporting dashboard for admins

---

## 📄 License
This project is open-source and available under the [MIT License](LICENSE).

---

> 🔧 *Feel free to contribute to this project or customize it for your institution’s needs!*