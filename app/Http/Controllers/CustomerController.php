<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filterAttributes($request);
        $customers = Customer::query()
            ->when($filters['search'] !== '', fn ($query) => $this->applySearchFilter($query, $filters['search']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->latest()
            ->get();

        return view('pages.contacts.index', [
            'title' => 'Pelanggan',
            'entityLabel' => 'Pelanggan',
            'entityName' => 'customer',
            'entityPlural' => 'pelanggan',
            'routePath' => '/customers',
            'items' => $customers
                ->map(fn (Customer $customer): array => $this->payload($customer))
                ->values(),
            'filters' => $filters,
            'loyaltyStats' => [
                'stamps' => (int) $customers->sum('loyalty_stamp_count'),
                'fiftyRewardCustomers' => $customers->where('loyalty_fifty_reward_available', true)->count(),
                'freeCupRewardCustomers' => $customers->where('loyalty_free_reward_available', true)->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = Customer::query()->create($this->validateCustomer($request));

        return response()->json([
            'message' => 'Pelanggan berhasil dibuat.',
            'customer' => $this->payload($customer),
            'item' => $this->payload($customer),
        ], 201);
    }

    public function update(Request $request, string $code): JsonResponse
    {
        $customer = Customer::query()->firstOrNew(['code' => $code]);
        $customer->fill($this->validateCustomer($request, $code))->save();

        return response()->json([
            'message' => "Pelanggan {$code} berhasil diperbarui.",
            'customer' => $this->payload($customer),
            'item' => $this->payload($customer),
        ]);
    }

    private function validateCustomer(Request $request, ?string $currentCode = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('customers', 'code')->ignore($currentCode, 'code'),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'status' => ['required', 'string', 'in:aktif,nonaktif'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function payload(Customer $customer): array
    {
        return [
            'name' => $customer->name,
            'code' => $customer->code,
            'phone' => $customer->phone ?? '',
            'email' => $customer->email ?? '',
            'status' => $this->statusLabel($customer->status),
            'address' => $customer->address ?? '',
            'loyaltyStamps' => $customer->loyalty_stamp_count,
            'loyaltyFiftyAvailable' => $customer->loyalty_fifty_reward_available,
            'loyaltyFreeCupAvailable' => $customer->loyalty_free_reward_available,
        ];
    }

    private function filterAttributes(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['aktif', 'nonaktif'])],
        ]);

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'status' => $validated['status'] ?? '',
        ];
    }

    private function applySearchFilter($query, string $search): void
    {
        $query->where(function ($nested) use ($search): void {
            $nested->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }

    private function statusLabel(string $status): string
    {
        return [
            'aktif' => 'Aktif',
            'nonaktif' => 'Nonaktif',
        ][$status] ?? 'Aktif';
    }
}
