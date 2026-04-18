<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Config\Env;
use App\Controllers\AuthController;
use App\Controllers\CompanyDocumentController;
use App\Controllers\DashboardController;
use App\Controllers\EmployeeController;
use App\Controllers\EmployeeDocumentController;
use App\Controllers\FileController;
use App\Controllers\LookupController;
use App\Controllers\NotificationController;
use App\Controllers\PassportController;
use App\Controllers\ReportController;
use App\Controllers\SettingsController;
use App\Controllers\UserController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Router;
use App\Middleware\RequireAuth;
use App\Middleware\RequireRole;
use App\Services\ActivityLogger;
use App\Services\EmployeeImportService;
use App\Services\FileUploadService;
use App\Services\PassportDocumentSyncService;
use App\Services\PassportImportService;

require dirname(__DIR__) . '/bootstrap/autoload.php';
require dirname(__DIR__) . '/src/Support/helpers.php';

Env::load(dirname(__DIR__));
$config = AppConfig::all();

session_name($config['app']['session_name']);
session_start();

header('Access-Control-Allow-Origin: ' . $config['app']['frontend_url']);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = Database::connect($config['db']);
$request = Request::capture();
$router = new Router();

$uploadService = new FileUploadService($config['app']['upload_dir']);
$activityLogger = new ActivityLogger($pdo);
$passportDocumentSyncService = new PassportDocumentSyncService($pdo);
$employeeImportService = new EmployeeImportService($pdo);
$passportImportService = new PassportImportService($pdo, $passportDocumentSyncService);

$authController = new AuthController($pdo);
$dashboardController = new DashboardController($pdo);
$employeeController = new EmployeeController($pdo, $uploadService, $activityLogger, $employeeImportService);
$passportController = new PassportController($pdo, $uploadService, $activityLogger, $passportDocumentSyncService, $passportImportService);
$employeeDocumentController = new EmployeeDocumentController($pdo, $uploadService, $activityLogger);
$companyDocumentController = new CompanyDocumentController($pdo, $uploadService, $activityLogger);
$settingsController = new SettingsController($pdo);
$userController = new UserController($pdo, $uploadService);
$lookupController = new LookupController($pdo);
$notificationController = new NotificationController($pdo);
$reportController = new ReportController($pdo);
$fileController = new FileController($config['app']['upload_dir']);

$auth = new RequireAuth();
$adminOrHr = new RequireRole(['admin', 'hr_user']);
$adminOnly = new RequireRole(['admin']);

$router->add('POST', '/api/auth/login', fn ($request) => $authController->login($request));
$router->add('GET', '/api/auth/me', fn () => $authController->me(), [$auth]);
$router->add('POST', '/api/auth/logout', fn () => $authController->logout(), [$auth]);

$router->add('GET', '/api/dashboard/summary', fn ($request) => $dashboardController->summary($request), [$auth]);

$router->add('GET', '/api/employees', fn ($request) => $employeeController->index($request), [$auth]);
$router->add('GET', '/api/employees/status-summary', fn () => $employeeController->statusSummary(), [$auth]);
$router->add('GET', '/api/employees/import-template', fn () => $employeeController->importTemplate(), [$auth]);
$router->add('POST', '/api/employees/import', fn ($request) => $employeeController->import($request), [$auth, $adminOrHr]);
$router->add('GET', '/api/employees/{id}', fn ($request, $params) => $employeeController->show($request, $params), [$auth]);
$router->add('POST', '/api/employees', fn ($request) => $employeeController->store($request), [$auth, $adminOrHr]);
$router->add('PUT', '/api/employees/{id}', fn ($request, $params) => $employeeController->update($request, $params), [$auth, $adminOrHr]);
$router->add('POST', '/api/employees/{id}', fn ($request, $params) => $employeeController->update($request, $params), [$auth, $adminOrHr]);
$router->add('POST', '/api/employees/{id}/delete', fn ($request, $params) => $employeeController->delete($request, $params), [$auth, $adminOrHr]);

$router->add('GET', '/api/lookups', fn () => $lookupController->index(), [$auth]);

$router->add('GET', '/api/passports', fn ($request) => $passportController->lists($request), [$auth]);
$router->add('GET', '/api/passports/history/{employeeId}', fn ($request, $params) => $passportController->history($request, $params), [$auth]);
$router->add('POST', '/api/passports', fn ($request) => $passportController->upsert($request), [$auth, $adminOrHr]);
$router->add('POST', '/api/passports/import', fn ($request) => $passportController->import($request), [$auth, $adminOrHr]);

$router->add('GET', '/api/employee-documents', fn ($request) => $employeeDocumentController->index($request), [$auth]);
$router->add('POST', '/api/employee-documents', fn ($request) => $employeeDocumentController->store($request), [$auth, $adminOrHr]);
$router->add('POST', '/api/employee-documents/{id}', fn ($request, $params) => $employeeDocumentController->update($request, $params), [$auth, $adminOrHr]);

$router->add('GET', '/api/company-documents', fn () => $companyDocumentController->index(), [$auth]);
$router->add('POST', '/api/company-documents', fn ($request) => $companyDocumentController->store($request), [$auth, $adminOrHr]);
$router->add('POST', '/api/company-documents/{id}', fn ($request, $params) => $companyDocumentController->update($request, $params), [$auth, $adminOrHr]);

$router->add('GET', '/api/settings', fn () => $settingsController->index(), [$auth, $adminOnly]);
$router->add('POST', '/api/settings', fn ($request) => $settingsController->saveSetting($request), [$auth, $adminOnly]);
$router->add('POST', '/api/settings/master/{type}', fn ($request, $params) => $settingsController->saveMaster($request, $params), [$auth, $adminOnly]);

$router->add('GET', '/api/notifications', fn () => $notificationController->index(), [$auth]);
$router->add('POST', '/api/notifications/{id}/read', fn ($request, $params) => $notificationController->markRead($request, $params), [$auth]);
$router->add('POST', '/api/notifications/read-all', fn () => $notificationController->markAllRead(), [$auth]);

$router->add('GET', '/api/users', fn () => $userController->index(), [$auth, $adminOnly]);
$router->add('POST', '/api/users', fn ($request) => $userController->store($request), [$auth, $adminOnly]);
$router->add('POST', '/api/users/{id}', fn ($request, $params) => $userController->update($request, $params), [$auth, $adminOnly]);
$router->add('GET', '/api/profile', fn () => $userController->profile(), [$auth]);
$router->add('POST', '/api/profile', fn ($request) => $userController->updateProfile($request), [$auth]);
$router->add('POST', '/api/profile/password', fn ($request) => $userController->updatePassword($request), [$auth]);

$router->add('GET', '/api/reports/passports', fn ($request) => $reportController->passportCustody($request), [$auth]);
$router->add('GET', '/api/reports/passport-movements', fn ($request) => $reportController->passportMovements($request), [$auth]);
$router->add('GET', '/api/reports/expiry', fn ($request) => $reportController->expiryReport($request), [$auth]);
$router->add('GET', '/api/reports/employee-summary', fn ($request) => $reportController->employeeDocumentSummary($request), [$auth]);
$router->add('GET', '/api/files/view', fn () => $fileController->view(), [$auth]);

$router->dispatch($request);
