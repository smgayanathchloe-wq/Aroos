<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\PlanningController;
use App\Http\Controllers\Api\ReminderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API
|--------------------------------------------------------------------------
|
| Consumed by the Aroos mobile app. A Wedding is the tenant boundary: the
| client names one with the `X-Wedding-Id` header and the API enforces the
| same access rules as the web dashboard.
|
*/

Route::prefix('v1')->group(function () {
    Route::get('health', fn () => response()->json([
        'success' => true,
        'data' => ['status' => 'ok', 'time' => now()->toIso8601String()],
    ]));

    // Sign-in is rate limited on top of the per-account throttle in the
    // controller, to blunt distributed guessing.
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        // Account
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::put('auth/profile', [AuthController::class, 'updateProfile']);
        Route::put('auth/password', [AuthController::class, 'changePassword'])
            ->middleware('throttle:6,1');

        // Home
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('report', [DashboardController::class, 'report']);

        // Guests, RSVP and check-in
        Route::get('guests/summary', [GuestController::class, 'summary']);
        // Door mode and QR scanning. Declared before guests/{id} so the
        // literal segments are not swallowed by the numeric route.
        Route::get('guests/checkin-board', [GuestController::class, 'checkInBoard']);
        Route::get('guests/by-token/{token}', [GuestController::class, 'findByToken']);
        Route::get('guests', [GuestController::class, 'index']);
        Route::post('guests', [GuestController::class, 'store']);
        Route::get('guests/{id}', [GuestController::class, 'show'])->whereNumber('id');
        Route::put('guests/{id}', [GuestController::class, 'update'])->whereNumber('id');
        Route::delete('guests/{id}', [GuestController::class, 'destroy'])->whereNumber('id');
        Route::post('guests/{id}/rsvp', [GuestController::class, 'rsvp'])->whereNumber('id');
        Route::post('guests/{id}/checkin', [GuestController::class, 'checkIn'])->whereNumber('id');
        Route::post('guests/{id}/checkin/undo', [GuestController::class, 'undoCheckIn'])->whereNumber('id');

        // Budget
        Route::get('budget', [FinanceController::class, 'budget']);
        Route::put('budget/total', [FinanceController::class, 'updateTotalBudget']);
        Route::post('budget/categories', [FinanceController::class, 'storeCategory']);
        Route::put('budget/categories/{id}', [FinanceController::class, 'updateCategory'])->whereNumber('id');
        Route::delete('budget/categories/{id}', [FinanceController::class, 'destroyCategory'])->whereNumber('id');

        // Pledges and contributions
        Route::get('pledges/summary', [FinanceController::class, 'pledgeSummary']);
        Route::get('pledges', [FinanceController::class, 'pledges']);
        Route::post('pledges', [FinanceController::class, 'storePledge']);
        Route::get('pledges/{id}', [FinanceController::class, 'showPledge'])->whereNumber('id');
        Route::put('pledges/{id}', [FinanceController::class, 'updatePledge'])->whereNumber('id');
        Route::delete('pledges/{id}', [FinanceController::class, 'destroyPledge'])->whereNumber('id');
        Route::get('contributions', [FinanceController::class, 'contributions']);
        Route::post('contributions', [FinanceController::class, 'storeContribution']);

        // Planning
        Route::get('checklist', [PlanningController::class, 'checklist']);
        Route::post('checklist/phases/{phase}/tasks', [PlanningController::class, 'storeTask'])->whereNumber('phase');
        Route::put('checklist/tasks/{task}', [PlanningController::class, 'updateTask'])->whereNumber('task');
        Route::delete('checklist/tasks/{task}', [PlanningController::class, 'destroyTask'])->whereNumber('task');

        Route::get('vendors', [PlanningController::class, 'vendors']);
        Route::post('vendors', [PlanningController::class, 'storeVendor']);
        Route::put('vendors/{id}', [PlanningController::class, 'updateVendor'])->whereNumber('id');
        Route::delete('vendors/{id}', [PlanningController::class, 'destroyVendor'])->whereNumber('id');

        Route::get('timeline', [PlanningController::class, 'timeline']);
        Route::post('timeline', [PlanningController::class, 'storeEvent']);
        Route::put('timeline/{id}', [PlanningController::class, 'updateEvent'])->whereNumber('id');
        Route::delete('timeline/{id}', [PlanningController::class, 'destroyEvent'])->whereNumber('id');

        Route::get('seating', [PlanningController::class, 'seating']);
        Route::post('seating/tables', [PlanningController::class, 'storeTable']);
        Route::delete('seating/tables/{id}', [PlanningController::class, 'destroyTable'])->whereNumber('id');
        Route::post('seating/assign', [PlanningController::class, 'assignSeat']);

        Route::get('crew', [PlanningController::class, 'crew']);
        Route::get('collaborators', [PlanningController::class, 'collaborators']);

        // Messaging: SMS and email, through the same service the web uses.
        Route::get('reminders/channels', [ReminderController::class, 'channels']);
        Route::get('reminders', [ReminderController::class, 'index']);
        Route::post('reminders', [ReminderController::class, 'store']);
        Route::post('reminders/quick-send', [ReminderController::class, 'quickSend'])
            ->middleware('throttle:30,1');
        Route::get('reminders/{id}', [ReminderController::class, 'show'])->whereNumber('id');
        Route::put('reminders/{id}', [ReminderController::class, 'update'])->whereNumber('id');
        Route::delete('reminders/{id}', [ReminderController::class, 'destroy'])->whereNumber('id');
        Route::get('reminders/{id}/preview', [ReminderController::class, 'preview'])->whereNumber('id');
        Route::get('reminders/{id}/logs', [ReminderController::class, 'logs'])->whereNumber('id');
        // Sending real SMS costs money, so throttle it harder than reads.
        Route::post('reminders/{id}/send', [ReminderController::class, 'send'])
            ->whereNumber('id')->middleware('throttle:20,1');
    });
});
