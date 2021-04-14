<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Braintree Keys
    |--------------------------------------------------------------------------
    |
    | The Braintree merchant ID, publishable key and secret key give you access to Braintree's
    |
    */

    'env' => env('BRAINTREE_ENV'),

    'merchant_id' => env('BRAINTREE_MERCHANT_ID'),

    'key' => env('BRAINTREE_PUBLIC_KEY'),

    'secret' => env('BRAINTREE_PRIVATE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Braintree Subscription Model
    |--------------------------------------------------------------------------
    |
    | This is the model in your application that implements the Billable trait
    | provided by Braintree Subscription. It will serve as the primary model you use while
    | interacting with Braintree Subscription related methods, subscriptions, and so on.
    |
    */

    'model' => env('BRAINTREE_MODEL', class_exists(App\Models\User::class) ? App\Models\User::class : App\User::class),

    /*
     * The braintree webhooks will be available on this path.
     */
    'path' => env('CASHIER_PATH', 'braintree'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | This is the default currency that will be used when generating charges
    | from your application. Of course, you are welcome to use any of the
    | various world currencies that are currently supported via Stripe.
    |
    */

    'currency' => env('CASHIER_CURRENCY', 'usd'),

    /*
    |--------------------------------------------------------------------------
    | Currency Locale
    |--------------------------------------------------------------------------
    |
    | This is the default locale in which your money values are formatted in
    | for display. To utilize other locales besides the default en locale
    | verify you have the "intl" PHP extension installed on the system.
    |
    */

    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'en'),


    /*
    |--------------------------------------------------------------------------
    | Invoice Paper Size
    |--------------------------------------------------------------------------
    |
    | This option is the default paper size for all invoices generated using
    | Cashier. You are free to customize this settings based on the usual
    | paper size used by the customers using your Laravel applications.
    |
    | Supported sizes: 'letter', 'legal', 'A4'
    |
    */

    'paper' => env('CASHIER_PAPER', 'letter'),

    /*
    |--------------------------------------------------------------------------
    | Stripe Logger
    |--------------------------------------------------------------------------
    |
    | This setting defines which logging channel will be used by the Stripe
    | library to write log messages. You are free to specify any of your
    | logging channels listed inside the "logging" configuration file.
    |
    */

    'logger' => env('CASHIER_LOGGER'),

];
