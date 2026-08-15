# AI Agent Master Plan: Educational Platform API (Laravel 13 & PHP 8.5)

**Context for AI Agent:** You are an expert backend developer tasked with building a RESTful API using a fresh installation of **Laravel 13** and **PHP 8.5**. The database is **SQLite** for local development (ensure migrations are strictly compatible with MySQL for future production deployment). Follow modern PHP 8.5 syntax (e.g., constructor property promotion, match expressions, typed properties) and Laravel 13 best practices.

## Phase 1: Project Setup & Authentication
1. **Install Sanctum:** Install and configure **Laravel Sanctum** for token-based API authentication.
2. **Database Config:** Configure the default database connection to `sqlite` in the `.env` file.
3. **Response Standard:** Set up a Base API Controller or Trait to standardize JSON API responses (e.g., `successResponse(data, message)`, `errorResponse(message, status)`).

## Phase 2: Database Migrations & Models
Create the following Models and Migrations with appropriate eloquent relationships:
1. **User:** Update the default users table. Add `username` (unique, string), `role` (enum: 'student', 'assistant'), and `phone` (string).
2. **StudentProfile:** `user_id` (foreign key to users), `student_code` (string), `grade` (string), `profile_image` (string, nullable), `qr_code_string` (unique UUID string - backend generates it, frontend renders it), `dob` (date), `guardian_name` (string), `guardian_phone` (string).
3. **Lesson:** `chapter_name` (string), `title` (string), `duration_minutes` (integer), `video_url` (full YouTube URL string), `thumbnail_url` (string), `about_lesson` (text), `what_you_will_learn` (JSON array), `notes` (text, nullable).
4. **Attendance:** `student_id` (foreign key to users/students), `scanned_by` (foreign key to users/assistants), `created_at` (timestamp).

## Phase 3: Factories & Database Seeding
*Requirement: We need robust dummy data so the Flutter frontend developer can test immediately.*
1. **Factories:** Create factories for `User`, `StudentProfile`, and `Lesson`. Ensure `Lesson` factory uses real YouTube URL formats and valid JSON for `what_you_will_learn`. Generate UUIDs using `Str::uuid()` for `qr_code_string`.
2. **DatabaseSeeder:** Create the following:
   - 1 Admin/Assistant user (e.g., username: 'admin', password: 'password', role: 'assistant').
   - 1 specific Student user (e.g., username: 'student1', password: 'password', role: 'student') with a known `qr_code_string` like `test-qr-123` for easy testing in Postman/Flutter.
   - 10 random dummy students with their `StudentProfile`.
   - 5 dummy `Lesson` records.

## Phase 4: API Routes & Controllers Implementation
Group all routes under `api/v1`. Apply the `auth:sanctum` middleware to all routes except the login route.

**4.1 Auth Controller**
- `POST /auth/login`: Validate `username` and `password`. Return Sanctum token and the user role.

**4.2 Assistant Controller (Role: assistant)**
- `GET /assistant/student/scan/{qr_code_string}`: Find student by QR string. Return profile details (name, grade, guardian info, etc.).
- `POST /assistant/attendance/register`: Accept `student_id`. Create an `Attendance` record. Dispatch the `SendAttendanceSmsJob`.

**4.3 Student Controller (Role: student)**
- `GET /student/profile`: Return the authenticated user's profile, including their `qr_code_string` (so the Flutter app can render the QR UI).
- `GET /student/lessons`: Return all lessons. Implement an optional `?search={keyword}` query parameter to filter by lesson title. Return basic list fields (id, chapter, title, duration, thumbnail).
- `GET /student/lessons/{id}`: Return full lesson details (including YouTube URL and JSON overview). Include logic to calculate and append `previous_lesson_id` and `next_lesson_id`.

## Phase 5: Background Jobs & SMSMisr Integration
1. **Create Job:** Create a queued Job: `SendAttendanceSmsJob`. It should accept the `User` (student) model instance in its constructor.
2. **Create Service:** Create a Service class: `SmsMisrService`. Implement a method to send an SMS via HTTP client (Laravel's `Http::` facade) to the SMSMisr gateway API. The message should be in Arabic: "تم تسجيل حضور الطالب [اسم الطالب] بنجاح".
3. **Execute Job:** Call the `SmsMisrService` inside the `handle()` method of `SendAttendanceSmsJob` using the student's `guardian_phone`.

---
**Execution Instruction for AI:** Please execute these phases sequentially. Ask me for confirmation after you finish setting up Phase 2 (Migrations) before proceeding to Phase 3.