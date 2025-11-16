<?php

namespace App\Actions\Fortify;

use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request)
    {
        $isAdmin = (bool) $request->boolean('admin');


        $adminLoginRoute = 'admin.login'; // 例: GET /admin/login
        $userLoginRoute  = 'login';

        return redirect()->route($isAdmin ? $adminLoginRoute : $userLoginRoute);
    }
}