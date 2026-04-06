<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PlanController extends Controller
{
    public function __construct(
        private readonly PlanService $planService
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->with([
                'prices' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('currency')
                    ->orderBy('billing_cycle'),
            ])
            ->get();

        return PlanResource::collection($plans);
    }

    public function show(Plan $plan): PlanResource
    {
        abort_unless($plan->is_active, 404, 'Plan not found.');

        $plan->load([
            'prices' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('currency')
                ->orderBy('billing_cycle'),
        ]);

        return new PlanResource($plan);
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->planService->upsert($request->validated());

        $plan->load([
            'prices' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('currency')
                ->orderBy('billing_cycle'),
        ]);

        return (new PlanResource($plan))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePlanRequest $request, Plan $plan): PlanResource
    {
        $plan = $this->planService->upsert($request->validated(), $plan);

        $plan->load([
            'prices' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('currency')
                ->orderBy('billing_cycle'),
        ]);

        return new PlanResource($plan);
    }

    public function destroy(Plan $plan): Response
    {
        $this->planService->deactivate($plan);

        return response()->noContent();
    }
}