<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        return view('pages.contacts.index', [
            'title' => 'Pelanggan',
            'entityLabel' => 'Pelanggan',
            'entityName' => 'customer',
            'entityPlural' => 'pelanggan',
            'routePath' => '/customers',
            'items' => Customer::query()
                ->latest()
                ->get()
                ->map(fn (Customer $customer): array => $this->payload($customer))
                ->values(),
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
        ];
    }

    private function statusLabel(string $status): string
    {
        return [
            'aktif' => 'Aktif',
            'nonaktif' => 'Nonaktif',
        ][$status] ?? 'Aktif';
    }
}
