<?php

namespace App\Controllers;

use App\Repositories\AdminRepository;
use App\Support\Request;
use App\Support\Response;
use RuntimeException;

class AdminController
{
    public function __construct(private AdminRepository $admin)
    {
    }

    public function dashboard(): void
    {
        Response::json([
            'success' => true,
            'data' => $this->admin->dashboardSummary(),
        ]);
    }

    public function updateUserStatus(Request $request, int $userId): void
    {
        $payload = $request->json();
        $status = (string) ($payload['status'] ?? '');

        try {
            $user = $this->admin->updateUserStatus($userId, $status);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        Response::json([
            'success' => true,
            'message' => 'อัปเดตสถานะผู้ใช้เรียบร้อย',
            'data' => ['user' => $user],
        ]);
    }

    public function updateReportStatus(Request $request, int $reportId, int $reviewedBy): void
    {
        $payload = $request->json();
        $status = (string) ($payload['status'] ?? '');

        try {
            $report = $this->admin->updateReportStatus($reportId, $status, $reviewedBy);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        Response::json([
            'success' => true,
            'message' => 'อัปเดตรายงานเรียบร้อย',
            'data' => ['report' => $report],
        ]);
    }
}
