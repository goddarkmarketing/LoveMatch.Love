<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\AppDataController;
use App\Controllers\AdminController;
use App\Controllers\ChatController;
use App\Controllers\DiscoverController;
use App\Controllers\GiftController;
use App\Controllers\MatchController;
use App\Controllers\SubscriptionController;
use App\Controllers\WalletController;
use App\Repositories\AppDataRepository;
use App\Repositories\AdminRepository;
use App\Repositories\ChatRepository;
use App\Repositories\DiscoverRepository;
use App\Repositories\GiftRepository;
use App\Repositories\MatchRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\UserRepository;
use App\Repositories\WalletRepository;
use App\Support\Database;
use App\Support\Request;
use App\Support\Response;

require_once dirname(__DIR__) . '/src/Support/Database.php';
require_once dirname(__DIR__) . '/src/Support/Request.php';
require_once dirname(__DIR__) . '/src/Support/Response.php';
require_once dirname(__DIR__) . '/src/Repositories/UserRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/PaymentRepository.php';
require_once dirname(__DIR__) . '/src/Support/OmiseClient.php';
require_once dirname(__DIR__) . '/src/Repositories/AppDataRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/AdminRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/ChatRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/DiscoverRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/GiftRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/MatchRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/WalletRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/SubscriptionRepository.php';
require_once dirname(__DIR__) . '/src/Controllers/AuthController.php';
require_once dirname(__DIR__) . '/src/Controllers/AppDataController.php';
require_once dirname(__DIR__) . '/src/Controllers/AdminController.php';
require_once dirname(__DIR__) . '/src/Controllers/ChatController.php';
require_once dirname(__DIR__) . '/src/Controllers/DiscoverController.php';
require_once dirname(__DIR__) . '/src/Controllers/GiftController.php';
require_once dirname(__DIR__) . '/src/Controllers/MatchController.php';
require_once dirname(__DIR__) . '/src/Controllers/WalletController.php';
require_once dirname(__DIR__) . '/src/Controllers/SubscriptionController.php';

$appConfig = require dirname(__DIR__) . '/config/app.php';

session_name($appConfig['session_name']);
session_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$request = new Request();

try {
    $db = Database::connection();
    $paymentConfig = require dirname(__DIR__) . '/config/payments.php';
    $authController = new AuthController(
        new UserRepository($db),
        new PaymentRepository($db),
        $paymentConfig,
        $db
    );
    $appDataController = new AppDataController(new AppDataRepository($db), $paymentConfig);
    $adminController = new AdminController(new AdminRepository($db));
    $chatController = new ChatController(new ChatRepository($db), new MatchRepository($db));
    $discoverController = new DiscoverController(new DiscoverRepository($db));
    $matchController = new MatchController(new MatchRepository($db));
    $walletController = new WalletController(new WalletRepository($db));
    $giftController = new GiftController(new GiftRepository($db), new WalletRepository($db));
    $subscriptionController = new SubscriptionController(new SubscriptionRepository($db));
} catch (Throwable $exception) {
    Response::json([
        'success' => false,
        'message' => $exception->getMessage(),
    ], 500);
}

$method = $request->method();
$path = $request->path();

