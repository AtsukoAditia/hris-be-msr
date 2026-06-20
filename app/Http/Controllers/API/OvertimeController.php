<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Overtime\ApproveOvertimeRequest;
use App\Http\Requests\Overtime\IndexOvertimeRequest;
use App\Http\Requests\Overtime\RecordActualOvertimeRequest;
use App\Http\Requests\Overtime\RejectOvertimeRequest;
use App\Http\Requests\Overtime\StoreOvertimeRequest;
use App\Http\Resources\OvertimeRequestResource;
use App\Models\OvertimeRequest;
use App\Policies\OvertimeRequestPolicy;
use App\Services\OvertimeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OvertimeController extends Controller
{
    public function __construct(
        private readonly OvertimeService $overtimeService,
        private readonly OvertimeRequestPolicy $policy,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function index(IndexOvertimeRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($this->policy->viewAny($user), 403);

        $paginator = $this->overtimeService->list($request->validated(), $user);

        return OvertimeRequestResource::collection($paginator);
    }

    /**
     * @throws AuthorizationException
     */
    public function my(IndexOvertimeRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($this->policy->viewAny($user), 403);

        $filters = $request->validated();
        $filters['employee_id'] = $user->employee?->id ?? 0;
        $paginator = $this->overtimeService->list($filters, $user);

        return OvertimeRequestResource::collection($paginator);
    }

    /**
     * @throws AuthorizationException
     */
    public function store(StoreOvertimeRequest $request): JsonResponse
    {
        abort_unless($this->policy->create($request->user()), 403);

        $overtimeRequest = $this->overtimeService->submit($request->validated(), $request->user());

        return (new OvertimeRequestResource($overtimeRequest->load(['employee.user', 'overtimePolicy'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @throws AuthorizationException
     */
    public function show(Request $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        abort_unless($this->policy->view($request->user(), $overtimeRequest), 403);

        return new OvertimeRequestResource($overtimeRequest->load(['employee.user', 'overtimePolicy', 'approver']));
    }

    /**
     * @throws AuthorizationException
     */
    public function cancel(Request $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        abort_unless($this->policy->cancel($request->user(), $overtimeRequest), 403);

        $overtimeRequest = $this->overtimeService->cancel($overtimeRequest);

        return new OvertimeRequestResource($overtimeRequest->load(['employee.user', 'overtimePolicy']));
    }

    /**
     * @throws AuthorizationException
     */
    public function approve(ApproveOvertimeRequest $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        abort_unless($this->policy->approve($request->user(), $overtimeRequest), 403);

        $overtimeRequest = $this->overtimeService->approve($overtimeRequest, $request->user());

        return new OvertimeRequestResource($overtimeRequest);
    }

    /**
     * @throws AuthorizationException
     */
    public function reject(RejectOvertimeRequest $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        abort_unless($this->policy->reject($request->user(), $overtimeRequest), 403);

        $overtimeRequest = $this->overtimeService->reject(
            $overtimeRequest,
            $request->user(),
            $request->validated('rejection_reason')
        );

        return new OvertimeRequestResource($overtimeRequest->load(['employee.user', 'overtimePolicy']));
    }

    /**
     * @throws AuthorizationException
     */
    public function recordActual(RecordActualOvertimeRequest $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        abort_unless($this->policy->recordActual($request->user(), $overtimeRequest), 403);

        $overtimeRequest = $this->overtimeService->recordActualMinutes(
            $overtimeRequest,
            $request->validated('actual_minutes')
        );

        return new OvertimeRequestResource($overtimeRequest->load(['employee.user', 'overtimePolicy']));
    }
}
