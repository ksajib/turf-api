<?php

use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\BusinessController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionPlanController;
use App\Http\Controllers\Api\V1\Admin\BusinessSubscriptionController;
use App\Http\Controllers\Api\V1\Admin\PlatformDashboardController;
use App\Http\Controllers\Api\V1\Owner\AuthController as OwnerAuthController;
use App\Http\Controllers\Api\V1\Owner\OwnerWorkspaceController;
use App\Http\Controllers\Api\V1\Owner\TurfController;
use App\Http\Controllers\Api\V1\Owner\BookingController;
use App\Http\Controllers\Api\V1\Owner\PaymentController;
use App\Http\Controllers\Api\V1\Owner\CustomerController;
use App\Http\Controllers\Api\V1\Owner\RoleController;
use App\Http\Controllers\Api\V1\Owner\StaffController;
use App\Http\Controllers\Api\V1\Owner\ReportController;
use App\Http\Controllers\Api\V1\Owner\SubscriptionController;
use App\Http\Controllers\Api\V1\Owner\PayrollSettingsController;
use App\Http\Controllers\Api\V1\Owner\PayrollShiftController;
use App\Http\Controllers\Api\V1\Owner\PayrollHolidayController;
use App\Http\Controllers\Api\V1\Owner\PayrollLeaveTypeController;
use App\Http\Controllers\Api\V1\Owner\PayrollLeaveApplicationController;
use App\Http\Controllers\Api\V1\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Api\V1\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Api\V1\Customer\TurfController as CustomerTurfController;
use App\Http\Controllers\Api\V1\Customer\PaymentController as CustomerPaymentController;
use App\Http\Controllers\Api\V1\Customer\BookingController as CustomerBookingController;
use App\Http\Controllers\Api\V1\Auth\UnifiedAuthController;
use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('v1/health', fn () => response()->json(['status' => 'ok']))->name('api.v1.health');

Route::prefix('v1/auth')->group(function () {
    Route::post('login', [UnifiedAuthController::class, 'login']);
    Route::post('refresh', [UnifiedAuthController::class, 'refresh']);
    Route::middleware('api.token')->group(function () {
        Route::get('me', [UnifiedAuthController::class, 'me']);
        Route::post('logout', [UnifiedAuthController::class, 'logout']);
    });
});

Route::prefix('v1/notifications')->middleware('api.token')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::patch('{notification}/read', [NotificationController::class, 'read']);
    Route::patch('read-all', [NotificationController::class, 'readAll']);
});

Route::prefix('v1/owner/auth')->group(function () {
    Route::post('login', [OwnerAuthController::class, 'login']);
    Route::post('refresh', [OwnerAuthController::class, 'refresh']);
    Route::middleware('api.token')->group(function () {
        Route::get('me', [OwnerAuthController::class, 'me']);
        Route::post('password', [OwnerAuthController::class, 'changePassword']);
        Route::post('logout', [OwnerAuthController::class, 'logout']);
    });
    Route::middleware(['auth:sanctum', 'owner.password.changed'])->get('profile', [OwnerAuthController::class, 'me']);
});

