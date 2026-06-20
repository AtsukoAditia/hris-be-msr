<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OvertimePolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'daily_max_minutes' => $this->daily_max_minutes,
            'weekly_max_minutes' => $this->weekly_max_minutes,
            'rate_multiplier' => $this->rate_multiplier,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
