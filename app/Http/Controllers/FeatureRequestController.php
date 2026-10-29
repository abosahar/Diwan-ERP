<?php

namespace App\Http\Controllers;

use App\Models\FeatureRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Feature / improvement requests from the company's users. Anyone signed in may
 * submit and vote; changing the status needs «admin.feature_requests.manage».
 */
class FeatureRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(array_merge(array_keys(FeatureRequest::STATUSES), ['open', 'all']))],
            'kind' => ['nullable', Rule::in(array_keys(FeatureRequest::KINDS))],
            'sort' => 'nullable|in:votes,latest',
            'mine' => 'nullable|boolean',
        ]);
        $status = $filters['status'] ?? 'open';
        $userId = $request->user()->id;

        $requests = FeatureRequest::with('author:id,name')
            ->withExists(['voters as voted' => fn ($q) => $q->where('users.id', $userId)])
            ->when($status === 'open', fn ($q) => $q->whereNotIn('status', ['done', 'declined']))
            ->when(! in_array($status, ['open', 'all'], true), fn ($q) => $q->where('status', $status))
            ->when($filters['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))
            ->when($filters['mine'] ?? false, fn ($q) => $q->where('user_id', $userId))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%")))
            ->when(($filters['sort'] ?? 'votes') === 'votes', fn ($q) => $q->orderByDesc('votes_count'))
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('FeatureRequests/Index', [
            'requests' => $requests,
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $status, 'kind' => $filters['kind'] ?? '', 'sort' => $filters['sort'] ?? 'votes', 'mine' => (bool) ($filters['mine'] ?? false)],
            'statuses' => FeatureRequest::STATUSES,
            'kinds' => FeatureRequest::KINDS,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('FeatureRequests/Create', ['kinds' => FeatureRequest::KINDS]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(FeatureRequest::KINDS))],
            'area' => 'nullable|string|max:100',
            'title' => 'required|string|max:200',
            'description' => 'required|string|max:5000',
        ]);

        $featureRequest = FeatureRequest::create($data + ['user_id' => $request->user()->id, 'status' => 'new']);
        $featureRequest->voters()->attach($request->user()->id);
        $featureRequest->update(['votes_count' => 1]);

        return redirect()->route('feature-requests.show', $featureRequest->id)->with('success', 'تم إرسال طلبك، شكرًا لمشاركتك');
    }

    public function show(Request $request, FeatureRequest $featureRequest): Response
    {
        $featureRequest->load('author:id,name');

        return Inertia::render('FeatureRequests/Show', [
            'item' => $featureRequest->toArray() + ['voted' => $featureRequest->voters()->where('users.id', $request->user()->id)->exists()],
            'statuses' => FeatureRequest::STATUSES,
            'kinds' => FeatureRequest::KINDS,
        ]);
    }

    /** Toggle the signed-in user's vote. */
    public function vote(Request $request, FeatureRequest $featureRequest)
    {
        $featureRequest->voters()->toggle($request->user()->id);
        $featureRequest->update(['votes_count' => $featureRequest->voters()->count()]);

        return back();
    }

    /** Administrators: status, note and the release it shipped in. */
    public function update(Request $request, FeatureRequest $featureRequest)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(FeatureRequest::STATUSES))],
            'admin_note' => 'nullable|string|max:2000',
            'released_in' => 'nullable|string|max:20',
        ]);

        $featureRequest->update($data);

        return back()->with('success', 'تم تحديث الطلب');
    }
}
