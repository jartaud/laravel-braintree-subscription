<?php

namespace Jartaud\LaravelBraintreeSubscription\Tests;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use Jartaud\LaravelBraintreeSubscription\LaravelBraintreeSubscriptionServiceProvider;

abstract class TestCase extends Orchestra
{
    public function setUp(): void
    {
        //sleep(2);

        parent::setUp();

        Model::unguard();

        $this
            //->setUpRoutes()
            ->migrateDatabase();

        $this->withoutExceptionHandling();
    }

    public function tearDown() : void
    {
        Schema::drop('users');
        Schema::drop('subscriptions');
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelBraintreeSubscriptionServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function migrateDatabase()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email');
            $table->string('name');
            $table->string('braintree_id')->nullable();
            $table->string('paypal_email')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_last_four')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('name');
            $table->string('braintree_id');
            $table->string('braintree_status');
            $table->string('braintree_plan');
            $table->integer('quantity');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'braintree_status']);
        });

        return $this;
    }
}
