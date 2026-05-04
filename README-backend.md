# LoveMatch.Love Backend Quick Start

## สิ่งที่มีแล้ว
- schema ฐานข้อมูล: [db/schema.sql](/C:/xampp/htdocs/rwmlouey/db/schema.sql)
- roadmap ระบบ: [docs/backend-roadmap.md](/C:/xampp/htdocs/rwmlouey/docs/backend-roadmap.md)
- API auth เบื้องต้น: [api/index.php](/C:/xampp/htdocs/rwmlouey/api/index.php)

## 0. อัปเดตตาราง payments (ถ้า DB เดิมมีอยู่แล้ว)
ถ้าเคย import `schema.sql` ก่อนมีค่า `registration` ใน `payment_target` ให้รันครั้งเดียว:

- [db/migrations/001_payment_target_registration.sql](/C:/xampp/htdocs/rwmlouey/db/migrations/001_payment_target_registration.sql)

## 0b. ชำระเงินตอนสมัคร (Omise + โอนธนาคาร)
- ตั้งค่า: คัดลอก [config/payments.local.php.example](/C:/xampp/htdocs/rwmlouey/config/payments.local.php.example) เป็น `config/payments.local.php` แล้วใส่ **Public / Secret key** (test หรือ live) และข้อมูลบัญชีรับโอน
- หรือใช้ environment: `OMISE_PUBLIC_KEY`, `OMISE_SECRET_KEY`, `REGISTRATION_FEE_THB`
- **อย่า commit** `payments.local.php` — มีใน `.gitignore` แล้ว
- ค่าสมัครเริ่มต้น ฿199 — ตั้ง `registration_fee_thb` เป็น `0` ใน local ได้ถ้าต้องการข้ามการชำระระหว่างพัฒนา

## 1. สร้างฐานข้อมูล
เปิด `phpMyAdmin` หรือ MySQL client แล้ว import ไฟล์นี้:
- [db/schema.sql](/C:/xampp/htdocs/rwmlouey/db/schema.sql)
- [db/seed-demo-users.sql](/C:/xampp/htdocs/rwmlouey/db/seed-demo-users.sql)

ค่าเริ่มต้นในระบบตอนนี้:
- database: `lovematch_love`
- username: `root`
- password: ว่าง

ถ้าค่าไม่ตรง ให้ตั้งค่าแบบใดแบบหนึ่ง:
- **บนเซิร์ฟเวอร์ / ลูกค้า:** คัดลอก [config/database.local.php.example](/C:/xampp/htdocs/rwmlouey/config/database.local.php.example) เป็น `config/database.local.php` แล้วใส่ user/password จริง (ไฟล์นี้ถูก gitignore)
- **หรือใช้ environment:** `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

### เข้าไม่ได้ / ข้อความ `1698 Access denied for user 'root'@'localhost'`
บน Linux (Ubuntu ฯลฯ) บัญชี `root` ของ MySQL/MariaDB มักใช้ `auth_socket` ไม่ให้ PHP ล็อกอินด้วยรหัสผ่าน

แก้โดยสร้าง user สำหรับแอปแล้วใส่ใน `database.local.php` หรือ env — ตัวอย่างคำสั่งอยู่ใน [db/grant-app-user.sql](/C:/xampp/htdocs/rwmlouey/db/grant-app-user.sql) และในไฟล์ `.example` ด้านบน

## 2. ทดสอบ API
เมื่อ Apache ทำงานแล้ว ใช้ URL เหล่านี้ได้:

- `GET /rwmlouey/api/health`
- `POST /rwmlouey/api/auth/register`
- `POST /rwmlouey/api/auth/login`
- `POST /rwmlouey/api/auth/logout`
- `GET /rwmlouey/api/auth/me`

บัญชีทดลอง:
- `test1@example.com` / `123456`
- `test2@example.com` / `123456`
- `admin@lovematch.love` / `123456`

ตัวอย่าง `register`

```json
POST http://localhost/rwmlouey/api/auth/register
Content-Type: application/json

{
  "first_name": "Mike",
  "last_name": "Demo",
  "email": "mike@example.com",
  "password": "123456",
  "gender": "male",
  "interested_in": "female"
}
```

ตัวอย่าง `login`

```json
POST http://localhost/rwmlouey/api/auth/login
Content-Type: application/json

{
  "email": "mike@example.com",
  "password": "123456"
}
```

## 3. โครงสร้างไฟล์
- [api/index.php](/C:/xampp/htdocs/rwmlouey/api/index.php): router หลัก
- [api/.htaccess](/C:/xampp/htdocs/rwmlouey/api/.htaccess): rewrite route
- [src/Controllers/AuthController.php](/C:/xampp/htdocs/rwmlouey/src/Controllers/AuthController.php): auth endpoints
- [src/Repositories/UserRepository.php](/C:/xampp/htdocs/rwmlouey/src/Repositories/UserRepository.php): คุยกับฐานข้อมูล
- [src/Support/Database.php](/C:/xampp/htdocs/rwmlouey/src/Support/Database.php): PDO connection
- [src/Support/Request.php](/C:/xampp/htdocs/rwmlouey/src/Support/Request.php): request helper
- [src/Support/Response.php](/C:/xampp/htdocs/rwmlouey/src/Support/Response.php): JSON response helper

## 4. สิ่งที่ระบบทำแล้ว
- สมัครสมาชิก
- login/logout ด้วย session
- คืนค่าผู้ใช้ปัจจุบัน
- สร้าง wallet พร้อม signup bonus 50 เหรียญ
- ใช้ role `member` อัตโนมัติ

## 5. สิ่งที่ควรทำต่อทันที
1. เชื่อม popup `เข้าสู่ระบบ` และ `สมัครสมาชิก` ในหน้าเว็บเข้ากับ API
2. ทำ `GET /api/gifts`
3. ทำ `GET /api/chat/rooms`
4. ทำ `POST /api/chat/rooms/{id}/messages`
5. ทำ `POST /api/gifts/send`
6. ทำ `GET /api/subscription/plans`
7. ทำหน้า admin login และ dashboard
