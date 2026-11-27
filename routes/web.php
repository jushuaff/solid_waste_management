<?php
use App\Http\Controllers\{AuthController,PasswordResetController,ProfileController,DashboardController,
    ManagementController,ResidentGuideController,ReportController,ReportWorkflowController,NotificationController,LinisController,IncidentMediaController,FeeSettingsController,GarbageFeeController,FeeReceivingAccountController,FeeProviderController,GarbageFeeHistoryController,FeePaymentSubmissionController};
use Illuminate\Support\Facades\Route;
Route::get('/',fn()=>redirect()->route(auth()->check()?'dashboard':'login'));
Route::middleware('guest')->group(function(){
    Route::get('/login',[AuthController::class,'showLogin'])->name('login');
    Route::post('/login',[AuthController::class,'login'])->middleware('throttle:5,1')->name('login.attempt');
    Route::get('/register',[AuthController::class,'showRegister'])->name('register');
    Route::post('/register',[AuthController::class,'register'])->middleware('throttle:4,1')->name('register.store');
    Route::get('/forgot-password',[PasswordResetController::class,'requestForm'])->name('password.request');
    Route::post('/forgot-password',[PasswordResetController::class,'requestLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}',[PasswordResetController::class,'resetForm'])->name('password.reset');
    Route::post('/reset-password',[PasswordResetController::class,'reset'])->middleware('throttle:5,1')->name('password.update');
});
Route::post('/logout',[AuthController::class,'logout'])->middleware('auth')->name('logout');
Route::middleware(['auth','active'])->group(function(){
    Route::get('/dashboard',[DashboardController::class,'index'])->name('dashboard');

    // Phase 2: role-scoped garbage-fee tracking and externally confirmed payments.
    Route::get('/garbage-fees',[GarbageFeeController::class,'index'])
        ->middleware('role:superadmin,admin,coordinator,resident')->name('fees.index');
    Route::get('/garbage-fees/history',[GarbageFeeHistoryController::class,'index'])
        ->middleware('role:superadmin,admin,coordinator,resident')->name('fees.history.index');
    // Resident-submitted online transfers: evidence remains pending until admin verification.
    Route::post('/garbage-fees/submissions',[FeePaymentSubmissionController::class,'store'])
        ->middleware(['role:resident','throttle:5,1'])->name('fees.submissions.store');
    Route::get('/garbage-fees/submissions',[FeePaymentSubmissionController::class,'index'])
        ->middleware('role:superadmin,admin')->name('fees.submissions.index');
    Route::get('/garbage-fees/submissions/{submission}/receipt',[FeePaymentSubmissionController::class,'receipt'])
        ->middleware('role:superadmin,admin,resident')->name('fees.submissions.receipt');
    Route::post('/garbage-fees/submissions/{submission}/review',[FeePaymentSubmissionController::class,'review'])
        ->middleware(['role:superadmin,admin','throttle:15,1'])->name('fees.submissions.review');
    Route::post('/garbage-fees/generate',[GarbageFeeController::class,'generate'])
        ->middleware(['role:superadmin,admin','throttle:8,1'])->name('fees.generate');
    Route::post('/garbage-fees/charges/{charge}/payments',[GarbageFeeController::class,'recordPayment'])
        ->middleware(['role:superadmin,admin','throttle:15,1'])->name('fees.payments.store');
    Route::post('/garbage-fees/payments/{payment}/void',[GarbageFeeController::class,'voidPayment'])
        ->middleware(['role:superadmin','throttle:6,1'])->name('fees.payments.void');

    Route::get('/garbage-fees/settings',[FeeSettingsController::class,'index'])
        ->middleware('role:superadmin')->name('fees.settings.index');
    Route::post('/garbage-fees/settings/cities',[FeeSettingsController::class,'storeCity'])
        ->middleware('role:superadmin')->name('fees.settings.cities.store');
    Route::post('/garbage-fees/settings/cities/{city}/rates',[FeeSettingsController::class,'storeRate'])
        ->middleware('role:superadmin')->name('fees.settings.rates.store');
    Route::put('/garbage-fees/settings/cities/{city}/zones',[FeeSettingsController::class,'assignZones'])
        ->middleware('role:superadmin')->name('fees.settings.zones.update');

    Route::get('/garbage-fees/accounts',[FeeReceivingAccountController::class,'index'])
        ->middleware('role:superadmin,admin')->name('fees.accounts.index');
    Route::post('/garbage-fees/accounts',[FeeReceivingAccountController::class,'store'])
        ->middleware(['role:superadmin,admin','throttle:10,1'])->name('fees.accounts.store');
    Route::put('/garbage-fees/accounts/{account}',[FeeReceivingAccountController::class,'update'])
        ->middleware(['role:superadmin,admin','throttle:10,1'])->name('fees.accounts.update');
    Route::post('/garbage-fees/providers',[FeeProviderController::class,'store'])
        ->middleware(['role:superadmin','throttle:10,1'])->name('fees.providers.store');
    Route::patch('/garbage-fees/providers/{provider}/toggle',[FeeProviderController::class,'toggle'])
        ->middleware('role:superadmin')->name('fees.providers.toggle');
    Route::get('/garbage-fees/accounts/{account}/qr',[FeeReceivingAccountController::class,'qr'])
        ->middleware('role:superadmin,admin,resident')->name('fees.accounts.qr');

    // MIT 104 LINIS — online-first inspection, household and MRF monitoring.
    Route::get('/linis/dashboard',[LinisController::class,'dashboard'])->name('linis.dashboard');
    Route::get('/linis/monthly-report',[LinisController::class,'monthlyReport'])->name('linis.print-monthly');
    Route::get('/linis/inspections/{id}/evidence',[LinisController::class,'evidence'])->name('linis.evidence');
    Route::get('/linis/{module}',[LinisController::class,'index'])->whereIn('module',['households','inspections','weigh-ins'])->name('linis.index');
    Route::get('/linis/{module}/create',[LinisController::class,'create'])->whereIn('module',['households','inspections','weigh-ins'])->name('linis.create');
    Route::post('/linis/{module}',[LinisController::class,'store'])->whereIn('module',['households','inspections','weigh-ins'])->name('linis.store');
    Route::get('/linis/{module}/{id}/edit',[LinisController::class,'edit'])->whereIn('module',['households','inspections','weigh-ins'])->name('linis.edit');
    Route::put('/linis/{module}/{id}',[LinisController::class,'update'])->whereIn('module',['households','inspections','weigh-ins'])->name('linis.update');
    Route::delete('/linis/{module}/{id}',[LinisController::class,'destroy'])->whereIn('module',['households','inspections','weigh-ins'])->name('linis.destroy');

    Route::get('/schedule',[ResidentGuideController::class,'schedules'])->name('schedules.index');
    Route::get('/schedule/print',[ResidentGuideController::class,'printSchedules'])->name('schedules.print');
    Route::get('/local-rules',[ResidentGuideController::class,'rules'])->name('rules.index');
    Route::get('/profile',[ProfileController::class,'edit'])->name('profile.edit');
    Route::put('/profile',[ProfileController::class,'update'])->name('profile.update');
    Route::put('/profile/password',[ProfileController::class,'password'])->name('profile.password');
    Route::get('/notifications',[NotificationController::class,'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read',[NotificationController::class,'read'])->name('notifications.read');
    Route::post('/notifications/{id}/open',[NotificationController::class,'open'])->name('notifications.open');
    Route::get('/reports',[ReportController::class,'index'])->name('reports.index');
    Route::get('/reports/create',[ReportController::class,'create'])->middleware('role:resident')->name('reports.create');
    Route::post('/reports',[ReportController::class,'store'])->middleware(['role:resident','throttle:10,1'])->name('reports.store');
    Route::get('/reports/print-summary',[ReportController::class,'printableSummary'])->middleware('role:superadmin,admin,coordinator')->name('reports.print-summary');
    Route::get('/reports/{report}',[ReportController::class,'show'])->name('reports.show');
    Route::get('/reports/{report}/edit',[ReportController::class,'edit'])->middleware('role:resident')->name('reports.edit');
    Route::put('/reports/{report}',[ReportController::class,'update'])->middleware('role:resident')->name('reports.update');
    Route::delete('/reports/{report}',[ReportController::class,'archive'])->middleware('role:superadmin,admin')->name('reports.archive');
    Route::get('/reports/{report}/print',[ReportController::class,'print'])->name('reports.print');
    Route::get('/reports/{report}/evidence',[ReportController::class,'evidence'])->name('reports.evidence');
    Route::get('/reports/{report}/media/{media}',[IncidentMediaController::class,'show'])->name('reports.media.show');
    Route::delete('/reports/{report}/media/{media}',[IncidentMediaController::class,'destroy'])
        ->middleware(['role:resident','throttle:10,1'])->name('reports.media.destroy');
    Route::post('/reports/{report}/workflow',[ReportWorkflowController::class,'change'])->middleware('role:superadmin,admin,coordinator,crew')->name('reports.workflow');
    Route::middleware('role:superadmin,admin,coordinator')->prefix('manage')->name('management.')->group(function(){
        Route::get('/users/{id}/password',[ManagementController::class,'passwordForm'])->middleware('role:superadmin')->name('users.password');
        Route::put('/users/{id}/password',[ManagementController::class,'changePassword'])->middleware(['role:superadmin','throttle:5,1'])->name('users.password.update');
        Route::get('/{resource}',[ManagementController::class,'index'])->name('index');
        Route::get('/{resource}/create',[ManagementController::class,'create'])->name('create');
        Route::post('/{resource}',[ManagementController::class,'store'])->name('store');
        Route::get('/{resource}/{id}/edit',[ManagementController::class,'edit'])->name('edit');
        Route::put('/{resource}/{id}',[ManagementController::class,'update'])->name('update');
        Route::delete('/{resource}/{id}',[ManagementController::class,'destroy'])->name('destroy');
    });
});
