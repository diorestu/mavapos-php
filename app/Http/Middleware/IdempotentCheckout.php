<?php

namespace App\Http\Middleware;

use App\Models\CheckoutRequest;
use App\Models\User;
use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class IdempotentCheckout
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Idempotency-Key');
        $body = $request->input('client_transaction_id');
        if ($header === null && $body === null) {
            return $next($request);
        }

        Validator::make(
            ['header' => $header, 'client_transaction_id' => $body],
            ['header' => [$header === null ? 'nullable' : 'required', 'uuid'], 'client_transaction_id' => ['nullable', 'uuid']],
        )->validate();
        if ($header !== null && $body !== null && strtolower($header) !== strtolower($body)) {
            throw ValidationException::withMessages(['client_transaction_id' => 'ID transaksi tidak sama dengan Idempotency-Key.']);
        }
        $key = strtolower($header ?? $body);
        $tenantId = $request->user()->tenantOwnerId();
        $actorId = (int) $request->user()->id;
        $branchId = app(BranchContext::class)->activeId();
        if ($request->filled('client_created_at')) {
            $captured = Validator::make($request->only(['client_created_at', 'cashier_id']), [
                'client_created_at' => ['required', 'date'],
                'cashier_id' => ['required', 'integer', 'min:1'],
            ])->validate();
            $cashierId = (int) $captured['cashier_id'];
        } else {
            $cashierId = User::query()
                ->whereKey((int) session('pos_active_cashier_id', $actorId))
                ->where('tenant_owner_id', $tenantId)
                ->whereIn('role', ['owner', 'admin', 'kasir'])
                ->value('id') ?? $actorId;
        }
        $payload = $request->except(['client_transaction_id', '_token']);
        $hash = hash('sha256', json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        return DB::transaction(function () use ($request, $next, $tenantId, $actorId, $cashierId, $branchId, $key, $hash): Response {
            // Updating the existing owner row locks even an absent UUID, including on SQLite.
            DB::table('users')->where('id', $tenantId)->update(['updated_at' => DB::raw('updated_at')]);
            $stored = CheckoutRequest::query()
                ->where('tenant_owner_id', $tenantId)
                ->where('client_transaction_id', $key)
                ->first();
            if ($stored) {
                if ($stored->actor_id !== $actorId || $stored->cashier_id !== (int) $cashierId || $stored->branch_id !== $branchId || ! hash_equals($stored->payload_hash, $hash)) {
                    return response()->json(['message' => 'ID transaksi sudah digunakan dengan kasir, cabang, atau data berbeda.'], 409);
                }

                return response($stored->response_body, $stored->response_status, [
                    'Content-Type' => 'application/json', 'Idempotent-Replayed' => 'true',
                ]);
            }

            $response = $next($request);
            if (! $response->isSuccessful()) {
                throw new HttpResponseException($response);
            }
            CheckoutRequest::query()->create([
                'tenant_owner_id' => $tenantId, 'client_transaction_id' => $key,
                'actor_id' => $actorId, 'cashier_id' => $cashierId, 'branch_id' => $branchId,
                'payload_hash' => $hash, 'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
            ]);

            return $response;
        }, 5);
    }

    private function canonicalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $child) {
            if (is_array($child)) {
                $value[$key] = $this->canonicalize($child);
            }
        }

        return $value;
    }
}
