# EntEx Portal

A simple, vibrant **Common Entrance Examination Result Portal** built with PHP and MySQL, designed to run on XAMPP (localhost) and deployable to shared hosting such as Namecheap.

---

## Features

### Student Portal
- Login via Index Number / ID (auto-generated at registration)
- View exam schedule (date, time, subjects)
- View published result as a formatted report card (grade, comment, qualification status)
- View interview schedule and confirm availability / request reschedule
- View admission decision and proceed to acceptance fee payment

### Admin Panel
- **Exam Batches** – Create uniquely named exam batches with date/time; duplicates are rejected
- **Subjects** – Add/delete subjects available in the system
- **Register Student** – Individual form (with photo upload) or bulk CSV upload; index numbers auto-generated in format `JA/PS/YEAR/001`
- **All Students** – Search, filter, view profiles, reassign batches
- **Prepare Exam Schedules** – Schedule by batch (one click) or by individual one-off students
- **Prepare Exam Result** – Enter scores per subject (over /100, /60, /50); auto-calculates grade and comment; set interview date, re-sit; publish individually or all at once; force-add late students to past batches with warning
- **Schedule Interview** – Schedule interviews for qualified students; approve reschedule requests from students
- **Admission Status** – Mark students offered/rejected, write personalised notification, set admission number, confirm fee payment
- **Settings** – School name, logo, welcome message, acceptance fee URL, index prefix, serial length, grading rubrics, cut-off scores, admin password

---

## Installation (XAMPP / Localhost)

1. **Copy files** to `C:\xampp\htdocs\entex_portal\` (Windows) or `/opt/lampp/htdocs/entex_portal/` (Linux/Mac)

2. **Create database**
   - Open `http://localhost/phpmyadmin`
   - Create a new database called `entex_portal`
   - Import `database.sql`

3. **Configure** – Open `config.php` and update:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'entex_portal');
   define('DB_USER', 'root');
   define('DB_PASS', '');          // Set your MySQL password
   define('BASE_URL', 'http://localhost/entex_portal');
   ```

4. **Open** `http://localhost/entex_portal/` in your browser

5. **Default Admin Login**
   - URL: click the **🔑 Admin** button top-right on the login page
   - Username: `admin`
   - Password: `password` *(change immediately in Settings → Change Password)*

---

## Deploying to Namecheap (cPanel Hosting)

1. Zip the project folder and upload via cPanel File Manager to `public_html/entex_portal/`
2. Create a MySQL database via cPanel → MySQL Databases; import `database.sql` via phpMyAdmin
3. Update `config.php` with your live credentials and `BASE_URL`
4. Ensure `uploads/` and `uploads/students/` are writable (chmod 755)

---

## CSV Bulk Student Upload Format

No header row. Columns: `Full Name, Grade Applied, Age, Gender, Subjects (pipe-separated)`

```
John Doe,Grade 5,10,Male,Mathematics|English Language|Science
Jane Smith,JSS 1,11,Female,Mathematics|English Language
```

---

## Directory Structure

```
entex_portal/
├── index.php              # Login page (student + admin)
├── logout.php
├── config.php             # DB config & helper functions
├── database.sql           # Full database schema
├── student/               # Student-facing pages
├── admin/                 # Admin panel pages
├── includes/              # Shared header/footer partials
├── assets/css/style.css   # Red-dominant responsive stylesheet
├── assets/js/main.js      # UI interactions
└── uploads/               # Student photo uploads (protected)
```

---

## Security

- CSRF tokens on all forms
- Passwords hashed with bcrypt (`password_hash`)
- All output escaped with `htmlspecialchars`
- PDO prepared statements (SQL injection prevention)
- Upload directory protected from PHP execution
