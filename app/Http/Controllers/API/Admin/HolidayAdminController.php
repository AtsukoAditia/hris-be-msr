<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Holiday\StoreHolidayRequest;
use App\Http\Requests\Holiday\UpdateHolidayRequest;
use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HolidayAdminController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Holiday::query()->orderBy('date');

        if ($request->filled('year')) {
            $query->whereYear('date', $request->input('year'));
        }

        if ($request->boolean('is_recurring')) {
            $query->where('is_recurring', true);
        }

        $holidays = $query->paginate(50);

        return HolidayResource::collection($holidays);
    }

    public function store(StoreHolidayRequest $request): JsonResponse
    {
        $holiday = Holiday::create($request->validated());

        return response()->json([
            'message' => 'Holiday created successfully.',
            'data' => new HolidayResource($holiday),
        ], 201);
    }

    public function show(Holiday $holiday): HolidayResource
    {
        return new HolidayResource($holiday);
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday): JsonResponse
    {
        $holiday->update($request->validated());

        return response()->json([
            'message' => 'Holiday updated successfully.',
            'data' => new HolidayResource($holiday),
        ]);
    }

    public function destroy(Holiday $holiday): JsonResponse
    {
        $holiday->delete();

        return response()->json([
            'message' => 'Holiday deleted successfully.',
        ]);
    }
}
