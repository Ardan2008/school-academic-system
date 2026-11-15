<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function suggest(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));

        if ($user === null) {
            return response()->json(['results' => [], 'groups' => []])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $service = app(GlobalSearchService::class);

        if (mb_strlen($q) < 1) {
            $results = array_slice($service->initial($user), 0, 8);
            $groups = collect($results)->groupBy(fn ($i) => $i['group'] ?? 'Menu')->map(fn ($c, $k) => ['group' => $k, 'items' => $c->values()])->values()->all();

            return response()->json(['results' => $results, 'groups' => $groups, 'initial' => true])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $results = $service->search($user, $q, 10);

        return response()->json([
            'results' => $results,
            'groups' => collect($results)->groupBy(fn ($i) => $i['group'] ?? 'Lainnya')->map(fn ($c, $k) => ['group' => $k, 'items' => $c->values()])->values()->all(),
            'initial' => false,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function search(Request $request)
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));

        if ($user === null || mb_strlen($q) < 1) {
            return redirect()->back();
        }

        $service = app(GlobalSearchService::class);
        $results = $service->search($user, $q, 50);

        return view('dashboard.search.results', [
            'q' => $q,
            'results' => $results,
            'role' => $user->role,
        ]);
    }
}
