<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\EmailCheckController;
use App\Http\Controllers\Auth\ValidationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AnnonceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SiteStatsController;
use App\Http\Controllers\MedecinController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\OrganizationApiController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\ProfessionalProfileController;
use App\Http\Controllers\PharmacyApiController;
use App\Http\Controllers\PharmacyProfileController;
use App\Http\Controllers\ParapharmacyApiController;
use App\Http\Controllers\ProfileSlugController;
use App\Http\Controllers\MediaAuditController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public routes
Route::post('/login', [LoginController::class, 'login']);
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/organizations/register', [OrganizationApiController::class, 'register']);
Route::post('/check-email', [EmailCheckController::class, 'check']);
Route::post('/check-availability', [ValidationController::class, 'checkAvailability']);
Route::get('/annonces', [AnnonceController::class, 'publicIndex']);
Route::get('/annonces/{id}', [AnnonceController::class, 'show']);
// NEW: make site stats public
Route::get('/site-stats', [SiteStatsController::class, 'getStats']);
Route::post('/site-stats/bump', [SiteStatsController::class, 'bump']);

// Pharmacy routes (public - no authentication required)
Route::prefix('pharmacies')->group(function () {
    Route::get('/', [PharmacyApiController::class, 'index']);
    Route::get('/search', [PharmacyApiController::class, 'searchByCity']);
    Route::get('/{id}', [PharmacyApiController::class, 'show']);
    Route::get('/slug/{slug}', [PharmacyApiController::class, 'showBySlug']);
});

// Parapharmacy routes (public - no authentication required)
Route::prefix('parapharmacies')->group(function () {
    Route::get('/', [ParapharmacyApiController::class, 'index']);
    Route::get('/search', [ParapharmacyApiController::class, 'searchByCity']);
    Route::get('/{id}', [ParapharmacyApiController::class, 'show']);
    Route::get('/slug/{slug}', [ParapharmacyApiController::class, 'showBySlug']);
});

// Statistics routes for auto-incrementing counters
Route::get('/statistics', [StatisticsController::class, 'getStatistics']);
Route::post('/statistics/reset', [StatisticsController::class, 'resetStatistics']);

// Public search routes (no authentication required)
Route::get('/users', [UserController::class, 'publicSearch']);
Route::get('/medecins', [MedecinController::class, 'publicIndex']);
Route::get('/medecins/{id}', [MedecinController::class, 'publicShow']);
Route::get('/organizations', [OrganizationApiController::class, 'index']);
Route::get('/organizations/search', [OrganizationApiController::class, 'search']);
Route::get('/organizations/{id}', [OrganizationApiController::class, 'show']);
Route::get('/users/{id}', [UserController::class, 'publicShow']);
// Universal slug-based profile resolver (professionals + organizations)
Route::get('/profiles/slug/{slug}', [ProfileSlugController::class, 'showBySlug']);

// Universal profile endpoints - auto-detect profile type
Route::get('/profiles/{id}', [App\Http\Controllers\ProfileController::class, 'show']);
Route::get('/professionals/{id}', [App\Http\Controllers\ProfessionalController::class, 'show']);

// Clinic-specific API endpoints
Route::get('/clinics', [App\Http\Controllers\ClinicController::class, 'index']);
Route::get('/clinics/search', [App\Http\Controllers\ClinicController::class, 'searchByCity']);
Route::get('/clinics/{id}', [App\Http\Controllers\ClinicController::class, 'show']);
Route::get('/rendezvous/professional/{id}', [App\Http\Controllers\RendezVousController::class, 'getProfessionalData']);

