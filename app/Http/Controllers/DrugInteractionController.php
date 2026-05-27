<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DrugInteractionController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'drugs' => 'required|array|min:2',
            'drugs.*' => 'required|string',
        ]);

        $drugs = array_map('strtolower', $request->input('drugs'));

        $allInteractions = Cache::remember('ddi_interactions', 3600, function () {
            $path = public_path('data/interactions.json');

            return json_decode(file_get_contents($path), true);
        });

        $found = [];

        foreach ($allInteractions as $interaction) {
            $d1 = strtolower($interaction['drug1']);
            $d2 = strtolower($interaction['drug2']);

            if (in_array($d1, $drugs) && in_array($d2, $drugs)) {
                $found[] = $interaction;
            }
        }

        return response()->json([
            'interactions' => $found,
            'count' => count($found),
        ]);
    }
}
