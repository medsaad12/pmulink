<?php

namespace App\Http\Responses;

use App\Http\Middleware\ResolveTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Always route authenticated users through the organization picker (never
     * url.intended). The picker forwards single-organization users straight to
     * the whiteboard, while members of several organizations get to choose.
     *
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $request->session()->forget('url.intended');
        $request->session()->forget(ResolveTenant::SESSION_KEY);

        return redirect()->route('organizations.select');
    }
}
