<?php
namespace App\Http\Controllers;
class HeadquarterController extends Controller
{
    public function index() { return view('headquarters.index'); }
    public function create() { return view('headquarters.create'); }
    public function edit(int $id) { return view('headquarters.edit', ['headquarterId' => $id]); }
}
