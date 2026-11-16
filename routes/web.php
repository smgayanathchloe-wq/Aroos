<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PledgeReportController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\CrewController;
use App\Http\Controllers\FloorPlanController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\InspirationController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MemoryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\Marketplace\AdminMarketplaceController;
use App\Http\Controllers\Marketplace\MarketplaceController;
use App\Http\Controllers\Marketplace\NotificationController;
use App\Http\Controllers\Marketplace\ProviderCatalogController;
use App\Http\Controllers\Marketplace\ProviderOnboardingController;
use App\Http\Controllers\Marketplace\ProviderWorkController;
use App\Http\Controllers\Marketplace\WeddingServicesController;
use App\Http\Controllers\OutreachController;
use App\Http\Controllers\Webhooks\ClickPesaWebhookController;
use App\Http\Controllers\Webhooks\VoiceWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PledgeController;
use App\Http\Controllers\PledgeImportController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SeatingController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\WeddingController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::post('/contact', [LandingController::class, 'contact'])->name('landing.contact');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Public wedding site + RSVP + invitation card + QR check-in
Route::get('/w/{slug}', [PublicSiteController::class, 'site'])->name('public.site');
Route::get('/w/{slug}/rsvp/{token}', [PublicSiteController::class, 'rsvp'])->name('public.rsvp');
Route::post('/w/{slug}/rsvp/{token}', [PublicSiteController::class, 'submitRsvp'])->name('public.rsvp.submit');
Route::get('/w/{slug}/invitation/{token}', [PublicSiteController::class, 'invitation'])->name('public.invitation');
Route::get('/w/{slug}/checkin/{token}', [CheckinController::class, 'scan'])->name('public.checkin.scan');
Route::post('/w/{slug}/checkin/{token}', [CheckinController::class, 'confirm'])->name('public.checkin.confirm');

// Service provider marketplace: public marketing page (no listings)
Route::get('/service-providers', [MarketplaceController::class, 'landing'])->name('marketplace.landing');

// Provider webhooks (signature-verified in the controllers; CSRF-exempt)
Route::prefix('/webhooks')->middleware('throttle:600,1')->group(function () {
    Route::get('/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
    Route::post('/whatsapp', [WhatsAppWebhookController::class, 'cloud'])->name('webhooks.whatsapp.cloud');
    Route::post('/twilio/messaging', [WhatsAppWebhookController::class, 'twilio'])->name('webhooks.twilio.messaging');
    Route::post('/voice/{conversation}/answer', [VoiceWebhookController::class, 'answer'])->name('webhooks.voice.answer');
    Route::post('/voice/{conversation}/turn', [VoiceWebhookController::class, 'turn'])->name('webhooks.voice.turn');
    Route::post('/voice/{conversation}/status', [VoiceWebhookController::class, 'status'])->name('webhooks.voice.status');
    Route::post('/clickpesa', ClickPesaWebhookController::class)->name('webhooks.clickpesa');
});
// Synthesised call audio for the telephony provider to play (unguessable names, deleted after a day)
Route::get('/outreach/audio/{file}', function (string $file) {
    abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists('outreach-audio/'.$file), 404);

    return \Illuminate\Support\Facades\Storage::disk('local')->response('outreach-audio/'.$file);
})->where('file', '[0-9a-f-]{36}\.(mp3|wav)')->name('outreach.audio');

