<?php

use App\Http\Controllers\Api\AccessController;
use App\Http\Controllers\Api\Admin\LandingSettingsController as AdminLandingSettingsController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Billing\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Api\Billing\Admin\PriceController as AdminPriceController;
use App\Http\Controllers\Api\Billing\Public\PlanController;
use App\Http\Controllers\Api\Billing\Tenant\PaymentController as BillingPaymentController;
use App\Http\Controllers\Api\Billing\Tenant\PaymentMethodController as BillingPaymentMethodController;
use App\Http\Controllers\Api\Billing\Tenant\SubscriptionController as BillingSubscriptionController;
use App\Http\Controllers\Api\ContentPipelineController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\LandingSettingsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ScheduledPostController;
use App\Http\Controllers\Api\SocialAccountController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkspaceAvatarController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\Api\YouTubeCategoryController;
use App\Http\Controllers\Api\YouTubePrivacyStatusController;
use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| Autenticação Pública
|--------------------------------------------------------------------------
*/

Route::controller(AuthController::class)->group(function () {
    Route::post('auth/signup', 'signup')->middleware('throttle:3,1');
    Route::post('auth/forgot-password', 'forgotPassword')->middleware('throttle:3,1');
    Route::post('auth/password-reset', 'passwordReset')->middleware('throttle:3,1');
    Route::post('login', 'login')->middleware('throttle:55,1');
    Route::post('auth/confirm-email', 'confirmEmail')->middleware('throttle:10,1');
});

/*
|--------------------------------------------------------------------------
| OAUTH SECONDDARY CALLBACKS (PÚBLICOS)
|--------------------------------------------------------------------------
| Rotas que recebem retorno do Google/Meta sem Bearer Token.
| O Token de usuário é validado pelo parâmetro JWT 'state' na URL.
*/
Route::get('social-accounts/{platform}/callback', [SocialAccountController::class, 'callback']);

/*
|--------------------------------------------------------------------------
| Planos e tema da landing (PÚBLICO)
|--------------------------------------------------------------------------
| A landing page consome os planos ativos e o tema escolhido sem autenticação.
*/
Route::get('plans', [PlanController::class, 'index'])->middleware('throttle:45,1');
Route::get('landing-settings', [LandingSettingsController::class, 'show'])->middleware('throttle:45,1');

