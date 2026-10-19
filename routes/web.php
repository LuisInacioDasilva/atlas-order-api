<?php

use App\Models\Order;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    abort_unless(app()->environment('local'), 404);

    return view('dashboard', [
        'orders' => Order::query()->latest()->limit(12)->get(),
        'totals' => Order::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
    ]);
});
