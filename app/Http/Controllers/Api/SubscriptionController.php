<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentAttemptStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordPaymentRequest;
use App\Http\Requests\SubscribeRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $subscriptions = $request->user()
            ->subscriptions()
            ->with(['planPrice.plan', 'latestPaymentAttempt'])
            ->latest()
            ->get();

        return SubscriptionResource::collection($subscriptions);
    }

    public function store(SubscribeRequest $request): JsonResponse
    {
        $planPrice = PlanPrice::query()
            ->whereKey($request->integer('plan_price_id'))
            ->where('is_active', true)
            ->whereHas('plan', fn ($query) => $query->where('is_active', true))
            ->firstOrFail();

        $initialPaymentStatus = $request->filled('initial_payment_status')
            ? PaymentAttemptStatus::from($request->string('initial_payment_status')->toString())
            : null;

        $subscription = $this->subscriptionService->subscribe(
            user: $request->user(),
            planPrice: $planPrice,
            initialPaymentStatus: $initialPaymentStatus,
            failureReason: $request->input('failure_reason')
        );

        $subscription->load(['planPrice.plan', 'latestPaymentAttempt']);

        return (new SubscriptionResource($subscription))
            ->response()
            ->setStatusCode(201);
    }

    public function current(Request $request): JsonResponse|SubscriptionResource
    {
        $subscription = $request->user()
            ->subscriptions()
            ->whereIn('status', $this->accessGrantingStatusValues())
            ->with(['planPrice.plan', 'latestPaymentAttempt'])
            ->latest()
            ->first();

        if (! $subscription) {
            return response()->json([
                'message' => 'No active subscription found.',
            ], 404);
        }

        return new SubscriptionResource($subscription);
    }

    public function show(Request $request, Subscription $subscription): SubscriptionResource
    {
        $this->ensureOwnership($request, $subscription);

        $subscription->load([
            'planPrice.plan',
            'latestPaymentAttempt',
            'paymentAttempts',
        ]);

        return new SubscriptionResource($subscription);
    }

    public function recordPayment(RecordPaymentRequest $request, Subscription $subscription): SubscriptionResource
    {
        $this->ensureOwnership($request, $subscription);

        $status = PaymentAttemptStatus::from($request->string('status')->toString());

        $subscription = $this->subscriptionService->recordPayment(
            subscription: $subscription,
            status: $status,
            failureReason: $request->input('failure_reason')
        );

        $subscription->load(['planPrice.plan', 'latestPaymentAttempt', 'paymentAttempts']);

        return new SubscriptionResource($subscription);
    }

    public function cancel(Request $request, Subscription $subscription): SubscriptionResource
    {
        $this->ensureOwnership($request, $subscription);

        $subscription = $this->subscriptionService->cancel($subscription);

        $subscription->load(['planPrice.plan', 'latestPaymentAttempt', 'paymentAttempts']);

        return new SubscriptionResource($subscription);
    }

    private function ensureOwnership(Request $request, Subscription $subscription): void
    {
        abort_unless(
            $subscription->user_id === $request->user()->id,
            403,
            'You do not own this subscription.'
        );
    }

    /**
     * @return array<int, string>
     */
    private function accessGrantingStatusValues(): array
    {
        return array_map(
            fn (SubscriptionStatus $status) => $status->value,
            SubscriptionStatus::accessGranting()
        );
    }
}