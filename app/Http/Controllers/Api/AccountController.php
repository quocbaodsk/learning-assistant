<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
  public function updateInfo(Request $request)
  {
    $payload = $request->validate([
      'name'     => 'required|string|max:255',
      'email'    => 'nullable|string|email|max:255|unique:users,email,' . Auth::id(),
      'gender'   => 'nullable|string|max:255',
      'language' => 'nullable|string|max:255',
    ]);

    $user = User::find(Auth::id());

    if ($user) {
      $user->update($payload);

      return response()->json(['status' => 200, 'message' => 'User info updated successfully.']);
    }

    return response()->json(['status' => 400, 'message' => 'User not found.'], 400);
  }

  public function updatePassword(Request $request)
  {
    $payload = $request->validate([
      'new_password'     => 'required|string|min:6|confirmed',
      'current_password' => 'required|string',
    ]);

    $user = User::find(Auth::id());

    if ($user && password_verify($payload['current_password'], $user->password)) {
      $user->update(['password' => bcrypt($payload['new_password'])]);

      return response()->json(['status' => 200, 'message' => 'Password updated successfully.']);
    }

    return response()->json(['status' => 400, 'message' => 'Current password is incorrect.'], 400);
  }
}