/*
|--------------------------------------------------------------------------
| Webhook do Stripe (PÚBLICO)
|--------------------------------------------------------------------------
| Verificação de assinatura embutida no WebhookController do Cashier
| (usa STRIPE_WEBHOOK_SECRET). URL no dashboard do Stripe: /api/stripe/webhook.
| A lógica custom (tabela Payment) roda via listener de WebhookReceived.
*/
Route::post('stripe/webhook', [WebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');

/*
|--------------------------------------------------------------------------
| Rotas Autenticadas (Email NÃO verificado pode acessar)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    // Meu perfil: dados, foto (URL pré-assinada) e senha do próprio usuário
    Route::patch('me', [ProfileController::class, 'update']);
    Route::prefix('me')->group(function () {
        Route::post('avatar/upload-url', [ProfileController::class, 'avatarUploadUrl'])->middleware('throttle:20,1');
        Route::put('avatar', [ProfileController::class, 'setAvatar']);
        Route::delete('avatar', [ProfileController::class, 'removeAvatar']);
        Route::put('password', [ProfileController::class, 'changePassword'])->middleware('throttle:5,1');
    });
    Route::post('auth/resend-email-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:3,1');
});

/*
|--------------------------------------------------------------------------
| Rotas Protegidas (Email VERIFICADO obrigatório)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'verified', 'throttle:45,1'])->group(function () {
    // Catálogo global de planos/preços: exclusivo do super admin (specs/002 D1).
    Route::prefix('admin')->middleware('super_admin')->group(function () {
        // Tema da landing (leitura pública em GET /landing-settings)
        Route::put('landing-settings', [AdminLandingSettingsController::class, 'update']);
        Route::get('plans', [AdminPlanController::class, 'index']);
        Route::post('plans', [AdminPlanController::class, 'store']);
        Route::get('plans/{plan}', [AdminPlanController::class, 'show']);
        Route::patch('plans/{plan}', [AdminPlanController::class, 'update']);
        Route::delete('plans/{plan}', [AdminPlanController::class, 'destroy']);
        Route::get('plans/{plan}/prices', [AdminPriceController::class, 'index']);
        Route::post('plans/{plan}/prices', [AdminPriceController::class, 'store']);
        Route::get('plans/{plan}/prices/{price}/versions', [AdminPriceController::class, 'versions']);
        Route::patch('plans/{plan}/prices/{price}', [AdminPriceController::class, 'update']);
        Route::delete('plans/{plan}/prices/{price}', [AdminPriceController::class, 'destroy']);
    });

    Route::prefix('billing')->group(function () {
        Route::get('subscription', [BillingSubscriptionController::class, 'current'])
            ->middleware('permission:billing.view');
        Route::post('subscription/checkout', [BillingSubscriptionController::class, 'checkout'])
            ->middleware('permission:billing.manage');
        Route::post('subscription/checkout/confirm', [BillingSubscriptionController::class, 'confirm'])
            ->middleware('permission:billing.manage');

        Route::get('setup-intent', [BillingPaymentMethodController::class, 'setupIntent'])
            ->middleware('permission:billing.view');
        Route::get('cards', [BillingPaymentMethodController::class, 'index'])
            ->middleware('permission:billing.view');
        Route::post('cards', [BillingPaymentMethodController::class, 'store'])
            ->middleware('permission:billing.manage');
        Route::patch('cards/{paymentMethod}', [BillingPaymentMethodController::class, 'update'])
            ->middleware('permission:billing.manage');
        Route::delete('cards/{paymentMethod}', [BillingPaymentMethodController::class, 'destroy'])
            ->middleware('permission:billing.manage');

        Route::get('payments', [BillingPaymentController::class, 'index'])
            ->middleware('permission:billing.view');
        Route::get('payments/{payment}', [BillingPaymentController::class, 'show'])
            ->middleware('permission:billing.view');
    });

    // Scheduled Posts
    Route::get('scheduled-posts', [ScheduledPostController::class, 'index']);
    Route::post('scheduled-posts/upload-url', [ScheduledPostController::class, 'uploadUrl']);
    Route::post('scheduled-posts', [ScheduledPostController::class, 'store']);
    Route::post('scheduled-posts/{scheduledPost}/retry', [ScheduledPostController::class, 'retry']);
    Route::delete('scheduled-posts/{scheduledPost}', [ScheduledPostController::class, 'destroy']);

    // YouTube
    Route::get('youtube-categories', [YouTubeCategoryController::class, 'index']);
    Route::get('youtube-privacy-statuses', [YouTubePrivacyStatusController::class, 'index']);

    // Users (CRUD)
    Route::apiResource('users', UserController::class);
    Route::patch('users/{user}/role', [UserController::class, 'changeRole']);

    // Audit Logs
    Route::get('logs', [AuditLogController::class, 'index']);

    // Social Accounts
    Route::get('social-accounts', [SocialAccountController::class, 'index']);
    Route::get('social-accounts/schedules', [SocialAccountController::class, 'schedules']);
    Route::get('social-accounts/{platform}/auth', [SocialAccountController::class, 'redirect']);
    Route::delete('social-accounts/{socialAccount}', [SocialAccountController::class, 'destroy']);

    // Workspaces (CRUD)
    Route::apiResource('workspaces', WorkspaceController::class);
    Route::post('workspaces/{workspace}/switch', [WorkspaceController::class, 'switchWorkspace']);

    // Foto do workspace (URL pré-assinada, mesmo fluxo da foto do perfil)
    Route::prefix('workspaces/{workspace}/avatar')->group(function () {
        Route::post('upload-url', [WorkspaceAvatarController::class, 'uploadUrl'])->middleware('throttle:20,1');
        Route::put('/', [WorkspaceAvatarController::class, 'set']);
        Route::delete('/', [WorkspaceAvatarController::class, 'remove']);
    });

    // Analytics
    Route::get('analytics/{socialAccount}/dashboard', [AnalyticsController::class, 'dashboard']);
    Route::get('analytics/{socialAccount}/best-times', [AnalyticsController::class, 'bestTimes']);

    // Content Pipeline (Kanban)
    Route::get('pipeline/board', [ContentPipelineController::class, 'board']);
    Route::post('pipeline', [ContentPipelineController::class, 'store']);
    Route::get('pipeline/{contentPipeline}', [ContentPipelineController::class, 'show']);
    Route::patch('pipeline/{contentPipeline}', [ContentPipelineController::class, 'update']);
    Route::delete('pipeline/{contentPipeline}', [ContentPipelineController::class, 'destroy']);
    Route::patch('pipeline/{contentPipeline}/move', [ContentPipelineController::class, 'move']);
    Route::patch('pipeline/{contentPipeline}/restore', [ContentPipelineController::class, 'restore']);

    // Roles (CRUD)
    Route::apiResource('roles', RoleController::class);

    // Accesses (CRUD)
    Route::apiResource('accesses', AccessController::class);

    // Permissions (somente leitura)
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::get('permissions/{permission}', [PermissionController::class, 'show']);

    // Me - Invites (convites recebidos)
    Route::prefix('me/invites')->group(function () {
        Route::get('/', [InviteController::class, 'received']);
        Route::patch('{invite}/accept', [InviteController::class, 'accept']);
        Route::patch('{invite}/decline', [InviteController::class, 'decline']);
    });

    // Central de notificações: recebimento livre (usuário logado, sem permissão);
    // as preferências exigem permissão (notifications.view / notifications.update)
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread', [NotificationController::class, 'unread']);
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read']);
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);
    Route::get('notification-preferences', [NotificationPreferenceController::class, 'show'])
        ->middleware('permission:notifications.view');
    Route::patch('notification-preferences', [NotificationPreferenceController::class, 'update'])
        ->middleware('permission:notifications.update');

    // Invites (gerenciamento de convites do workspace)
    Route::get('invites', [InviteController::class, 'index']);
    Route::post('invites', [InviteController::class, 'send']);
});