// Pharmacy-specific API endpoints
Route::get('/pharmacies', [App\Http\Controllers\PharmacyApiController::class, 'index']);
Route::get('/pharmacies/{id}', [App\Http\Controllers\PharmacyApiController::class, 'show']);
Route::get('/pharmacies/search/city', [App\Http\Controllers\PharmacyApiController::class, 'searchByCity']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // User info
    Route::get('/user', [UserController::class, 'user']);
    
    // User profile (for patients only)
    Route::get('/user/profile', [UserController::class, 'profile']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::post('/user/profile/update', [UserController::class, 'updateProfile']); // <-- ADD THIS LINE
    Route::post('/user/profile/update-avatar', [UserController::class, 'updateProfileAvatar']);
    // Allow authenticated users to delete their own account
    Route::delete('/user', [UserController::class, 'destroySelf']);

    // Professional and Organization profile routes
    Route::prefix('professional')->group(function () {
        Route::get('/profile', [ProfessionalProfileController::class, 'getProfile']);
        Route::post('/profile/update', [ProfessionalProfileController::class, 'updateProfile']);
        Route::put('/profile/update', [ProfessionalProfileController::class, 'updateProfile']);
        Route::post('/profile/update-image', [ProfessionalProfileController::class, 'updateProfileImage']);
        Route::post('/profile/toggle-availability', [ProfessionalProfileController::class, 'toggleAvailability']);
        Route::post('/profile/set-absence', [ProfessionalProfileController::class, 'setAbsence']);
        Route::post('/profile/toggle-vacation-mode', [ProfessionalProfileController::class, 'toggleVacationMode']);
    });

    // Pharmacy profile routes (authenticated)
    Route::prefix('pharmacy')->group(function () {
        Route::get('/profile', [PharmacyProfileController::class, 'profile']);
        Route::post('/profile/update', [PharmacyProfileController::class, 'updateProfile']);
        Route::put('/profile/update', [PharmacyProfileController::class, 'updateProfile']);
    });


    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Doctor routes
    Route::prefix('doctor')->group(function () {
        Route::get('/stats', [DashboardController::class, 'doctorStats']);
        Route::get('/appointments', [DashboardController::class, 'doctorAppointments']);
        Route::get('/appointments/{id}', [DashboardController::class, 'getAppointmentDetails']);
        Route::put('/appointments/{id}/status', [DashboardController::class, 'updateAppointmentStatus']);

        Route::get('/annonces', [AnnonceController::class, 'index']);
        Route::post('/annonces', [AnnonceController::class, 'store']);
        Route::post('/annonces/activate-all', [AnnonceController::class, 'activateAll']);
        Route::post('/annonces/deactivate-all', [AnnonceController::class, 'deactivateAll']);
        Route::get('/annonces/{id}', [AnnonceController::class, 'show']);
        Route::post('/annonces/{id}', [AnnonceController::class, 'update']);
        Route::put('/annonces/{id}/toggle-status', [AnnonceController::class, 'toggleStatus']);
        Route::delete('/annonces/{id}', [AnnonceController::class, 'destroy']);
    });

    // Patient routes
    Route::prefix('patient')->group(function () {
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::get('/appointments', [AppointmentController::class, 'index']);
    });

    // Patient Santé routes (authenticated, patients only)
    Route::prefix('patient/sante')->group(function () {
        Route::get('/', [\App\Http\Controllers\PatientSanteController::class, 'index']);
        Route::post('/section/{section}', [\App\Http\Controllers\PatientSanteController::class, 'updateSection']);

        // Vaccines
        Route::get('/vaccins/catalog', [\App\Http\Controllers\PatientSanteController::class, 'catalogVaccines']);
        Route::post('/vaccins/add', [\App\Http\Controllers\PatientSanteController::class, 'addVaccine']);
        Route::delete('/vaccins/{id}', [\App\Http\Controllers\PatientSanteController::class, 'deleteVaccine']);
        Route::post('/vaccins/none', [\App\Http\Controllers\PatientSanteController::class, 'toggleVaccinesNone']);

        // Documents
        Route::post('/documents/upload', [\App\Http\Controllers\PatientSanteController::class, 'uploadDocument']);
        Route::delete('/documents/{id}', [\App\Http\Controllers\PatientSanteController::class, 'deleteDocument']);
    });

    // Medecins
    Route::get('/medecins', [MedecinController::class, 'index']);

    // Organizations (authenticated)
    Route::put('/organizations/{id}', [OrganizationApiController::class, 'update']);

    // Secure media audit endpoint (authenticated only)
    Route::get('/media/audit', [MediaAuditController::class, 'audit'])->middleware('throttle:30,1');

    // Appointment booking routes (aliases for rendezvous)
    Route::post('/rendezvous', [AppointmentController::class, 'store']);
    Route::get('/rendezvous/{id}', [AppointmentController::class, 'show']);
    Route::get('/appointments/booked-slots/{doctorId}', [AppointmentController::class, 'getBookedSlots']); // Add alias for frontend compatibility
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
    Route::get('/rendezvous/{id}', [AppointmentController::class, 'show']); // Add alias for frontend compatibility
    Route::put('/appointments/{id}', [AppointmentController::class, 'update']);
    Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
    Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy']);
    Route::get('/doctors/{id}/available-hours', [MedecinController::class, 'availableHours']);
    
    // Stripe subscription routes (disabled in production)
    // To re-enable, uncomment the block below and ensure proper keys are set.
    // Route::prefix('stripe')->group(function () {
    //     Route::post('/create-checkout-session', [StripeController::class, 'createCheckoutSession']);
    //     Route::post('/verify-session', [StripeController::class, 'verifySession']);
    //     Route::post('/cancel-subscription', [StripeController::class, 'cancelSubscription']);
    //     Route::post('/customer-portal', [StripeController::class, 'customerPortal']);
    //     Route::get('/subscription-details/{userId}', [StripeController::class, 'getSubscriptionDetails']);
    // });

    // Admin routes
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::post('/admin/users', [UserController::class, 'store']);
    Route::put('/admin/users/{id}', [UserController::class, 'update']);
    Route::delete('/admin/users/{id}', [UserController::class, 'destroy']);
    Route::get('/roles', [UserController::class, 'roles']);
});

// Stripe webhook (disabled)
// Route::post('/stripe/webhook', [StripeController::class, 'handleWebhook']);

// Public doctor routes
Route::get('/medecins/{id}', [MedecinController::class, 'show']);