$routes = [
    ['GET', '#^/auth/me$#', static fn (Request $request): mixed => $authController->me()],
    ['GET', '#^/gifts$#', static fn (Request $request): mixed => $appDataController->gifts()],
    ['GET', '#^/subscription/plans$#', static fn (Request $request): mixed => $appDataController->subscriptionPlans()],
    ['GET', '#^/subscription/current$#', static function (Request $request) use ($subscriptionController): mixed {
        $userId = requireAuthUserId();
        return $subscriptionController->current($userId);
    }],
    ['GET', '#^/admin/dashboard$#', static function (Request $request) use ($adminController, $authController): mixed {
        requireAdminUser($authController);
        return $adminController->dashboard();
    }],
    ['GET', '#^/chat/rooms$#', static fn (Request $request): mixed => $appDataController->chatRooms()],
    ['GET', '#^/chat/sidebar$#', static function (Request $request) use ($chatController): mixed {
        $userId = requireAuthUserId();
        return $chatController->sidebar($userId);
    }],
    ['GET', '#^/chat/rooms/(\d+)/members$#', static fn (Request $request, string $roomId): mixed => $chatController->members((int) $roomId)],
    ['GET', '#^/chat/rooms/(\d+)/messages$#', static fn (Request $request, string $roomId): mixed => $chatController->messages((int) $roomId)],
    ['GET', '#^/discover$#', static function (Request $request) use ($discoverController): mixed {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        return $discoverController->index($userId > 0 ? $userId : null);
    }],
    ['GET', '#^/matches$#', static function (Request $request) use ($matchController): mixed {
        $userId = requireAuthUserId();
        return $matchController->index($userId);
    }],
    ['GET', '#^/wallet$#', static function (Request $request) use ($walletController): mixed {
        $userId = requireAuthUserId();
        return $walletController->show($userId);
    }],
    ['GET', '#^/health$#', static fn (Request $request): mixed => Response::json([
        'success' => true,
        'message' => 'API is running',
    ])],
    ['GET', '#^/payments/registration-options$#', static fn (Request $request): mixed => $appDataController->registrationPaymentOptions()],
    ['POST', '#^/auth/register$#', static fn (Request $request): mixed => $authController->register($request)],
    ['POST', '#^/auth/login$#', static fn (Request $request): mixed => $authController->login($request)],
    ['POST', '#^/auth/logout$#', static fn (Request $request): mixed => $authController->logout()],
    ['POST', '#^/chat/rooms/(\d+)/messages$#', static function (Request $request, string $roomId) use ($chatController): mixed {
        $userId = requireAuthUserId();
        return $chatController->sendMessage($request, (int) $roomId, $userId);
    }],
    ['POST', '#^/chat/private/start$#', static function (Request $request) use ($chatController): mixed {
        $userId = requireAuthUserId();
        return $chatController->startPrivate($request, $userId);
    }],
    ['POST', '#^/chat/rooms/(\d+)/read$#', static function (Request $request, string $roomId) use ($chatController): mixed {
        $userId = requireAuthUserId();
        return $chatController->markRead($request, (int) $roomId, $userId);
    }],
    ['POST', '#^/chat/report$#', static function (Request $request) use ($chatController): mixed {
        $userId = requireAuthUserId();
        return $chatController->report($request, $userId);
    }],
    ['POST', '#^/chat/block$#', static function (Request $request) use ($chatController): mixed {
        $userId = requireAuthUserId();
        return $chatController->block($request, $userId);
    }],
    ['POST', '#^/gifts/send$#', static function (Request $request) use ($giftController): mixed {
        $userId = requireAuthUserId();
        return $giftController->send($request, $userId);
    }],
    ['POST', '#^/subscription/checkout$#', static function (Request $request) use ($subscriptionController): mixed {
        $userId = requireAuthUserId();
        return $subscriptionController->checkout($request, $userId);
    }],
    ['POST', '#^/admin/users/(\d+)/status$#', static function (Request $request, string $targetUserId) use ($adminController, $authController): mixed {
        requireAdminUser($authController);
        return $adminController->updateUserStatus($request, (int) $targetUserId);
    }],
    ['POST', '#^/admin/reports/(\d+)/status$#', static function (Request $request, string $reportId) use ($adminController, $authController): mixed {
        $adminUser = requireAdminUser($authController);
        return $adminController->updateReportStatus($request, (int) $reportId, (int) $adminUser['id']);
    }],
    ['POST', '#^/swipes$#', static function (Request $request) use ($matchController): mixed {
        $userId = requireAuthUserId();
        return $matchController->swipe($request, $userId);
    }],
];

$matched = false;

foreach ($routes as [$routeMethod, $pattern, $handler]) {
    if ($routeMethod !== $method) {
        continue;
    }

    if (!preg_match($pattern, $path, $matches)) {
        continue;
    }

    $matched = true;
    array_shift($matches);
    $handler($request, ...$matches);
    exit;
}

if (!$matched) {
    Response::json([
        'success' => false,
        'message' => 'Route not found',
        'path' => $path,
        'method' => $method,
    ], 404);
}

function requireAuthUserId(): int
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        Response::json([
            'success' => false,
            'message' => 'กรุณาเข้าสู่ระบบก่อน',
        ], 401);
    }

    return $userId;
}

function requireAdminUser(AuthController $authController): array
{
    $userId = requireAuthUserId();
    $user = $authController->userRecord($userId);

    if (!$user || !in_array($user['role_code'], ['admin', 'moderator'], true)) {
        Response::json([
            'success' => false,
            'message' => 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการระบบ',
        ], 403);
    }

    return $user;
}
