<?php

use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\MasterDataImportWebController;
use App\Http\Controllers\Pos\PosShellController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\WalkthroughChainController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthCheckController::class, 'health'])->name('health');
Route::get('/ready', [HealthCheckController::class, 'ready'])->name('ready');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/pos', [PosShellController::class, 'index'])->name('pos.shell');

Route::get('/import/master-data', [MasterDataImportWebController::class, 'index'])->name('import.master-data');
Route::post('/import/master-data', [MasterDataImportWebController::class, 'upload'])->name('import.master-data.upload');

Route::get('/signup', fn () => redirect('/register'));
Route::get('/register', [RegistrationController::class, 'showForm'])->name('register');
Route::post('/register', [RegistrationController::class, 'register'])->middleware('throttle:5,60');
Route::get('/verify-email', [RegistrationController::class, 'verifyEmail']);
Route::post('/verify-phone', [RegistrationController::class, 'verifyPhone']);

Route::middleware(['web', 'auth'])->prefix('api/walkthrough/chains')->group(function () {
    Route::post('/purchase-order', [WalkthroughChainController::class, 'createPurchaseOrder']);
    Route::post('/goods-receipt', [WalkthroughChainController::class, 'createGoodsReceipt']);
    Route::post('/vendor-bill', [WalkthroughChainController::class, 'createVendorBill']);
    Route::post('/quotation-sales-order', [WalkthroughChainController::class, 'createQuotationAndSalesOrder']);
    Route::post('/delivery-challan', [WalkthroughChainController::class, 'createDeliveryChallan']);
    Route::post('/sales-invoice', [WalkthroughChainController::class, 'createSalesInvoice']);
});
