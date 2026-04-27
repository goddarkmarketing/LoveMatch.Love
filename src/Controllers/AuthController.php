<?php

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Support\Request;
use App\Support\Response;
use InvalidArgumentException;
use RuntimeException;

class AuthController
{
    public function __construct(private UserRepository $users)
    {
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
