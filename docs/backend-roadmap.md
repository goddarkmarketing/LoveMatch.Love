# LoveMatch.Love Backend Roadmap

เอกสารนี้สรุปโครงระบบหลังบ้านที่สอดคล้องกับหน้าเว็บปัจจุบันใน [index.html](/C:/xampp/htdocs/rwmlouey/index.html)

## เป้าหมาย
- รองรับหน้า `เข้าสู่ระบบ`, `สมัครสมาชิก`, `Chat`, `Gifts`, `Premium`
- ออกแบบให้เหมาะกับ `XAMPP + MySQL/MariaDB`
- ต่อเป็น `PHP แบบ native`, `CodeIgniter`, `Laravel` หรือ `Node.js` ได้

## โมดูลหลัก
### 1. Authentication
- สมัครสมาชิกด้วยชื่อ, นามสกุล, อีเมล, รหัสผ่าน, เพศ, เพศที่ต้องการพบ
- เข้าสู่ระบบด้วยอีเมลและรหัสผ่าน
- ยืนยันอีเมล
- ลืมรหัสผ่าน / รีเซ็ตรหัสผ่าน
- จัดการ session หรือ JWT

ตารางหลัก:
- `users`
- `roles`

API ที่ควรมี:
- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`
- `GET /api/auth/me`

### 2. Profile & Discover
- แก้ไขโปรไฟล์
- อัปโหลดรูป
- เลือกภาษา / ความสนใจ
- ระบบค้นหาโปรไฟล์
- ระบบแนะนำคู่
- กดไลก์ / super like / pass / rewind

ตารางหลัก:
- `user_photos`
- `user_languages`
- `interests`
- `user_interests`
- `profile_preferences`
- `swipes`
- `matches`

API ที่ควรมี:
- `GET /api/profiles/discover`
- `GET /api/profiles/{id}`
- `PUT /api/profile/me`
- `POST /api/profile/photos`
- `POST /api/swipes`
- `GET /api/matches`

### 3. Chat
- ห้องแชทรวม 3 ห้องแรกตรงกับหน้าเว็บ: `general`, `thai`, `international`
- แชทส่วนตัวเมื่อ match กันหรือถูกปลดล็อกแล้ว
- สถานะออนไลน์ / last seen
- แปลภาษาในข้อความ
- รายงานผู้ใช้จากห้องแชท

ตารางหลัก:
- `chat_rooms`
- `chat_room_members`
- `messages`
- `reports`
- `user_blocks`

API ที่ควรมี:
- `GET /api/chat/rooms`
- `GET /api/chat/rooms/{roomId}/messages`
- `POST /api/chat/rooms/{roomId}/messages`
- `POST /api/chat/rooms/{roomId}/translate`
- `POST /api/chat/rooms/{roomId}/report`
- `POST /api/chat/private/start`

### 4. Gifts & Unlock
- ดึงรายการของขวัญจากฐานข้อมูล
- ส่งของขวัญพร้อมข้อความ
- หักเหรียญจาก wallet
- สร้างประวัติการปลดล็อกแชทตามจำนวนวันหรือถาวร
- ตรวจสอบสิทธิ์ก่อนส่งข้อความ private

ตารางหลัก:
- `gift_catalog`
- `wallets`
- `wallet_transactions`
- `gift_transactions`
- `chat_unlocks`

API ที่ควรมี:
- `GET /api/gifts`
- `POST /api/gifts/send`
- `GET /api/wallet`
- `GET /api/chat/unlocks`

กติกาที่ควรใช้:
- ถ้า `unlock_type = days` ให้คำนวณ `unlock_end_at`
- ถ้า `unlock_type = permanent` ให้ `unlock_end_at = NULL`
- ห้อง private ต้องเช็ก unlock ก่อนส่งข้อความ

### 5. Premium & Billing
- เลือกแพ็กเกจ Free / Premium / VIP
- ชำระเงินผ่านบัตร, PromptPay, โอน
- รอการยืนยันชำระ
- เปิดสิทธิ์สมาชิกเมื่อจ่ายสำเร็จ
- ประวัติการชำระเงิน

ตารางหลัก:
- `subscription_plans`
- `subscriptions`
- `payments`

API ที่ควรมี:
- `GET /api/subscription/plans`
- `POST /api/subscription/checkout`
- `POST /api/payments/confirm`
- `GET /api/payments/history`

### 6. Safety / Anti-Scam
- ตรวจข้อความเสี่ยง
- เก็บคะแนนความเสี่ยง
- ซ่อนข้อความที่ถูก flag
- รายงานและ review โดย moderator
- block user

ตารางหลัก:
- `reports`
- `moderation_logs`
- `user_blocks`

API ที่ควรมี:
- `POST /api/reports`
- `POST /api/blocks`
- `DELETE /api/blocks/{userId}`

### 7. Notifications
- แจ้งเตือนเมื่อมีข้อความใหม่
- แจ้งเตือนเมื่อมี gift
- แจ้งเตือนเมื่อมี match
- แจ้งเตือนเรื่อง subscription

ตารางหลัก:
- `notifications`

API ที่ควรมี:
- `GET /api/notifications`
- `POST /api/notifications/read`

### 8. Admin Panel
- Dashboard สรุปผู้ใช้ใหม่, active users, รายได้, รายงาน, ยอดส่งของขวัญ
- จัดการสมาชิก
- จัดการรูปภาพและโปรไฟล์ที่ถูกรายงาน
- จัดการห้องแชท
- จัดการแพ็กเกจ
- จัดการรายการของขวัญ
- จัดการธุรกรรม
- จัดการ report และ moderation
- เก็บ audit log ทุก action สำคัญ

ตารางหลัก:
- `admin_audit_logs`
- ใช้ร่วมกับทุกตารางด้านบน

หน้า admin ที่ควรมี:
- `/admin/dashboard`
- `/admin/users`
- `/admin/reports`
- `/admin/payments`
- `/admin/subscriptions`
- `/admin/gifts`
- `/admin/chat-rooms`
- `/admin/moderation-logs`

## สิทธิ์ผู้ใช้
- `member`: ใช้งานทั่วไป
- `premium`: ได้สิทธิ์ Premium
- `vip`: ได้สิทธิ์ VIP
- `moderator`: ตรวจ report และ moderation
- `admin`: จัดการทุกระบบ

## ลำดับพัฒนาที่แนะนำ
### Phase 1: ระบบพื้นฐาน
- สมัครสมาชิก
- เข้าสู่ระบบ
- โปรไฟล์ผู้ใช้
- discover + like/pass
- ห้องแชทรวม

ผลลัพธ์:
- เว็บเริ่มใช้งานได้จริง

### Phase 2: ระบบรายได้
- wallet
- gift catalog
- gift send
- chat unlock
- premium/vip
- payment flow

ผลลัพธ์:
- เริ่มมี monetization

### Phase 3: ระบบดูแลแพลตฟอร์ม
- reports
- moderation logs
- block user
- admin dashboard
- analytics

ผลลัพธ์:
- คุมคุณภาพและความปลอดภัยของแพลตฟอร์มได้

## โครง backend ที่แนะนำ
ถ้าจะทำต่อเร็วและดูแลง่าย ผมแนะนำ 2 ทาง

### ทางเลือก A: PHP + Laravel
เหมาะถ้า:
- ใช้ XAMPP อยู่แล้ว
- อยากได้ auth, migration, queue, validation พร้อมใช้

โฟลเดอร์หลักที่ควรมี:
- `app/Models`
- `app/Http/Controllers/Api`
- `database/migrations`
- `routes/api.php`

### ทางเลือก B: PHP Native แบบแยกชั้น
เหมาะถ้า:
- อยากเริ่มไว
- โปรเจกต์ไม่ใหญ่ช่วงแรก

โครงสร้างที่ควรมี:
- `public/`
- `config/`
- `src/Controllers/`
- `src/Services/`
- `src/Repositories/`
- `src/Middleware/`
- `storage/logs/`

## งานที่ควรทำต่อทันที
1. import [schema.sql](/C:/xampp/htdocs/rwmlouey/db/schema.sql)
2. เลือก stack backend ว่าจะเป็น `Laravel` หรือ `PHP native`
3. ทำ `register/login API` ก่อน
4. ทำ `profile + discover`
5. ทำ `chat rooms + messages`
6. ทำ `gift send + wallet`
7. ทำ `premium checkout`
8. ทำ `admin dashboard`

## หมายเหตุ
- schema นี้ออกแบบให้รองรับสิ่งที่หน้าเว็บมีอยู่แล้ว ไม่ได้เป็นเพียง demo
- ถ้าจะใช้งานจริงควรเสริม:
  - rate limiting
  - CSRF protection
  - password hashing ด้วย `password_hash()`
  - file upload validation
  - image moderation
  - payment webhook verification
