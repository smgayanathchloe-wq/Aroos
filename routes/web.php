<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\CrewController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\InspirationController;
use App\Http\Controllers\MemoryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PledgeController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SeatingController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\WeddingController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Public wedding site + RSVP + invitation card
Route::get('/w/{slug}', [PublicSiteController::class, 'site'])->name('public.site');
Route::get('/w/{slug}/rsvp/{token}', [PublicSiteController::class, 'rsvp'])->name('public.rsvp');
Route::post('/w/{slug}/rsvp/{token}', [PublicSiteController::class, 'submitRsvp'])->name('public.rsvp.submit');
Route::get('/w/{slug}/invitation/{token}', [PublicSiteController::class, 'invitation'])->name('public.invitation');

// Authenticated app
Route::middleware('auth')->group(function () {
    // Super admin panel
    Route::middleware('super.admin')->prefix('/admin')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('admin.index');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
        Route::put('/users/{user}/role', [AdminController::class, 'updateRole'])->name('admin.users.role');
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');
    });

    Route::get('/weddings', [WeddingController::class, 'index'])->name('weddings.index');
    Route::post('/weddings', [WeddingController::class, 'store'])->name('weddings.store');

    Route::middleware('wedding.access')->prefix('/weddings/{wedding}')->group(function () {
        Route::get('/', [WeddingController::class, 'dashboard'])->name('wedding.dashboard');
        Route::put('/', [WeddingController::class, 'update'])->name('wedding.update');
        Route::delete('/', [WeddingController::class, 'destroy'])->name('wedding.destroy');

        // Checklist
        Route::get('/checklist', [ChecklistController::class, 'index'])->name('checklist.index');
        Route::post('/checklist/phases', [ChecklistController::class, 'storePhase'])->name('checklist.phases.store');
        Route::put('/checklist/phases/{phase}', [ChecklistController::class, 'updatePhase'])->name('checklist.phases.update');
        Route::delete('/checklist/phases/{phase}', [ChecklistController::class, 'destroyPhase'])->name('checklist.phases.destroy');
        Route::post('/checklist/phases/{phase}/tasks', [ChecklistController::class, 'storeTask'])->name('checklist.tasks.store');
        Route::patch('/checklist/tasks/{task}/toggle', [ChecklistController::class, 'toggleTask'])->name('checklist.tasks.toggle');
        Route::delete('/checklist/tasks/{task}', [ChecklistController::class, 'destroyTask'])->name('checklist.tasks.destroy');

        // Budget
        Route::get('/budget', [BudgetController::class, 'index'])->name('budget.index');
        Route::put('/budget/total', [BudgetController::class, 'updateTotal'])->name('budget.total');
        Route::post('/budget', [BudgetController::class, 'store'])->name('budget.store');
        Route::put('/budget/{category}', [BudgetController::class, 'update'])->name('budget.update');
        Route::delete('/budget/{category}', [BudgetController::class, 'destroy'])->name('budget.destroy');

        // Guests
        Route::get('/guests', [GuestController::class, 'index'])->name('guests.index');
        Route::post('/guests', [GuestController::class, 'store'])->name('guests.store');
        Route::put('/guests/{guest}', [GuestController::class, 'update'])->name('guests.update');
        Route::delete('/guests/{guest}', [GuestController::class, 'destroy'])->name('guests.destroy');

        // Seating
        Route::get('/seating', [SeatingController::class, 'index'])->name('seating.index');
        Route::post('/seating', [SeatingController::class, 'store'])->name('seating.store');
        Route::put('/seating/{table}', [SeatingController::class, 'update'])->name('seating.update');
        Route::delete('/seating/{table}', [SeatingController::class, 'destroy'])->name('seating.destroy');
        Route::post('/seating/assign', [SeatingController::class, 'assign'])->name('seating.assign');

        // Vendors
        Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
        Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
        Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
        Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');

        // Crew
        Route::get('/crew', [CrewController::class, 'index'])->name('crew.index');
        Route::post('/crew', [CrewController::class, 'storeTeam'])->name('crew.store');
        Route::put('/crew/{team}', [CrewController::class, 'updateTeam'])->name('crew.update');
        Route::delete('/crew/{team}', [CrewController::class, 'destroyTeam'])->name('crew.destroy');
        Route::post('/crew/{team}/members', [CrewController::class, 'storeMember'])->name('crew.members.store');
        Route::delete('/crew/{team}/members/{member}', [CrewController::class, 'destroyMember'])->name('crew.members.destroy');

        // Timeline
        Route::get('/timeline', [TimelineController::class, 'index'])->name('timeline.index');
        Route::post('/timeline', [TimelineController::class, 'store'])->name('timeline.store');
        Route::put('/timeline/{event}', [TimelineController::class, 'update'])->name('timeline.update');
        Route::delete('/timeline/{event}', [TimelineController::class, 'destroy'])->name('timeline.destroy');

        // Menus
        Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
        Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');
        Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
        Route::post('/menus/{menu}/items', [MenuController::class, 'storeItem'])->name('menus.items.store');
        Route::delete('/menus/{menu}/items/{item}', [MenuController::class, 'destroyItem'])->name('menus.items.destroy');

        // Inspiration
        Route::get('/inspiration', [InspirationController::class, 'index'])->name('inspiration.index');
        Route::post('/inspiration', [InspirationController::class, 'store'])->name('inspiration.store');
        Route::delete('/inspiration/{inspiration}', [InspirationController::class, 'destroy'])->name('inspiration.destroy');

        // Photos
        Route::get('/photos', [PhotoController::class, 'index'])->name('photos.index');
        Route::post('/photos', [PhotoController::class, 'store'])->name('photos.store');
        Route::delete('/photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');

        // Memories
        Route::get('/memories', [MemoryController::class, 'index'])->name('memories.index');
        Route::delete('/memories/{memory}', [MemoryController::class, 'destroy'])->name('memories.destroy');

        // Pledges & contributions
        Route::get('/pledges', [PledgeController::class, 'index'])->name('pledges.index');
        Route::post('/pledges', [PledgeController::class, 'store'])->name('pledges.store');
        Route::put('/pledges/{pledge}', [PledgeController::class, 'update'])->name('pledges.update');
        Route::delete('/pledges/{pledge}', [PledgeController::class, 'destroy'])->name('pledges.destroy');
        Route::post('/contributions', [PledgeController::class, 'storeContribution'])->name('contributions.store');
        Route::delete('/contributions/{contribution}', [PledgeController::class, 'destroyContribution'])->name('contributions.destroy');

        // Reminders
        Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');
        Route::post('/reminders', [ReminderController::class, 'store'])->name('reminders.store');
        Route::put('/reminders/{reminder}', [ReminderController::class, 'update'])->name('reminders.update');
        Route::delete('/reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');
        Route::post('/reminders/{reminder}/send', [ReminderController::class, 'send'])->name('reminders.send');
        Route::get('/reminders/{reminder}/logs', [ReminderController::class, 'logs'])->name('reminders.logs');

        // Collaborators
        Route::get('/collaborators', [CollaboratorController::class, 'index'])->name('collaborators.index');
        Route::post('/collaborators', [CollaboratorController::class, 'store'])->name('collaborators.store');
        Route::delete('/collaborators/{collaborator}', [CollaboratorController::class, 'destroy'])->name('collaborators.destroy');

        // Website builder
        Route::get('/website', [WebsiteController::class, 'edit'])->name('website.edit');
        Route::put('/website', [WebsiteController::class, 'update'])->name('website.update');
    });
});
