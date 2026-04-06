<?php

namespace Tests\Feature;

use App\Enums\PaymentAttemptStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected PlanPrice $trialPlanPrice;
    protected PlanPrice $paidPlanPrice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $trialPlan = Plan::create([
            'name' => 'Trial Plan',
            'description' => 'Comes with free trial',
            'trial_days' => 7,
            'is_active' => true,
        ]);

        $this->trialPlanPrice = PlanPrice::create([
            'plan_id' => $trialPlan->id,
            'currency' => 'USD',
            'amount' => 10.00,
            'billing_cycle' => 'monthly',
            'is_active' => true,
        ]);

        $paidPlan = Plan::create([
            'name' => 'Paid Plan',
            'description' => 'No trial plan',
            'trial_days' => 0,
            'is_active' => true,
        ]);

        $this->paidPlanPrice = PlanPrice::create([
            'plan_id' => $paidPlan->id,
            'currency' => 'USD',
            'amount' => 30.00,
            'billing_cycle' => 'monthly',
            'is_active' => true,
        ]);
    }

    public function test_user_can_subscribe_to_trial_plan(): void
    {
        $response = $this->postJson('/api/subscriptions', [
            'plan_price_id' => $this->trialPlanPrice->id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', SubscriptionStatus::TRIALING->value);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->user->id,
            'plan_price_id' => $this->trialPlanPrice->id,
            'status' => SubscriptionStatus::TRIALING->value,
        ]);
    }

    public function test_non_trial_plan_requires_initial_payment_status(): void
    {
        $response = $this->postJson('/api/subscriptions', [
            'plan_price_id' => $this->paidPlanPrice->id,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['initial_payment_status']);
    }

    public function test_successful_initial_payment_creates_active_subscription(): void
    {
        $response = $this->postJson('/api/subscriptions', [
            'plan_price_id' => $this->paidPlanPrice->id,
            'initial_payment_status' => PaymentAttemptStatus::SUCCESS->value,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', SubscriptionStatus::ACTIVE->value);

        $subscription = Subscription::first();

        $this->assertNotNull($subscription->ends_at);
        $this->assertDatabaseHas('payment_attempts', [
            'subscription_id' => $subscription->id,
            'status' => PaymentAttemptStatus::SUCCESS->value,
        ]);
    }

    public function test_failed_initial_payment_creates_past_due_subscription_with_grace_period(): void
    {
        $response = $this->postJson('/api/subscriptions', [
            'plan_price_id' => $this->paidPlanPrice->id,
            'initial_payment_status' => PaymentAttemptStatus::FAILED->value,
            'failure_reason' => 'Card declined',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', SubscriptionStatus::PAST_DUE->value);

        $subscription = Subscription::first();

        $this->assertNotNull($subscription->grace_period_ends_at);

        $this->assertDatabaseHas('payment_attempts', [
            'subscription_id' => $subscription->id,
            'status' => PaymentAttemptStatus::FAILED->value,
            'failure_reason' => 'Card declined',
        ]);
    }

    public function test_successful_payment_on_past_due_subscription_reactivates_it(): void
    {
        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_price_id' => $this->paidPlanPrice->id,
            'status' => SubscriptionStatus::PAST_DUE,
            'started_at' => now(),
            'grace_period_ends_at' => now()->addDays(3),
        ]);

        $response = $this->postJson("/api/subscriptions/{$subscription->id}/payments", [
            'status' => PaymentAttemptStatus::SUCCESS->value,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::ACTIVE->value);

        $subscription->refresh();

        $this->assertNotNull($subscription->ends_at);
        $this->assertNull($subscription->grace_period_ends_at);
    }

    public function test_failed_payment_on_active_subscription_moves_it_to_past_due(): void
    {
        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_price_id' => $this->paidPlanPrice->id,
            'status' => SubscriptionStatus::ACTIVE,
            'started_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $response = $this->postJson("/api/subscriptions/{$subscription->id}/payments", [
            'status' => PaymentAttemptStatus::FAILED->value,
            'failure_reason' => 'Insufficient funds',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::PAST_DUE->value);

        $subscription->refresh();

        $this->assertNotNull($subscription->grace_period_ends_at);
    }

    public function test_expired_trial_transitions_to_past_due_after_command_runs(): void
    {
        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_price_id' => $this->trialPlanPrice->id,
            'status' => SubscriptionStatus::TRIALING,
            'started_at' => now()->subDays(7),
            'trial_ends_at' => now()->subMinute(),
        ]);

        $this->artisan('subscriptions:process')
            ->assertExitCode(0);

        $subscription->refresh();

        $this->assertEquals(SubscriptionStatus::PAST_DUE, $subscription->status);
        $this->assertNotNull($subscription->grace_period_ends_at);
    }

    public function test_expired_grace_period_transitions_to_canceled_after_command_runs(): void
    {
        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_price_id' => $this->paidPlanPrice->id,
            'status' => SubscriptionStatus::PAST_DUE,
            'started_at' => now()->subDays(4),
            'grace_period_ends_at' => now()->subMinute(),
        ]);

        $this->artisan('subscriptions:process')
            ->assertExitCode(0);

        $subscription->refresh();

        $this->assertEquals(SubscriptionStatus::CANCELED, $subscription->status);
        $this->assertNotNull($subscription->canceled_at);
    }

    public function test_user_can_cancel_subscription_manually(): void
    {
        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_price_id' => $this->paidPlanPrice->id,
            'status' => SubscriptionStatus::ACTIVE,
            'started_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $response = $this->postJson("/api/subscriptions/{$subscription->id}/cancel");

        $response
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::CANCELED->value);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => SubscriptionStatus::CANCELED->value,
        ]);
    }
}