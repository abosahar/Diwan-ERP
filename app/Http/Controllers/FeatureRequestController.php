<?php

namespace App\Http\Controllers;

use App\Services\SelfUpdate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Feature / improvement requests go to the developer, not to the company owner:
 * the page builds a GitHub issue on the product repository (the user's own GitHub
 * session submits it, so the install holds no token).
 */
class FeatureRequestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('FeatureRequests/Index', ['repo' => SelfUpdate::REPO]);
    }
}
