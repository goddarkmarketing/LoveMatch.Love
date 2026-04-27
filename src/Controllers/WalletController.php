<?php

namespace App\Controllers;

use App\Repositories\WalletRepository;
use App\Support\Response;

class WalletController
{
    public function __construct(private WalletRepository $wallets)
    {
    }

    public function show(int $userId): void
    {
        $summary = $this->wallets->getSummaryByUserId($userId);

        if (!$summary) {
            Response::json([
                'success' => false,
                'message' => 'ไม่พบกระเป๋าเหรียญ',
            ], 404);
        }

        Response::json([
            'success' => true,
            'data' => $summary,
        ]);
    }
}
