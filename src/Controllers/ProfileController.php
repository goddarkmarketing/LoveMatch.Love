<?php

namespace App\Controllers;

use App\Repositories\UserPhotoRepository;
use App\Repositories\UserRepository;
use App\Support\Response;
use RuntimeException;

class ProfileController
{
    private const MAX_PHOTO_BYTES = 5_242_880;

    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private UserRepository $users,
        private UserPhotoRepository $photos
    ) {
    }

    public function submitOnboarding(int $userId): void
    {
        if ($userId <= 0) {
            Response::json([
                'success' => false,
                'message' => 'กรุณาเข้าสู่ระบบก่อนยืนยันตัวตน',
            ], 401);
        }

        $user = $this->users->findById($userId);
        if (!$user) {
            Response::json([
                'success' => false,
                'message' => 'ไม่พบผู้ใช้',
            ], 404);
        }

        if ((int) ($user['is_profile_completed'] ?? 0) === 1) {
            Response::json([
                'success' => false,
                'message' => 'คุณยืนยันตัวตนแล้ว',
            ], 409);
        }

        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $birthDate = trim((string) ($_POST['birth_date'] ?? ''));
        $province = trim((string) ($_POST['province'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $bio = trim((string) ($_POST['bio'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));

        if ($displayName === '' || mb_strlen($displayName) < 2) {
            Response::json([
                'success' => false,
                'message' => 'กรุณากรอกชื่อที่แสดงในโปรไฟล์',
            ], 422);
        }

        if (!$this->isValidBirthDate($birthDate)) {
            Response::json([
                'success' => false,
                'message' => 'กรุณาเลือกวันเกิดที่ถูกต้อง (อายุ 18 ปีขึ้นไป)',
            ], 422);
        }

        if ($province === '' || $city === '') {
            Response::json([
                'success' => false,
                'message' => 'กรุณากรอกจังหวัดและเมือง/เขต',
            ], 422);
        }

        $profileFiles = $this->normalizeUploadedFiles($_FILES['photos'] ?? null);
        if ($profileFiles === []) {
            Response::json([
                'success' => false,
                'message' => 'กรุณาอัปโหลดรูปโปรไฟล์อย่างน้อย 1 รูป',
            ], 422);
        }

        if (count($profileFiles) > 5) {
            Response::json([
                'success' => false,
                'message' => 'อัปโหลดรูปโปรไฟล์ได้ไม่เกิน 5 รูป',
            ], 422);
        }

        $verificationFile = $this->normalizeSingleUpload($_FILES['verification_photo'] ?? null);
        if ($verificationFile === null) {
            Response::json([
                'success' => false,
                'message' => 'กรุณาอัปโหลดรูปยืนยันตัวตน (เช่น ถ่ายคู่บัตรประชาชน)',
            ], 422);
        }

        $uploadDir = dirname(__DIR__, 2) . '/uploads/profile-photos';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            Response::json([
                'success' => false,
                'message' => 'ไม่สามารถสร้างโฟลเดอร์เก็บรูปได้',
            ], 500);
        }

        try {
            $storedProfile = [];
            foreach ($profileFiles as $index => $file) {
                $storedProfile[] = $this->storeImage($file, $userId, 'profile', $uploadDir, $index);
            }

            $storedVerification = $this->storeImage($verificationFile, $userId, 'verify', $uploadDir, 0);

            $avatarUrl = $storedProfile[0]['file_url'];
            $photoRows = [];
            foreach ($storedProfile as $index => $row) {
                $photoRows[] = [
                    'file_url' => $row['file_url'],
                    'sort_order' => $index,
                    'is_primary' => $index === 0 ? 1 : 0,
                ];
            }
            $photoRows[] = [
                'file_url' => $storedVerification['file_url'],
                'sort_order' => 100,
                'is_primary' => 0,
            ];

            $this->users->completeOnboarding($userId, [
                'display_name' => $displayName,
                'birth_date' => $birthDate,
                'province' => $province,
                'city' => $city,
                'bio' => $bio !== '' ? $bio : null,
                'phone' => $phone !== '' ? $phone : null,
                'avatar_url' => $avatarUrl,
            ], $photoRows, $this->photos);

            $fresh = $this->users->findById($userId);
            $status = (string) ($fresh['status'] ?? 'active');
            $message = $status === 'pending_verification'
                ? 'ส่งข้อมูลยืนยันตัวตนแล้ว ทีมงานจะตรวจสอบก่อนเปิดใช้งานเต็มรูปแบบ'
                : 'บันทึกโปรไฟล์และยืนยันตัวตนเรียบร้อยแล้ว';

            Response::json([
                'success' => true,
                'message' => $message,
                'data' => ['user' => $fresh],
            ]);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function isValidBirthDate(string $birthDate): bool
    {
        $date = \DateTime::createFromFormat('Y-m-d', $birthDate);
        if (!$date || $date->format('Y-m-d') !== $birthDate) {
            return false;
        }

        $today = new \DateTime('today');
        $age = (int) $date->diff($today)->y;

        return $age >= 18 && $age <= 100;
    }

    /**
     * @return list<array{tmp_name: string, size: int, error: int}>
     */
    private function normalizeUploadedFiles(mixed $files): array
    {
        if (!is_array($files) || !isset($files['error'])) {
            return [];
        }

        if (!is_array($files['error'])) {
            if ((int) ($files['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                return [];
            }

            return [[
                'tmp_name' => (string) ($files['tmp_name'] ?? ''),
                'size' => (int) ($files['size'] ?? 0),
                'error' => (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE),
            ]];
        }

        $normalized = [];
        foreach ($files['error'] as $index => $error) {
            if ((int) $error !== UPLOAD_ERR_OK) {
                continue;
            }
            $normalized[] = [
                'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
                'size' => (int) ($files['size'][$index] ?? 0),
                'error' => (int) $error,
            ];
        }

        return $normalized;
    }

    /**
     * @return array{tmp_name: string, size: int, error: int}|null
     */
    private function normalizeSingleUpload(mixed $file): ?array
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        return [
            'tmp_name' => (string) ($file['tmp_name'] ?? ''),
            'size' => (int) ($file['size'] ?? 0),
            'error' => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
        ];
    }

    /**
     * @param array{tmp_name: string, size: int, error: int} $file
     * @return array{file_url: string}
     */
    private function storeImage(array $file, int $userId, string $prefix, string $uploadDir, int $index): array
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('อัปโหลดรูปไม่สำเร็จ');
        }

        if ((int) ($file['size'] ?? 0) <= 0 || (int) ($file['size'] ?? 0) > self::MAX_PHOTO_BYTES) {
            throw new RuntimeException('รูปภาพต้องมีขนาดไม่เกิน 5MB');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmpName)) {
            throw new RuntimeException('ไฟล์รูปไม่ถูกต้อง');
        }

        $mime = (string) mime_content_type($tmpName);
        if (!isset(self::ALLOWED_MIMES[$mime])) {
            throw new RuntimeException('รองรับเฉพาะไฟล์ JPG, PNG หรือ WEBP');
        }

        $filename = $prefix . '-' . $userId . '-' . date('YmdHis') . '-' . $index . '-' . bin2hex(random_bytes(6))
            . '.' . self::ALLOWED_MIMES[$mime];
        $destination = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('บันทึกรูปไม่สำเร็จ');
        }

        return ['file_url' => 'uploads/profile-photos/' . $filename];
    }
}