// Authenticated app
Route::middleware('auth')->group(function () {
    // Super admin panel
    Route::middleware('super.admin')->prefix('/admin')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('admin.index');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
        Route::put('/users/{user}/role', [AdminController::class, 'updateRole'])->name('admin.users.role');
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');
        Route::get('/settings', [AdminController::class, 'settings'])->name('admin.settings');
        Route::post('/settings', [AdminController::class, 'saveSettings'])->name('admin.settings.save');
        Route::post('/settings/test-sms', [AdminController::class, 'testSms'])->name('admin.settings.test-sms');
        Route::get('/requests', [AdminController::class, 'requests'])->name('admin.requests');
        Route::put('/requests/{contactRequest}', [AdminController::class, 'updateRequest'])->name('admin.requests.update');

        // Packages management
        Route::get('/packages', [PackageController::class, 'index'])->name('admin.packages.index');
        Route::get('/packages/create', [PackageController::class, 'create'])->name('admin.packages.create');
        Route::post('/packages', [PackageController::class, 'store'])->name('admin.packages.store');
        Route::get('/packages/{package}/edit', [PackageController::class, 'edit'])->name('admin.packages.edit');
        Route::put('/packages/{package}', [PackageController::class, 'update'])->name('admin.packages.update');
        Route::delete('/packages/{package}', [PackageController::class, 'destroy'])->name('admin.packages.destroy');

        // Marketplace administration
        Route::get('/marketplace', [AdminMarketplaceController::class, 'index'])->name('admin.marketplace.index');
        Route::get('/marketplace/providers/{provider}', [AdminMarketplaceController::class, 'show'])->name('admin.marketplace.provider')->withTrashed();
        Route::post('/marketplace/providers/{provider}/review', [AdminMarketplaceController::class, 'review'])->name('admin.marketplace.review');
        Route::post('/marketplace/providers/{provider}/ban', [AdminMarketplaceController::class, 'ban'])->name('admin.marketplace.ban');
        Route::post('/marketplace/providers/{provider}/unban', [AdminMarketplaceController::class, 'unban'])->name('admin.marketplace.unban');
        Route::post('/marketplace/media/{media}', [AdminMarketplaceController::class, 'moderateMedia'])->name('admin.marketplace.media');
        Route::get('/marketplace/categories', [AdminMarketplaceController::class, 'categories'])->name('admin.marketplace.categories');
        Route::post('/marketplace/categories', [AdminMarketplaceController::class, 'storeCategory'])->name('admin.marketplace.categories.store');
        Route::put('/marketplace/categories/{category}', [AdminMarketplaceController::class, 'updateCategory'])->name('admin.marketplace.categories.update');

        // Analytics
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('admin.analytics.index');
        Route::get('/analytics/wedding/{wedding}', [AnalyticsController::class, 'wedding'])->name('admin.analytics.wedding');
        Route::delete('/analytics/wedding/{wedding}', [AnalyticsController::class, 'deleteWedding'])->name('admin.wedding.delete');
    });

    // Marketplace: discovery (any signed-in user)
    Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');
    Route::get('/marketplace/saved', [MarketplaceController::class, 'favourites'])->name('marketplace.favourites');
    Route::get('/marketplace/providers/{provider}', [MarketplaceController::class, 'show'])->name('marketplace.provider');
    Route::post('/marketplace/providers/{provider}/favourite', [MarketplaceController::class, 'favourite'])->name('marketplace.favourite');
    Route::post('/marketplace/providers/{provider}/requests', [MarketplaceController::class, 'enquire'])
        ->middleware('throttle:20,60')->name('marketplace.enquire');

    // Marketplace: the wedding's requests and bookings. Outside wedding.access on
    // purpose: couples (read-only elsewhere) may request and book services.
    Route::prefix('/weddings/{wedding}/services')->group(function () {
        Route::get('/', [WeddingServicesController::class, 'index'])->name('services.index');
        Route::get('/compare', [WeddingServicesController::class, 'compare'])->name('services.compare');
        Route::get('/requests/{enquiry}', [WeddingServicesController::class, 'enquiry'])->name('services.enquiry');
        Route::post('/requests/{enquiry}/counter', [WeddingServicesController::class, 'counter'])->middleware('throttle:30,60')->name('services.counter');
        Route::post('/requests/{enquiry}/accept', [WeddingServicesController::class, 'accept'])->name('services.accept');
        Route::post('/requests/{enquiry}/message', [WeddingServicesController::class, 'message'])->middleware('throttle:60,60')->name('services.message');
        Route::post('/requests/{enquiry}/withdraw', [WeddingServicesController::class, 'withdraw'])->name('services.withdraw');
        Route::post('/bookings/{booking}/cancel', [WeddingServicesController::class, 'cancelBooking'])->name('services.bookings.cancel');
    });

    // Marketplace: provider business area
    Route::prefix('/provider')->group(function () {
        Route::get('/', [ProviderOnboardingController::class, 'dashboard'])->name('provider.dashboard');
        Route::get('/register', [ProviderOnboardingController::class, 'create'])->name('provider.register');
        Route::post('/register', [ProviderOnboardingController::class, 'store'])->name('provider.register.store');
        Route::get('/profile', [ProviderOnboardingController::class, 'edit'])->name('provider.profile');
        Route::put('/profile', [ProviderOnboardingController::class, 'update'])->name('provider.profile.update');
        Route::post('/visibility', [ProviderOnboardingController::class, 'visibility'])->name('provider.visibility');
        Route::get('/verification', [ProviderOnboardingController::class, 'verification'])->name('provider.verification');
        Route::post('/verification', [ProviderOnboardingController::class, 'submitVerification'])->name('provider.verification.submit');
        Route::get('/verification/documents/{document}', [ProviderOnboardingController::class, 'document'])->name('provider.verification.document');

        Route::get('/services', [ProviderCatalogController::class, 'services'])->name('provider.services');
        Route::post('/services', [ProviderCatalogController::class, 'storeService'])->name('provider.services.store');
        Route::get('/services/{service}', [ProviderCatalogController::class, 'editService'])->name('provider.services.edit');
        Route::put('/services/{service}', [ProviderCatalogController::class, 'updateService'])->name('provider.services.update');
        Route::delete('/services/{service}', [ProviderCatalogController::class, 'archiveService'])->name('provider.services.archive');
        Route::post('/services/{service}/packages', [ProviderCatalogController::class, 'storePackage'])->name('provider.packages.store');
        Route::put('/packages/{package}', [ProviderCatalogController::class, 'updatePackage'])->name('provider.packages.update');
        Route::post('/packages/{package}/duplicate', [ProviderCatalogController::class, 'duplicatePackage'])->name('provider.packages.duplicate');
        Route::delete('/packages/{package}', [ProviderCatalogController::class, 'archivePackage'])->name('provider.packages.archive');
        Route::get('/portfolio', [ProviderCatalogController::class, 'portfolio'])->name('provider.portfolio');
        Route::post('/portfolio', [ProviderCatalogController::class, 'storeMedia'])->name('provider.media.store');
        Route::put('/portfolio/{media}', [ProviderCatalogController::class, 'updateMedia'])->name('provider.media.update');
        Route::delete('/portfolio/{media}', [ProviderCatalogController::class, 'destroyMedia'])->name('provider.media.destroy');

        Route::get('/requests', [ProviderWorkController::class, 'enquiries'])->name('provider.enquiries');
        Route::get('/requests/{enquiry}', [ProviderWorkController::class, 'enquiry'])->name('provider.enquiries.show');
        Route::post('/requests/{enquiry}/quote', [ProviderWorkController::class, 'quote'])->name('provider.enquiries.quote');
        Route::post('/requests/{enquiry}/message', [ProviderWorkController::class, 'message'])->middleware('throttle:60,60')->name('provider.enquiries.message');
        Route::post('/requests/{enquiry}/decline', [ProviderWorkController::class, 'decline'])->name('provider.enquiries.decline');
        Route::get('/bookings', [ProviderWorkController::class, 'bookings'])->name('provider.bookings');
        Route::post('/bookings/{booking}/complete', [ProviderWorkController::class, 'completeBooking'])->name('provider.bookings.complete');
        Route::post('/bookings/{booking}/cancel', [ProviderWorkController::class, 'cancelBooking'])->name('provider.bookings.cancel');
        Route::get('/calendar', [ProviderWorkController::class, 'calendar'])->name('provider.calendar');
        Route::post('/calendar/blocks', [ProviderWorkController::class, 'storeBlackout'])->name('provider.blackouts.store');
        Route::delete('/calendar/blocks/{blackout}', [ProviderWorkController::class, 'destroyBlackout'])->name('provider.blackouts.destroy');
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

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
        Route::get('/guests/print', [GuestController::class, 'printList'])->name('guests.print');

        // Seating
        Route::get('/seating', [SeatingController::class, 'index'])->name('seating.index');
        Route::post('/seating', [SeatingController::class, 'store'])->name('seating.store');
        Route::put('/seating/{table}', [SeatingController::class, 'update'])->name('seating.update');
        Route::delete('/seating/{table}', [SeatingController::class, 'destroy'])->name('seating.destroy');
        Route::post('/seating/assign', [SeatingController::class, 'assign'])->name('seating.assign');
        Route::get('/seating/print', [SeatingController::class, 'printChart'])->name('seating.print');

        // Floor plan (2D)
        Route::get('/floorplan', [FloorPlanController::class, 'index'])->name('floorplan.index');
        Route::post('/floorplan', [FloorPlanController::class, 'store'])->name('floorplan.store');
        Route::post('/floorplan/bulk', [FloorPlanController::class, 'bulkUpdate'])->name('floorplan.bulk');
        Route::delete('/floorplan/{element}', [FloorPlanController::class, 'destroy'])->name('floorplan.destroy');

        // Check-in & QR cards
        Route::get('/checkin', [CheckinController::class, 'index'])->name('checkin.index');
        Route::get('/checkin/cards', [CheckinController::class, 'cardsAll'])->name('checkin.cards');
        Route::get('/checkin/card/{guest}', [CheckinController::class, 'card'])->name('checkin.card');
        Route::post('/checkin/{guest}/manual', [CheckinController::class, 'manual'])->name('checkin.manual');
        Route::post('/checkin/{guest}/undo', [CheckinController::class, 'undo'])->name('checkin.undo');
        Route::get('/checkin/print', [CheckinController::class, 'printList'])->name('checkin.print');

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

        // Photos & albums
        Route::get('/photos', [PhotoController::class, 'index'])->name('photos.index');
        Route::post('/photos', [PhotoController::class, 'store'])->name('photos.store');
        Route::post('/photos/albums', [PhotoController::class, 'storeAlbum'])->name('photos.albums.store');
        Route::put('/photos/albums/{album}', [PhotoController::class, 'updateAlbum'])->name('photos.albums.update');
        Route::delete('/photos/albums/{album}', [PhotoController::class, 'destroyAlbum'])->name('photos.albums.destroy');
        Route::put('/photos/{photo}/move', [PhotoController::class, 'move'])->name('photos.move');
        Route::delete('/photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');

        // Memories
        Route::get('/memories', [MemoryController::class, 'index'])->name('memories.index');
        Route::delete('/memories/{memory}', [MemoryController::class, 'destroy'])->name('memories.destroy');

        // Pledges & contributions
        Route::get('/pledges', [PledgeController::class, 'index'])->name('pledges.index');
        Route::post('/pledges', [PledgeController::class, 'store'])->name('pledges.store');
        Route::put('/pledges/{pledge}', [PledgeController::class, 'update'])->name('pledges.update');
        Route::delete('/pledges/{pledge}', [PledgeController::class, 'destroy'])->name('pledges.destroy');
        Route::delete('/pledges', [PledgeController::class, 'destroyAll'])->name('pledges.destroyAll');
        Route::post('/contributions', [PledgeController::class, 'storeContribution'])->name('contributions.store');
        Route::delete('/contributions/{contribution}', [PledgeController::class, 'destroyContribution'])->name('contributions.destroy');
        // Spreadsheet import (upload -> preview -> commit)
        Route::get('/pledges/import', [PledgeImportController::class, 'index'])->name('pledges.import.index');
        Route::post('/pledges/import/preview', [PledgeImportController::class, 'preview'])->name('pledges.import.preview');
        Route::post('/pledges/import', [PledgeImportController::class, 'store'])->name('pledges.import.store');
        Route::get('/pledges/import/template', [PledgeImportController::class, 'template'])->name('pledges.import.template');

        Route::post('/pledges/{pledge}/clickpesa', [PledgeController::class, 'requestClickPesa'])->name('pledges.clickpesa');

        // AI pledge outreach
        Route::get('/outreach', [OutreachController::class, 'index'])->name('outreach.index');
        Route::post('/outreach/pledges/{pledge}/contact', [OutreachController::class, 'contact'])->name('outreach.contact');
        Route::post('/outreach/campaign', [OutreachController::class, 'campaign'])->name('outreach.campaign');
        Route::post('/outreach/consent', [OutreachController::class, 'consent'])->name('outreach.consent');
        Route::get('/outreach/settings', [OutreachController::class, 'settings'])->name('outreach.settings');
        Route::put('/outreach/settings', [OutreachController::class, 'updateSettings'])->name('outreach.settings.update');
        Route::put('/outreach/secrets', [OutreachController::class, 'updateSecrets'])->name('outreach.secrets.update');
        Route::get('/outreach/conversations', [OutreachController::class, 'conversations'])->name('outreach.conversations');
        Route::get('/outreach/conversations/{conversation}', [OutreachController::class, 'conversation'])->name('outreach.conversation');
        Route::get('/outreach/follow-ups', [OutreachController::class, 'followUps'])->name('outreach.followups');
        Route::post('/outreach/follow-ups/{followUp}', [OutreachController::class, 'closeFollowUp'])->name('outreach.followups.close');
        Route::post('/outreach/jobs/{job}/cancel', [OutreachController::class, 'cancelJob'])->name('outreach.jobs.cancel');
        Route::post('/outreach/questions/{query}/answer', [OutreachController::class, 'answerQuestion'])->name('outreach.questions.answer');

        Route::get('/pledges/print', [PledgeController::class, 'printList'])->name('pledges.print');
        Route::get('/pledges/report/print', [PledgeReportController::class, 'print'])->name('pledges.report.print');

        // Messages / reminders
        Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');
        Route::post('/reminders', [ReminderController::class, 'store'])->name('reminders.store');
        Route::put('/reminders/{reminder}', [ReminderController::class, 'update'])->name('reminders.update');
        Route::delete('/reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');
        Route::get('/reminders/{reminder}/preview', [ReminderController::class, 'preview'])->name('reminders.preview');
        Route::post('/reminders/{reminder}/send', [ReminderController::class, 'send'])->name('reminders.send');
        Route::get('/reminders/{reminder}/logs', [ReminderController::class, 'logs'])->name('reminders.logs');

        // Collaborators
        Route::get('/collaborators', [CollaboratorController::class, 'index'])->name('collaborators.index');
        Route::post('/collaborators', [CollaboratorController::class, 'store'])->name('collaborators.store');
        Route::delete('/collaborators/{collaborator}', [CollaboratorController::class, 'destroy'])->name('collaborators.destroy');

        // Website builder
        Route::get('/website', [WebsiteController::class, 'edit'])->name('website.edit');
        Route::put('/website', [WebsiteController::class, 'update'])->name('website.update');

        // Storage management
        Route::get('/storage', [StorageController::class, 'show'])->name('storage.show');
        Route::put('/storage', [StorageController::class, 'update'])->name('storage.update');

        // Full report
        Route::get('/report', [ReportController::class, 'index'])->name('report.index');
    });
});
