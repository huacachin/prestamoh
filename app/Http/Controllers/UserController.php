<?php

namespace App\Http\Controllers;

class UserController extends Controller
{
    public function index() { return view('users.index'); }
    public function create() { return view('users.create'); }
    public function edit(int $user) { return view('users.edit', compact('user')); }
    public function perms(int $user) { return view('users.perms', ['userId' => $user]); }
}
