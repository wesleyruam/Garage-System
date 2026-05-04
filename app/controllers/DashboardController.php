<?php

namespace App\Controllers;

class DashboardController
{
    public function index(): void
    {
        success_response(['status' => 'online', 'app' => config('app.name')]);
    }
}
