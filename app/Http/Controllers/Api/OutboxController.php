<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OutboxMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OutboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'state' => ['sometimes', Rule::in(['pending', 'acked'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(OutboxMessage::query()
            ->when(isset($filters['state']), fn ($query) => $query->where('state', $filters['state']))
            ->orderBy('created_at')->orderBy('id')
            ->paginate($filters['per_page'] ?? 20)->withQueryString());
    }

    public function acknowledge(OutboxMessage $message): JsonResponse
    {
        $updated = DB::transaction(function () use ($message): OutboxMessage {
            $locked = OutboxMessage::query()->lockForUpdate()->findOrFail($message->id);
            if ($locked->state === 'pending') {
                $locked->update([
                    'state' => 'acked',
                    'ack_actor' => mb_substr((string) config('services.atlas.client', 'local-client'), 0, 100),
                    'acked_at' => now(),
                ]);
            }

            return $locked->refresh();
        });

        return response()->json($updated);
    }
}
