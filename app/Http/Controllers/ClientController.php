<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class ClientController extends Controller
{
    public function index() { return view('clients.index'); }
    public function create() { return view('clients.create'); }
    public function edit(int $id) { return view('clients.edit', compact('id')); }
    public function show(int $id) { return view('clients.show', compact('id')); }
    public function export(Request $request) { }
}
