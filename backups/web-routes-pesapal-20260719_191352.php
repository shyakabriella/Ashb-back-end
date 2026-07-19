<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


/*
|--------------------------------------------------------------------------
| Public invoice payment redirect
|--------------------------------------------------------------------------
|
| Redirect invoice payment links opened through the API domain to the
| Next.js frontend payment page.
|
*/

\Illuminate\Support\Facades\Route::get(
    '/invoices/{invoice}/pay',
    function (string $invoice) {
        return redirect()->away(
            'https://www.d.ashbhub.com/invoices/'
            . $invoice
            . '/pay'
        );
    }
)
    ->whereNumber('invoice')
    ->name('invoices.pay.redirect');

\Illuminate\Support\Facades\Route::get(
    '/pesapal/callback',
    [
        \App\Http\Controllers\API
            \PesapalPaymentController::class,
        'callback',
    ]
)->name('pesapal.callback');

