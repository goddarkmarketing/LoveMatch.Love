<?php

namespace App\Controllers;

use App\Repositories\CoinTopupRepository;
use App\Support\Request;
use App\Support\Response;
use RuntimeException;

class CoinTopupController
{
    private const MAX_SLIP_BYTES = 5242880;

    public function __construct(private CoinTopupRepository $topups)
    {
    }

    public function packages(): void
    {
        Response::json([
            'success' => true,
            'data' => [
                'packages' => $this->topups->packages(),
            ],
        ]);
    }

    public function create(Request $request, int $userId): void
    {
        $payload = $request->json();
        $packageCode = (string) ($payload['package_code'] ?? '');
        $slipUrl = trim((string) ($payload['slip_url'] ?? '')) ?: null;
        $reference = trim((string) ($payload['transfer_reference'] ?? '')) ?: null;

        try {
            $topup = $this->topups->createRequest($userId, $packageCode, $slipUrl, $reference);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        Response::json([
            'success' => true,
            'message' => 'ส่งคำขอเติม Point แล้ว รอผู้ดูแลตรวจสอบยอดเงิน',
            'data' => [
                'topup' => $topup,
            ],
        ], 201);
    }

    public function uploadSlip(int $userId): void
    {
        if ($userId <= 0) {
            Response::json([
                'success' => false,
                'message' => 'กรุณาเข้าสู่ระบบก่อนอัปโหลดสลิป',
            ], 401);
        }

        $file = $_FILES['slip'] ?? null;
        if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::json([
                'success' => false,
                'message' => 'กรุณาเลือกไฟล์สลิปที่ต้องการอัปโหลด',
            ], 422);
        }

        if ((int) ($file['size'] ?? 0) <= 0 || (int) ($file['size'] ?? 0) > self::MAX_SLIP_BYTES) {
            Response::json([
                'success' => false,
                'message' => 'ไฟล์สลิปต้องมีขนาดไม่เกิน 5MB',
            ], 422);
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $mime = is_file($tmpName) ? (string) mime_content_type($tmpName) : '';
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];

        if (!isset($extensions[$mime])) {
            Response::json([
                'success' => false,
                'message' => 'รองรับเฉพาะไฟล์ JPG, PNG, WEBP หรือ PDF',
            ], 422);
        }

        $uploadDir = dirname(__DIR__, 2) . '/uploads/topup-slips';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            Response::json([
                'success' => false,
                'message' => 'ไม่สามารถสร้างโฟลเดอร์เก็บสลิปได้',
            ], 500);
        }

        $filename = 'topup-' . $userId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
        $destination = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            Response::json([
                'success' => false,
                'message' => 'อัปโหลดสลิปไม่สำเร็จ',
            ], 500);
        }

        Response::json([
            'success' => true,
            'message' => 'อัปโหลดสลิปแล้ว',
            'data' => [
                'slip_url' => 'uploads/topup-slips/' . $filename,
            ],
        ], 201);
    }

    public function approve(int $requestId, int $adminUserId): void
    {
        try {
            $topup = $this->topups->approve($requestId, $adminUserId);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        Response::json([
            'success' => true,
            'message' => 'อนุมัติเติม Point แล้ว',
            'data' => [
                'topup' => $topup,
            ],
        ]);
    }

    public function reject(Request $request, int $requestId, int $adminUserId): void
    {
        $payload = $request->json();
        $reason = trim((string) ($payload['reason'] ?? ''));

        try {
            $topup = $this->topups->reject($requestId, $adminUserId, $reason);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        Response::json([
            'success' => true,
            'message' => 'ปฏิเสธคำขอเติม Point แล้ว',
            'data' => [
                'topup' => $topup,
            ],
        ]);
    }
}
