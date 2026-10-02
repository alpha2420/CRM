<?php

namespace App\Http\Controllers;

use App\Tasks\MyDay;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "My day": everything the signed-in person has to do today.
 */
class MyDayController extends Controller
{
    public function __invoke(Request $request, MyDay $myDay): View
    {
        $user = $request->user();

        return view('tasks.today', [
            'day' => $myDay->for($user),
            'people' => $user->isAdmin() ? $user->organization->users()->active()->orderBy('name')->get() : collect(),
        ]);
    }
}
