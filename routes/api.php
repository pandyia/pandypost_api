<?php

use App\Http\Controllers\Api\AccessController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ScheduledPostController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\Api\YouTubeCategoryController;
use App\Http\Controllers\Api\YouTubePrivacyStatusController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\SocialAccountController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ContentPipelineController;
use Illuminate\Support\Facades\Route;

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
| Rotas Autenticadas (Email NÃO verificado pode acessar)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::post('auth/resend-email-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:3,1');
});

/*
|--------------------------------------------------------------------------
| Rotas Protegidas (Email VERIFICADO obrigatório)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'verified', 'throttle:45,1'])->group(function () {
    // Billing
    Route::post('subscribe', [SubscriptionController::class, 'subscribe']);
    Route::get('plans', [PlanController::class, 'index']);

    // Scheduled Posts
    Route::get('scheduled-posts', [ScheduledPostController::class, 'index']);
    Route::post('scheduled-posts', [ScheduledPostController::class, 'store']);

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

    // Invites (gerenciamento de convites do workspace)
    Route::get('invites', [InviteController::class, 'index']);
    Route::post('invites', [InviteController::class, 'send']);
});