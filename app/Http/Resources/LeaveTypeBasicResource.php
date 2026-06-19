<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveTypeBasicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'requires_balance' => $this->requires_balance,
            'max_days' => $this->max_days,
        ];
    }
}
