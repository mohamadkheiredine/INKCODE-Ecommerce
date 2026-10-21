<?php

namespace App\Http\Controllers\Search;

use App\Actions\Search\SearchEverything;
use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    /**
     * The global search (Ctrl+K): up to five Orders, Products and Customers matching `q`,
     * grouped, each group present only when the User's Role may list that module.
     */
    public function index(SearchRequest $request, SearchEverything $searchEverything): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => (object) $searchEverything->handle($user, $request->search())]);
    }
}
