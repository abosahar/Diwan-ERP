<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/** «أبرز التحديثات»: what changed in each release (config/release_notes.php). */
class ReleaseNotesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('WhatsNew/Index', ['releases' => config('release_notes')]);
    }
}
