<?php

namespace App\Controllers;

use App\Repositories\DiscoverRepository;
use App\Support\Response;

class DiscoverController
{
    public function __construct(private DiscoverRepository $discover)
    {
    }

    public function index(?int $userId): void
    {
        Response::json([
            'success' => true,
            'data' => [
                'profiles' => $this->discover->discover($userId),
            ],
        ]);
    }
}
