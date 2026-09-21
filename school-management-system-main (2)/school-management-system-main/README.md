# 🏫 OMK School Management System

A full-featured, multi-role school management system built with **PHP**, **MySQL**, and **Bootstrap 5** — no frameworks, no npm, just pure PHP. Designed to run on any shared hosting or XAMPP setup.

---

## ✨ Features

### 👤 Roles
| Role | Access |
|------|--------|
| **Admin** | Full system control — users, classes, courses, sections, lesson plans, reports |
| **Coordinator** | Daily attendance, grade files, visual timetable builder, student overview |
| **Teacher** | Post content & documents, manage agenda, submit lesson plans |
| **Student** | View courses, weekly schedule, agenda, attendance record, download grades |

### 🔑 Key Features

#### 🛡️ Admin
- Add / edit / deactivate users (admin, coordinator, teacher, student)
- Auto-generated system IDs (e.g. `STU20250001`, `TCH20250001`)
- Manage classes, sections, courses with color coding
- Assign teachers to courses & sections
- Approve / sign / reject teacher lesson plans
- Full reports & stats dashboard
- In-app notifications system

#### 📋 Coordinator
- **Daily Attendance** — mark full-day present/absent per student per section (not per course). Visual monthly calendar, 14-day history sidebar, 30-day rate per student
- **Visual Timetable Builder** — interactive 7-day × 6-session grid. Click any empty cell to assign a course. Conflict detection prevents double-booking the same teacher at the same time. Click filled cells to edit or remove
- **Grade Files** — upload PDF/Excel/any file per course per section. Students can see and download directly
- **Students Overview** — attendance rates, grade averages, expandable profile per student
- **Programs** — weekly schedule management with room assignments

#### 🧑‍🏫 Teacher
- Post text, announcements and documents per course per section
- Add agenda events (quiz, exam, assignment, project, homework) with due dates
- Submit lesson plans for admin review
- Full calendar view of scheduled events

#### 🎓 Student
- **My Courses** — course cards with post count and upcoming events; click to open course detail (posts, agenda, calendar tabs)
- **My Schedule** — read-only visual weekly timetable (7 days × 6 sessions). Today's column highlighted. Shows today's classes in a summary card at the top
- **Agenda** — FullCalendar with all upcoming events across all courses
- **Attendance** — calendar view + log list + attendance rate progress bar + warning if below 75%
- **Grades** — download grade files uploaded by coordinator, grouped per course

---

## 🚀 Quick Setup (XAMPP)

### 1. Clone the repo
```bash
git clone https://github.com/your-username/omk-school.git
```

### 2. Place in htdocs
```
C:/xampp/htdocs/school/
```

### 3. Create the database
- Open **phpMyAdmin** → create a new database named `omk_school`
- Import `omk_school.sql`

### 4. Configure
```bash
cp includes/config.example.php includes/config.php
```
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'omk_school');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', 'http://localhost/school');
```

### 5. Create upload folders
```
school/uploads/documents/   ← teacher post attachments & lesson plans
school/uploads/grades/      ← coordinator grade files for students to download
```
Both folders are created automatically on first use, but must be writable by PHP.

### 6. Open in browser
```
http://localhost/school
```

---

## 🔐 Demo Accounts

All demo accounts use the password: **`password`**

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@omk.edu | password |
| Coordinator | sarah.morgan@omk.edu | password |
| Coordinator | david.clark@omk.edu | password |
| Teacher | michael.brown@omk.edu | password |
| Teacher | emily.wilson@omk.edu | password |
| Student | aj20250001@omk.edu | password |
| Student | md20250002@omk.edu | password |

---

## 🗂️ Project Structure

```
school/
├── admin/              ← Admin pages (dashboard, users, courses, classes, lesson plans, reports...)
├── coordinator/        ← Coordinator pages (attendance, grades, programs, students, dashboard...)
├── teacher/            ← Teacher pages (classes, agenda, lesson plans, profile...)
├── student/            ← Student pages (courses, programs, agenda, attendance, grades, profile...)
├── api/                ← AJAX endpoints (admin_ajax, coordinator_ajax, upload_grades, logout...)
├── includes/
│   ├── config.php          ← DB config (gitignored — copy from config.example.php)
│   ├── config.example.php  ← Config template
│   ├── auth.php            ← Session helpers & role guards
│   ├── header.php          ← Top navbar
│   ├── sidebar.php         ← Role-based sidebar navigation
│   └── footer.php
├── css/style.css       ← Custom styles
├── js/main.js          ← Shared JS utilities
├── uploads/
│   ├── documents/      ← Teacher post attachments & lesson plan files
│   └── grades/         ← Coordinator grade files (student downloadable)
└── omk_school.sql      ← Full database schema + demo data
```

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.x — PDO, no framework |
| Database | MySQL / MariaDB |
| Frontend | Bootstrap 5.3, Bootstrap Icons |
| Calendar | FullCalendar 6.1 |
| Charts | Chart.js |
| AJAX | Fetch API (no jQuery) |
| Server | Apache — XAMPP / Hostinger / any shared host |

---

## 📦 Deployment on Hostinger

1. Upload all files via **File Manager** or FTP to `public_html/school/`
2. Create a MySQL database in Hostinger control panel
3. Import `omk_school.sql`
4. Copy `includes/config.example.php` → `includes/config.php` and fill in:
   - Hostinger DB credentials
   - Your domain as `BASE_URL` (e.g. `https://yourdomain.com/school`)

---

## 📄 License

This project is open-source. Feel free to use, modify and distribute for educational or commercial purposes.

---

## 👨‍💻 Built by

**Ibrahim Jarkas** — Web Development   
🌐 [zearex.com](https://zearex.com) &nbsp;|&nbsp; 📧 ibrahimjarkascs@gmail.com
