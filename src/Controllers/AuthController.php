<?php

namespace App\Controllers;

use App\Repositories\PaymentRepository;
use App\Repositories\SubscriptionRepository;
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
        private SubscriptionRepository $subscriptions,
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
        $planId = (int) ($payload['plan_id'] ?? 0);
        $paymentMethod = trim((string) ($payload['payment_method'] ?? 'bank_transfer'));

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

        if ($planId <= 0) {
            Response::json([
                'success' => false,
                'message' => 'กรุณาเลือกแพ็กเกจสมาชิก (plan_id)',
            ], 422);
        }

        $plan = $this->subscriptions->findPlanById($planId);
        if (!$plan) {
            Response::json([
                'success' => false,
                'message' => 'ไม่พบแพ็กเกจที่เลือก',
            ], 422);
        }

        $price = (float) $plan['price_thb'];
        $planSummary = [
            'id' => $planId,
            'name' => (string) $plan['name_th'],
            'tier' => (string) $plan['tier'],
            'price_thb' => $price,
        ];

        if ($this->users->findByEmail($email)) {
            Response::json([
                'success' => false,
                'message' => 'อีเมลนี้ถูกใช้งานแล้ว',
            ], 409);
        }

        if ($price > 0) {
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

        if ($price <= 0) {
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
                $this->subscriptions->insertSubscriptionForNewUser((int) $user['id'], $planId, 'active');
            } catch (RuntimeException $exception) {
                Response::json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 500);
            }

            $_SESSION['user_id'] = $user['id'];
            $fresh = $this->users->findById((int) $user['id']);

            Response::json([
                'success' => true,
                'message' => 'สมัครสมาชิกแพ็กเกจ ' . $plan['name_th'] . ' สำเร็จ',
                'data' => [
                    'user' => $fresh,
                    'selected_plan' => $planSummary,
                ],
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
        $subscriptionId = 0;
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
            $subscriptionId = $this->subscriptions->insertSubscriptionForNewUser($userId, $planId, 'pending');

            if ($paymentMethod === 'credit_card') {
                $omise = new OmiseClient();
                $satang = (int) round($price * 100);
                $result = $omise->createCharge(
                    (string) $this->paymentConfig['omise_secret_key'],
                    $satang,
                    $omiseToken,
                    [
                        'email' => mb_strtolower($email),
                        'purpose' => 'subscription_signup',
                        'plan_id' => (string) $planId,
                        'plan_code' => (string) $plan['code'],
                    ]
                );

                if (!$result['ok']) {
                    $this->pdo->rollBack();
                    Response::json([
                        'success' => false,
                        'message' => $result['message'] ?? 'ชำระเงินด้วยบัตรไม่สำเร็จ',
                    ], 402);
                }

                $this->payments->createSubscriptionPayment(
                    $userId,
                    $subscriptionId,
                    'credit_card',
                    $price,
                    $result['charge_id'] ?? null,
                    'paid'
                );
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));
                $this->subscriptions->updateSubscriptionStatus($subscriptionId, 'active', $expiresAt);
                $this->users->updateStatus($userId, 'active');
            } else {
                $this->payments->createSubscriptionPayment(
                    $userId,
                    $subscriptionId,
                    'bank_transfer',
                    $price,
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

        $data = [
            'user' => $fresh,
            'selected_plan' => $planSummary,
        ];
        if ($paymentMethod === 'bank_transfer') {
            $banks = $this->normalizedBankAccountsForPayment();
            $first = $banks[0] ?? null;
            $data['registration_payment'] = [
                'status' => 'pending',
                'amount_thb' => $price,
                'plan_name' => (string) $plan['name_th'],
                'bank_account_name' => (string) ($this->paymentConfig['bank_account_name'] ?? ''),
                'transfer_reference_note' => (string) ($this->paymentConfig['transfer_reference_note'] ?? ''),
                'bank_accounts' => $banks,
                'bank_name' => $first ? $first['name_th'] : (string) ($this->paymentConfig['bank_name'] ?? ''),
                'bank_account_number' => $first ? $first['account_number'] : (string) ($this->paymentConfig['bank_account_number'] ?? ''),
            ];
        }

        Response::json([
            'success' => true,
            'message' => $paymentMethod === 'bank_transfer'
                ? 'สมัครสมาชิกสำเร็จ กรุณาโอนค่าแพ็กเกจ ' . $plan['name_th'] . ' ตามข้อมูลด้านล่าง'
                : 'สมัครสมาชิกและชำระค่าแพ็กเกจ ' . $plan['name_th'] . ' สำเร็จ',
            'data' => $data,
        ], 201);
    }

    /**
     * @return list<array{code: string, name_th: string, account_number: string, logo: string, type: string}>
     */
    private function normalizedBankAccountsForPayment(): array
    {
        $cfg = $this->paymentConfig;
        if (!empty($cfg['bank_accounts']) && is_array($cfg['bank_accounts'])) {
            $out = [];
            foreach ($cfg['bank_accounts'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $out[] = [
                    'code' => (string) ($row['code'] ?? ''),
                    'name_th' => (string) ($row['name_th'] ?? ''),
                    'account_number' => (string) ($row['account_number'] ?? ''),
                    'logo' => (string) ($row['logo'] ?? ''),
                    'type' => (string) ($row['type'] ?? 'bank'),
                ];
            }

            return $out;
        }

        return [[
            'code' => 'default',
            'name_th' => (string) ($cfg['bank_name'] ?? ''),
            'account_number' => (string) ($cfg['bank_account_number'] ?? ''),
            'logo' => '',
            'type' => 'bank',
        ]];
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

    public function createQrLoginSession(): void
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 300);

        $this->expireOldQrLoginSessions();

        $statement = $this->pdo->prepare(
            'INSERT INTO qr_login_sessions (
                token_hash, status, requester_ip, requester_user_agent, expires_at, created_at, updated_at
             ) VALUES (
                :token_hash, "pending", :requester_ip, :requester_user_agent, :expires_at, NOW(), NOW()
             )'
        );
        $statement->execute([
            'token_hash' => $tokenHash,
            'requester_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'requester_user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'expires_at' => $expiresAt,
        ]);

        Response::json([
            'success' => true,
            'message' => 'สร้าง QR เข้าสู่ระบบแล้ว',
            'data' => [
                'token' => $token,
                'expires_at' => $expiresAt,
            ],
        ], 201);
    }

    public function qrLoginStatus(string $token): void
    {
        $session = $this->findQrLoginSession($token);
        if (!$session) {
            Response::json([
                'success' => false,
                'message' => 'QR เข้าสู่ระบบไม่ถูกต้อง',
            ], 404);
        }

        if ($this->isQrLoginExpired($session)) {
            $this->markQrLoginSession((int) $session['id'], 'expired');
            Response::json([
                'success' => true,
                'data' => ['status' => 'expired'],
            ]);
        }

        if ($session['status'] === 'approved' && !empty($session['approved_user_id'])) {
            $userId = (int) $session['approved_user_id'];
            $_SESSION['user_id'] = $userId;
            $this->users->touchLastSeen($userId);

            $update = $this->pdo->prepare(
                'UPDATE qr_login_sessions
                 SET status = "consumed", consumed_at = NOW(), updated_at = NOW()
                 WHERE id = :id AND status = "approved"'
            );
            $update->execute(['id' => (int) $session['id']]);

            Response::json([
                'success' => true,
                'message' => 'เข้าสู่ระบบด้วย QR สำเร็จ',
                'data' => [
                    'status' => 'consumed',
                    'user' => $this->users->findById($userId),
                ],
            ]);
        }

        Response::json([
            'success' => true,
            'data' => [
                'status' => $session['status'],
            ],
        ]);
    }

    public function approveQrLoginSession(string $token): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            Response::json([
                'success' => false,
                'message' => 'กรุณาเข้าสู่ระบบบนมือถือก่อนอนุมัติ QR',
            ], 401);
        }

        $session = $this->findQrLoginSession($token);
        if (!$session || $session['status'] !== 'pending') {
            Response::json([
                'success' => false,
                'message' => 'QR นี้ไม่พร้อมใช้งานแล้ว',
            ], 422);
        }

        if ($this->isQrLoginExpired($session)) {
            $this->markQrLoginSession((int) $session['id'], 'expired');
            Response::json([
                'success' => false,
                'message' => 'QR หมดอายุแล้ว โปรดสร้างใหม่',
            ], 410);
        }

        $update = $this->pdo->prepare(
            'UPDATE qr_login_sessions
             SET status = "approved",
                 approved_user_id = :approved_user_id,
                 approved_ip = :approved_ip,
                 approved_user_agent = :approved_user_agent,
                 approved_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id AND status = "pending"'
        );
        $update->execute([
            'approved_user_id' => $userId,
            'approved_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'approved_user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'id' => (int) $session['id'],
        ]);

        Response::json([
            'success' => true,
            'message' => 'อนุมัติ QR เข้าสู่ระบบแล้ว',
            'data' => [
                'status' => 'approved',
            ],
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

    private function findQrLoginSession(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM qr_login_sessions
             WHERE token_hash = :token_hash
             LIMIT 1'
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    private function isQrLoginExpired(array $session): bool
    {
        return strtotime((string) $session['expires_at']) <= time();
    }

    private function markQrLoginSession(int $sessionId, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE qr_login_sessions
             SET status = :status, updated_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'id' => $sessionId,
        ]);
    }

    private function expireOldQrLoginSessions(): void
    {
        $this->pdo->exec(
            'UPDATE qr_login_sessions
             SET status = "expired", updated_at = NOW()
             WHERE status IN ("pending", "approved") AND expires_at < NOW()'
        );
    }
}
