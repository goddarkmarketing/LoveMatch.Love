# LoveMatch.Love Backend Quick Start

## สิ่งที่มีแล้ว
- schema ฐานข้อมูล: [db/schema.sql](/C:/xampp/htdocs/rwmlouey/db/schema.sql)
- roadmap ระบบ: [docs/backend-roadmap.md](/C:/xampp/htdocs/rwmlouey/docs/backend-roadmap.md)
- API auth เบื้องต้น: [api/index.php](/C:/xampp/htdocs/rwmlouey/api/index.php)

## 1. สร้างฐานข้อมูล
เปิด `phpMyAdmin` หรือ MySQL client แล้ว import ไฟล์นี้:
- [db/schema.sql](/C:/xampp/htdocs/rwmlouey/db/schema.sql)
- [db/seed-demo-users.sql](/C:/xampp/htdocs/rwmlouey/db/seed-demo-users.sql)

ค่าเริ่มต้นในระบบตอนนี้:
- database: `lovematch_love`
- username: `root`
- password: ว่าง

ถ้าค่าไม่ตรง ให้แก้ที่:
- [config/database.php](/C:/xampp/htdocs/rwmlouey/config/database.php)

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
