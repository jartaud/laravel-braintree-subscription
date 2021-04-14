<?php

namespace Jartaud\LaravelBraintreeSubscription\Tests;

use Carbon\Carbon;
use Braintree\Configuration;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Jartaud\LaravelBraintreeSubscription\Http\Controllers\WebhookController;

class LaravelBraintreeSubscriptionTest extends TestCase
{
    public $gateway;
    public $clientToken;
    public function setUp(): void
    {
        parent::setUp();

        Configuration::environment('sandbox');
        Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
        Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
        Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));
    }

    protected function getTestToken()
    {
        return 'fake-valid-nonce';
    }

    public function test_subscriptions_can_be_created()
    {
        $owner = User::create([
            'email' => 'josue.artaud@gmail.com',
            'name' => 'Josué Artaud',
        ]);

        // Create Subscription
        $owner->newSubscription('main', 'monthly-10-1')->create($this->getTestToken());

        $this->assertEquals(1, count($owner->subscriptions));
        $this->assertNotNull($owner->subscription('main')->braintree_id);

        $this->assertTrue($owner->subscribed('main'));
        $this->assertTrue($owner->subscribed('main', 'monthly-10-1'));
        $this->assertFalse($owner->subscribed('main', 'monthly-10-2'));
        $this->assertTrue($owner->subscription('main')->active());
        $this->assertFalse($owner->subscription('main')->cancelled());
        $this->assertFalse($owner->subscription('main')->onGracePeriod());
        $this->assertTrue($owner->subscription('main')->recurring());
        $this->assertFalse($owner->subscription('main')->ended());

        // Cancel Subscription
        $subscription = $owner->subscription('main');
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Modify Ends Date To Past
        $oldGracePeriod = $subscription->ends_at;
        $subscription->fill(['ends_at' => Carbon::now()->subDays(5)])->save();

        $this->assertFalse($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertTrue($subscription->ended());

        $subscription->fill(['ends_at' => $oldGracePeriod])->save();

        // Resume Subscription
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Increment & Decrement
        $subscription->incrementQuantity();

        $this->assertEquals(2, $subscription->quantity);

        $subscription->decrementQuantity();

        $this->assertEquals(1, $subscription->quantity);

        // Swap Plan
        $subscription->swap('monthly-10-2');

        $this->assertEquals('monthly-10-2', $subscription->braintree_plan);

        // Invoice Tests
        $invoice = $owner->invoicesIncludingPending()[0];
        $foundInvoice = $owner->findInvoice($invoice->id);

        $this->assertEquals($invoice->id, $foundInvoice->id);
        $this->assertEquals('$10.00', $invoice->total());
        $this->assertFalse($invoice->hasDiscount());
        $this->assertEquals(0, count($invoice->coupons()));
        $this->assertInstanceOf(Carbon::class, $invoice->date());
    }

    public function test_creating_subscription_with_coupons()
    {
        $owner = User::create([
            'email' => 'josue.artaud@gmail.com',
            'name' => 'Josué Artaud',
        ]);

        // Create Subscription
        $owner->newSubscription('main', 'monthly-10-1')
            ->withCoupon('coupon-1')
            ->create($this->getTestToken());

        $subscription = $owner->subscription('main');

        $this->assertTrue($owner->subscribed('main'));
        $this->assertTrue($owner->subscribed('main', 'monthly-10-1'));
        $this->assertFalse($owner->subscribed('main', 'monthly-10-2'));
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());

        // Invoice Tests
        $invoice = $owner->invoicesIncludingPending()[0];

        $this->assertTrue($invoice->hasDiscount());
        $this->assertEquals('$5.00', $invoice->total());
        $this->assertEquals('$5.00', $invoice->amountOff());
    }

    public function test_creating_subscription_with_trial()
    {
        $owner = User::create([
            'email' => 'josue.artaud@gmail.com',
            'name' => 'Josué Artaud',
        ]);

        // Create Subscription
        $owner->newSubscription('main', 'monthly-10-1')
            ->trialDays(7)
            ->create($this->getTestToken());

        $subscription = $owner->subscription('main');

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());
        $this->assertEquals(Carbon::today()->addDays(7)->day, $subscription->trial_ends_at->day);

        // Cancel Subscription
        $subscription->cancel();

        // Braintree trials are just cancelled out right since we have
        // no good way to cancel them and then later resume them.
        $this->assertFalse($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());
    }

    public function test_applying_coupons_to_existing_customers()
    {
        $owner = User::create([
            'email' => 'josue.artaud@gmail.com',
            'name' => 'Josué Artaud',
        ]);

        // Create Subscription
        $owner->newSubscription('main', 'monthly-10-1')->create($this->getTestToken());

        // Apply Coupon
        $owner->applyCoupon('coupon-1', 'main');

        $subscription = $owner->subscription('main')->asBraintreeSubscription();

        foreach ($subscription->discounts as $discount) {
            if ($discount->id === 'coupon-1') {
                return;
            }
        }

        $this->fail('Coupon was not applied to existing customer.');
    }

    public function test_yearly_to_monthly_properly_prorates()
    {
        $owner = User::create([
            'email' => 'josue.artaud@gmail.com',
            'name' => 'Josué Artaud',
        ]);

        // Create Subscription
        $owner->newSubscription('main', 'yearly-100-1')->create($this->getTestToken());

        $this->assertEquals(1, count($owner->subscriptions));
        $this->assertNotNull($owner->subscription('main')->braintree_id);

        // Swap To Monthly
        $owner->subscription('main')->swap('monthly-10-1');
        $owner = $owner->fresh();

        $this->assertEquals(2, count($owner->subscriptions));
        $this->assertNotNull($owner->subscription('main')->braintree_id);
        $this->assertEquals('monthly-10-1', $owner->subscription('main')->braintree_plan);

        $braintreeSubscription = $owner->subscription('main')->asBraintreeSubscription();

        foreach ($braintreeSubscription->discounts as $discount) {
            if ($discount->id === 'plan-credit') {
                $this->assertEquals('10.00', $discount->amount);
                $this->assertEquals(9, $discount->numberOfBillingCycles);

                return;
            }
        }

        $this->fail('Proration when switching to yearly was not done properly.');
    }

    public function test_monthly_to_yearly_properly_prorates()
    {
        $owner = User::create([
            'email' => 'josue.artaud@gmail.com',
            'name' => 'Josué Artaud',
        ]);

        // Create Subscription
        $owner->newSubscription('main', 'yearly-100-1')->create($this->getTestToken());

        $this->assertEquals(1, count($owner->subscriptions));
        $this->assertNotNull($owner->subscription('main')->braintree_id);

        // Swap To Monthly
        $owner->subscription('main')->swap('monthly-10-1');
        $owner = $owner->fresh();

        // Swap Back To Yearly
        $owner->subscription('main')->swap('yearly-100-1');
        $owner = $owner->fresh();

        $this->assertEquals(3, count($owner->subscriptions));
        $this->assertNotNull($owner->subscription('main')->braintree_id);
        $this->assertEquals('yearly-100-1', $owner->subscription('main')->braintree_plan);

        $braintreeSubscription = $owner->subscription('main')->asBraintreeSubscription();

        foreach ($braintreeSubscription->discounts as $discount) {
            if ($discount->id === 'plan-credit') {
                $this->assertEquals('90.00', $discount->amount);
                $this->assertEquals(1, $discount->numberOfBillingCycles);

                return;
            }
        }

        $this->fail('Proration when switching to yearly was not done properly.');
    }

    public function test_marking_as_cancelled_from_webhook()
    {
        $owner = User::create([
            'email' => 'josue.artaud@gmail.com',
            'name' => 'Josué Artaud',
        ]);

        // Create Subscription
        $owner->newSubscription('main', 'monthly-10-1')->create($this->getTestToken());

        // Perform Request to Webhook
        $request = Request::create('/', 'POST', [], [], [], [], json_encode(['kind' => 'SubscriptionCanceled',
            'subscription' => [
                'id' => $owner->subscription('main')->braintree_id,
            ],
        ]));
        $response = (new CashierTestControllerStub)->handleWebhook($request);

        $this->assertEquals(200, $response->getStatusCode());

        $owner = $owner->fresh();
        $subscription = $owner->subscription('main');

        $this->assertTrue($subscription->cancelled());
    }

    public function test_marking_subscription_cancelled_on_grace_period_as_cancelled_now_from_webhook()
    {
        $owner = User::create([
            'email' => 'josue.artaud@gmail.com',
            'name' => 'Josué Artaud',
        ]);

        // Create Subscription
        $owner->newSubscription('main', 'monthly-10-1')->create($this->getTestToken());

        // Cancel Subscription
        $subscription = $owner->subscription('main');
        $subscription->cancel();

        $this->assertTrue($subscription->onGracePeriod());

        // Perform Request to Webhook
        $request = Request::create('/', 'POST', [], [], [], [], json_encode(['kind' => 'SubscriptionCanceled',
            'subscription' => [
                'id' => $subscription->braintree_id,
            ],
        ]));
        $response = (new CashierTestControllerStub)->handleWebhook($request);

        $this->assertEquals(200, $response->getStatusCode());

        $owner = $owner->fresh();
        $subscription = $owner->subscription('main');

        $this->assertFalse($subscription->onGracePeriod());
    }
}

class User extends Model
{
    use \Jartaud\LaravelBraintreeSubscription\Billable;
}

class CashierTestControllerStub extends WebhookController
{
    /**
     * Parse the given Braintree webhook notification request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Braintree\WebhookNotification
     */
    protected function parseBraintreeNotification($request)
    {
        return json_decode($request->getContent());
    }
}
