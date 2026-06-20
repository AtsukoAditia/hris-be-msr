<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Normalize common boolean query-string representations before validation.
     *
     * Browsers and Axios serialize boolean query params as strings such as
     * "true" and "false", while Laravel's boolean validation rule only accepts
     * true, false, 1, 0, "1", and "0". Invalid values are intentionally left
     * untouched so the controller validation can still reject them.
     */
    protected function normalizeBooleanQuery(Request $request, string ...$keys): void
    {
        foreach ($keys as $key) {
            if (! $request->has($key)) {
                continue;
            }

            $normalized = filter_var(
                $request->input($key),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            );

            if ($normalized !== null) {
                $request->merge([$key => $normalized]);
            }
        }
    }
}
