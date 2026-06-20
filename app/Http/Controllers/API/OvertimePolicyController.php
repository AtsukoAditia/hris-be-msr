<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\OvertimePolicyResource;
use App\Models\OvertimePolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OvertimePolicyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 100), 1), 100);

        $policies = OvertimePolicy::query()
            ->active()
            ->orderBy('name')
            ->paginate($perPage);

        return OvertimePolicyResource::collection($policies);
    }
}
