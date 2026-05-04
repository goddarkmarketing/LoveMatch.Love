<?php

namespace App\Controllers;

use App\Repositories\PaymentRepository;
use App\Repositories\UserRepository;
use App\Support\OmiseClient;
use App\Support\Request;
use App\Support\Response;
use PDO;
use RuntimeException;

class AuthController
{
    public function __construct(
        private UserRepository $users,
        private PaymentRepository $payments,
        private array $paymentConfig,
        private PDO $pdo
    ) {
    }

    public function userRecord(int $userId): ?array
    {
        return $this->users->findById($userId);
    }

    public function register(Request $request): void
    {
        $payload = $request->json();

        $firstName = trim((string) ($payload['first_name'] ?? ''));
        $lastName = trim((string) ($payload['last_name'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $gender = (string) ($payload['gender'] ?? '');
        $interestedIn = (string) ($payload['interested_in'] ?? '');

        if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
            Response::json([
                'success' => false,
                'message' => 'first_name, last_name, email และ password จำเป็นต้องกรอก',
            ], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json([
                'success' => false,
                'message' => 'รูปแบบอีเมลไม่ถูกต้อง',
            ], 422);
        }

        if (mb_strlen($password) < 6) {
            Response::json([
                'success' => false,
                'message' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร',
            ], 422);
        }

        if ($this->users->findByEmail($email)) {
            Response::json([
                'success' => false,
                'message' => 'อีเมลนี้ถูกใช้งานแล้ว',
            ], 409);
        }

        $fee = (float) ($this->paymentConfig['registration_fee_thb'] ?? 0);
        $paymentMethod = trim((string) ($payload['payment_method'] ?? 'bank_transfer'));

        if ($fee > 0) {
            if (!in_array($paymentMethod, ['bank_transfer', 'credit_card'], true)) {
                Response::json([
                    'success' => false,
                    'message' => 'เลือกวิธีชำระเงิน: bank_transfer หรือ credit_card',
                ], 422);
            }
            if ($paymentMethod === 'credit_card' && trim((string) ($this->paymentConfig['omise_secret_key'] ?? '')) === '') {
                Response::json([
                    'success' => false,
                    'message' => 'ระบบยังไม่ตั้งค่า Omise (secret key) สำหรับรับบัตร',
                ], 503);
            }
        }

        if ($fee <= 0) {
            try {
                $user = $this->users->create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'display_name' => trim($firstName . ' ' . $lastName),
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'gender' => $gender,
                    'interested_in' => $interestedIn,
                ]);
            } catch (RuntimeException $exception) {
                Response::json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 500);
            }

            $_SESSION['user_id'] = $user['id'];

            Response::json([
                'success' => true,
                'message' => 'สมัครสมาชิกสำเร็จ',
                'data' => ['user' => $user],
            ], 201);
        }

        $omiseToken = trim((string) ($payload['omise_token'] ?? ''));
        if ($paymentMethod === 'credit_card' && $omiseToken === '') {
            Response::json([
                'success' => false,
                'message' => 'กรุณากรอกข้อมูลบัตรให้ครบ หรือเลือกโอนผ่านธนาคาร',
            ], 422);
        }

        $userId = 0;
        $this->pdo->beginTransaction();

        try {
            $user = $this->users->create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'display_name' => trim($firstName . ' ' . $lastName),
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'gender' => $gender,
                'interested_in' => $interestedIn,
                'status' => 'pending_verification',
            ]);
            $userId = (int) $user['id'];

            if ($paymentMethod === 'credit_card') {
                $omise = new OmiseClient();
                $satang = (int) round($fee * 100);
                $result = $omise->createCharge(
                    (string) $this->paymentConfig['omise_secret_key'],
                    $satang,
                    $omiseToken,
                    [
                        'email' => mb_strtolower($email),
                        'purpose' => 'registration',
                    ]
                );

                if (!$result['ok']) {
                    $this->pdo->rollBack();
                    Response::json([
                        'success' => false,
                        'message' => $result['message'] ?? 'ชำระเงินด้วยบัตรไม่สำเร็จ',
                    ], 402);
                }

                $this->payments->createRegistrationPayment(
                    $userId,
                    'credit_card',
                    $fee,
                    $result['charge_id'] ?? null,
                    'paid'
                );
                $this->users->updateStatus($userId, 'active');
            } else {
                $this->payments->createRegistrationPayment(
                    $userId,
                    'bank_transfer',
                    $fee,
                    null,
                    'pending'
                );
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }

        $fresh = $this->users->findById($userId);
        $_SESSION['user_id'] = $userId;

        $data = ['user' => $fresh];
        if ($paymentMethod === 'bank_transfer') {
            $data['registration_payment'] = [
                'status' => 'pending',
                'amount_thb' => $fee,
                'bank_name' => (string) ($this->paymentConfig['bank_name'] ?? ''),
                'bank_account_name' => (string) ($this->paymentConfig['bank_account_name'] ?? ''),
                'bank_account_number' => (string) ($this->paymentConfig['bank_account_number'] ?? ''),
                'transfer_reference_note' => (string) ($this->paymentConfig['transfer_reference_note'] ?? ''),
            ];
        }

        Response::json([
            'success' => true,
            'message' => $paymentMethod === 'bank_transfer'
                ? 'สมัครสมาชิกสำเร็จ กรุณาโอนค่าสมัครตามข้อมูลด้านล่าง'
                : 'สมัครสมาชิกและชำระเงินสำเร็จ',
            'data' => $data,
        ], 201);
    }

    public function login(Request $request): void
    {
        $payload = $request->json();
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($email === '' || $password === '') {
            Response::json([
                'success' => false,
                'message' => 'กรุณากรอกอีเมลและรหัสผ่าน',
            ], 422);
        }

        $user = $this->users->findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::json([
                'success' => false,
                'message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง',
            ], 401);
        }

        if (in_array($user['status'], ['suspended', 'banned', 'deleted'], true)) {
            Response::json([
                'success' => false,
                'message' => 'บัญชีนี้ไม่สามารถเข้าใช้งานได้',
            ], 403);
        }

        $_SESSION['user_id'] = $user['id'];
        $this->users->touchLastSeen((int) $user['id']);

        Response::json([
            'success' => true,
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'data' => ['user' => $this->users->findById((int) $user['id'])],
        ]);
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();

        Response::json([
            'success' => true,
            'message' => 'ออกจากระบบสำเร็จ',
        ]);
    }

    public function me(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            Response::json([
                'success' => false,
                'message' => 'ยังไม่ได้เข้าสู่ระบบ',
            ], 401);
        }

        $user = $this->users->findById($userId);

        if (!$user) {
            Response::json([
                'success' => false,
                'message' => 'ไม่พบผู้ใช้',
            ], 404);
        }

        $this->users->touchLastSeen($userId);
        $user = $this->users->findById($userId);

        Response::json([
            'success' => true,
            'data' => ['user' => $user],
        ]);
    }
}
