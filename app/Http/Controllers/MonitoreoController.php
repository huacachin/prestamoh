<?php

namespace App\Http\Controllers;

/** Monitoreo (16/10/2026): sesiones abiertas de todos los usuarios, solo director. */
class MonitoreoController extends Controller
{
    public function index()
    {
        return view('monitoreo.index');
    }
}
