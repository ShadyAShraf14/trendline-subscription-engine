<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_only_active_plans(): void
    {
        Plan::create([
            'name' => 'Visible Plan',
            'description' => 'Shown publicly',
            'trial_days' => 7,
            'is_active' => true,
        ]);

        Plan::create([
            'name' => 'Hidden Plan',
            'description' => 'Should not be listed',
            'trial_days' => 0,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/plans');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Visible Plan');
    }

    public function test_authenticated_user_can_create_plan_with_multiple_prices(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/plans', [
            'name' => 'Growth Plan',
            'description' => 'Scaling package',
            'trial_days' => 14,
            'is_active' => true,
            'prices' => [
                [
                    'currency' => 'USD',
                    'billing_cycle' => 'monthly',
                    'amount' => 19.99,
                ],
                [
                    'currency' => 'AED',
                    'billing_cycle' => 'yearly',
                    'amount' => 799.00,
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Growth Plan')
            ->assertJsonCount(2, 'data.prices');

        $this->assertDatabaseHas('plans', [
            'name' => 'Growth Plan',
            'trial_days' => 14,
        ]);

        $this->assertDatabaseHas('plan_prices', [
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'amount' => 19.99,
            'is_active' => true,
        ]);
    }

    public function test_authenticated_user_can_update_plan_and_deactivate_missing_prices(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $plan = Plan::create([
            'name' => 'Business Plan',
            'description' => 'Original description',
            'trial_days' => 0,
            'is_active' => true,
        ]);

        $monthly = $plan->prices()->create([
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'amount' => 25.00,
            'is_active' => true,
        ]);

        $yearly = $plan->prices()->create([
            'currency' => 'USD',
            'billing_cycle' => 'yearly',
            'amount' => 250.00,
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/plans/{$plan->id}", [
            'name' => 'Business Plan Updated',
            'description' => 'Updated description',
            'trial_days' => 3,
            'is_active' => true,
            'prices' => [
                [
                    'currency' => 'USD',
                    'billing_cycle' => 'monthly',
                    'amount' => 29.99,
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Business Plan Updated')
            ->assertJsonCount(1, 'data.prices');

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => 'Business Plan Updated',
            'trial_days' => 3,
        ]);

        $this->assertDatabaseHas('plan_prices', [
            'id' => $monthly->id,
            'is_active' => true,
            'amount' => 29.99,
        ]);

        $this->assertDatabaseHas('plan_prices', [
            'id' => $yearly->id,
            'is_active' => false,
        ]);
    }
}