Route::prefix('v1/owner')->middleware(['api.token', 'owner.password.changed'])->group(function () {
    Route::get('profile', [OwnerWorkspaceController::class, 'profile']);
    Route::get('dashboard/overview', [OwnerWorkspaceController::class, 'dashboard'])->middleware('staff.permission:dashboard.view');
    Route::get('turfs', [TurfController::class, 'index'])->middleware('staff.permission:turf.view,turf.edit');
    Route::post('turfs', [TurfController::class, 'store'])->middleware('staff.permission:turf.create');
    Route::get('turfs/{turf}', [TurfController::class, 'show'])->middleware('staff.permission:turf.view,turf.edit');
    Route::post('turfs/{turf}', [TurfController::class, 'update'])->middleware('staff.permission:turf.edit');
    Route::patch('turfs/{turf}', [TurfController::class, 'update'])->middleware('staff.permission:turf.edit');
    Route::patch('turfs/{turf}/status', [TurfController::class, 'status'])->middleware('staff.permission:turf.edit');
    Route::delete('turfs/{turf}', [TurfController::class, 'destroy'])->middleware('staff.permission:turf.delete');
    Route::get('bookings', [BookingController::class, 'index'])->middleware('staff.permission:bookings.view,bookings.edit');
    Route::post('bookings', [BookingController::class, 'store'])->middleware('staff.permission:bookings.create');
    Route::get('availability', [BookingController::class, 'availability'])->middleware('staff.permission:bookings.view,bookings.edit');
    Route::get('bookings/slots', [BookingController::class, 'slots'])->middleware('staff.permission:bookings.view,bookings.edit');
    Route::get('bookings/{booking}', [BookingController::class, 'show'])->middleware('staff.permission:bookings.view,bookings.edit');
    Route::patch('bookings/{booking}', [BookingController::class, 'update'])->middleware('staff.permission:bookings.edit');
    Route::patch('bookings/{booking}/status', [BookingController::class, 'status'])->middleware('staff.permission:bookings.edit');
    Route::post('bookings/{booking}/payment', [BookingController::class, 'payment'])->middleware('staff.permission:payments.create');
    Route::post('bookings/{booking}/invoice/send', [BookingController::class, 'sendInvoice'])->middleware('staff.permission:bookings.edit');
    Route::get('bookings/{booking}/invoice/pdf', [BookingController::class, 'downloadInvoice'])->middleware('staff.permission:bookings.view,bookings.edit');
    Route::get('payments', [PaymentController::class, 'index'])->middleware('staff.permission:payments.view,payments.edit');
    Route::get('payments/{booking}', [PaymentController::class, 'show'])->middleware('staff.permission:payments.view,payments.edit');
    Route::get('customers', [CustomerController::class, 'index'])->middleware('staff.permission:customers.view,customers.edit');
    Route::post('customers', [CustomerController::class, 'store'])->middleware('staff.permission:customers.create');
    Route::get('roles', [RoleController::class, 'index']);
    Route::get('staff', [StaffController::class, 'index']);
    Route::post('staff', [StaffController::class, 'store']);
    Route::post('staff/{staff}/reset-password', [StaffController::class, 'resetPassword']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::get('roles/{role}', [RoleController::class, 'show']);
    Route::patch('roles/{role}', [RoleController::class, 'update']);
    Route::post('roles/{role}/duplicate', [RoleController::class, 'duplicate']);
    Route::patch('roles/{role}/status', [RoleController::class, 'status']);
    Route::delete('roles/{role}', [RoleController::class, 'destroy']);
    Route::get('reports', [ReportController::class, 'index'])->middleware('staff.permission:reports.view');
    Route::get('subscription', [SubscriptionController::class, 'show']);
    Route::get('payroll/settings', [PayrollSettingsController::class, 'show']);
    Route::patch('payroll/settings', [PayrollSettingsController::class, 'update']);
    Route::get('payroll/shifts', [PayrollShiftController::class, 'index']);
    Route::post('payroll/shifts', [PayrollShiftController::class, 'store']);
    Route::patch('payroll/shifts/{shift}', [PayrollShiftController::class, 'update']);
    Route::delete('payroll/shifts/{shift}', [PayrollShiftController::class, 'destroy']);
    Route::get('payroll/holidays', [PayrollHolidayController::class, 'index']);
    Route::post('payroll/holidays', [PayrollHolidayController::class, 'store']);
    Route::patch('payroll/holidays/{holiday}', [PayrollHolidayController::class, 'update']);
    Route::delete('payroll/holidays/{holiday}', [PayrollHolidayController::class, 'destroy']);
    Route::get('payroll/leave-types', [PayrollLeaveTypeController::class, 'index']);
    Route::post('payroll/leave-types', [PayrollLeaveTypeController::class, 'store']);
    Route::patch('payroll/leave-types/{leaveType}', [PayrollLeaveTypeController::class, 'update']);
    Route::delete('payroll/leave-types/{leaveType}', [PayrollLeaveTypeController::class, 'destroy']);
    Route::get('payroll/leave-applications', [PayrollLeaveApplicationController::class, 'index']);
    Route::get('payroll/leave-balance', [PayrollLeaveApplicationController::class, 'balance']);
    Route::post('payroll/leave-applications', [PayrollLeaveApplicationController::class, 'store']);
    Route::delete('payroll/leave-applications/{application}', [PayrollLeaveApplicationController::class, 'destroy']);
    Route::post('subscription/pause', [SubscriptionController::class, 'pause']);
    Route::get('subscription/invoices/{payment}/pdf', [SubscriptionController::class, 'downloadInvoice']);
    Route::post('subscription/invoices/{payment}/send', [SubscriptionController::class, 'sendInvoice']);
});

Route::prefix('v1/customer')->group(function () {
    Route::post('register', [CustomerAuthController::class, 'register']);
    Route::get('dashboard/overview', [CustomerDashboardController::class, 'overview'])->middleware('api.token');
    Route::get('turfs', [CustomerTurfController::class, 'index'])->middleware('api.token');
    Route::get('turfs/slots', [CustomerTurfController::class, 'slots'])->middleware('api.token');
    Route::post('bookings', [CustomerTurfController::class, 'storeBooking'])->middleware('api.token');
    Route::get('payments', [CustomerBookingController::class, 'payments'])->middleware('api.token');
    Route::get('bookings', [CustomerBookingController::class, 'index'])->middleware('api.token');
    Route::get('bookings/{booking}', [CustomerBookingController::class, 'show'])->middleware('api.token');
    Route::patch('bookings/{booking}/cancel', [CustomerBookingController::class, 'cancel'])->middleware('api.token');
    Route::patch('bookings/{booking}/reschedule', [CustomerBookingController::class, 'reschedule'])->middleware('api.token');
    Route::post('bookings/{booking}/payment', [CustomerBookingController::class, 'payment'])->middleware('api.token');
});

Route::prefix('v1/customer/payments/sslcommerz')->group(function () {
    Route::match(['get', 'post'], 'success', [CustomerPaymentController::class, 'success']);
    Route::match(['get', 'post'], 'fail', [CustomerPaymentController::class, 'fail']);
    Route::match(['get', 'post'], 'cancel', [CustomerPaymentController::class, 'cancel']);
    Route::post('ipn', [CustomerPaymentController::class, 'ipn']);
});

Route::prefix('v1/admin/auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::middleware(['api.token', 'platform.admin'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('v1/admin')->middleware(['api.token', 'platform.admin'])->group(function () {
    Route::get('dashboard/overview', [PlatformDashboardController::class, 'overview']);
    Route::get('settings', [PlatformDashboardController::class, 'settings']);
    Route::get('subscription-plans/published', [BusinessSubscriptionController::class, 'plans']);
    Route::get('subscriptions', [BusinessSubscriptionController::class, 'index']);
    Route::get('subscription-payments', [BusinessSubscriptionController::class, 'payments']);
    Route::get('subscription-payments/pending', [BusinessSubscriptionController::class, 'pendingPayments']);
    Route::post('subscription-payments/{payment}/verify', [BusinessSubscriptionController::class, 'verify']);
    Route::post('subscription-payments/{payment}/reject', [BusinessSubscriptionController::class, 'reject']);
    Route::get('businesses/owners', [BusinessController::class, 'owners']);
    Route::post('credential-deliveries/{delivery}/retry', [BusinessController::class, 'retryCredentialDelivery']);
    Route::post('businesses/{business}/credential-reissue', [BusinessController::class, 'reissueCredential']);
    Route::apiResource('businesses', BusinessController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::get('businesses/{business}/subscription', [BusinessSubscriptionController::class, 'show']);
    Route::post('businesses/{business}/subscription/payments', [BusinessSubscriptionController::class, 'storePayment']);
    Route::post('businesses/{business}/subscription/complete-payment', [BusinessSubscriptionController::class, 'completePayment']);
    Route::get('subscription-payments/{payment}/invoice', [BusinessSubscriptionController::class, 'invoice']);
    Route::post('subscription-payments/{payment}/invoice/pdf', [BusinessSubscriptionController::class, 'generateInvoicePdf']);
    Route::get('subscription-payments/{payment}/invoice/pdf', [BusinessSubscriptionController::class, 'downloadInvoicePdf']);
    Route::post('subscription-payments/{payment}/invoice/send', [BusinessSubscriptionController::class, 'sendInvoice']);
    Route::apiResource('plans', SubscriptionPlanController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('plans/{plan}/publish', [SubscriptionPlanController::class, 'publish']);
    Route::post('plans/{plan}/archive', [SubscriptionPlanController::class, 'archive']);
});
