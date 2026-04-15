<?php

namespace App\Http\Controllers;

class ConceptController extends Controller
{
    public function index() { return view('concepts.index'); }
    public function create() { return view('concepts.create'); }
    public function edit(int $id) { return view('concepts.edit', ['conceptId' => $id]); }
}
