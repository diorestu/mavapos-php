<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filterAttributes($request);

        return view('pages.contacts.index', [
            'title' => 'Supplier',
            'entityLabel' => 'Supplier',
            'entityName' => 'supplier',
            'entityPlural' => 'supplier',
            'routePath' => '/suppliers',
            'items' => Supplier::query()
                ->when($filters['search'] !== '', fn ($query) => $this->applySearchFilter($query, $filters['search']))
                ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
                ->latest()
                ->get()
                ->map(fn (Supplier $supplier): array => $this->payload($supplier))
                ->values(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $supplier = Supplier::query()->create($this->validateSupplier($request));

        return response()->json([
            'message' => 'Supplier berhasil dibuat.',
            'supplier' => $this->payload($supplier),
            'item' => $this->payload($supplier),
        ], 201);
    }

    public function update(Request $request, string $code): JsonResponse
    {
        $supplier = Supplier::query()->firstOrNew(['code' => $code]);
        $supplier->fill($this->validateSupplier($request, $code))->save();

        return response()->json([
            'message' => "Supplier {$code} berhasil diperbarui.",
            'supplier' => $this->payload($supplier),
            'item' => $this->payload($supplier),
        ]);
    }

    private function validateSupplier(Request $request, ?string $currentCode = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('suppliers', 'code')->ignore($currentCode, 'code'),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'status' => ['required', 'string', 'in:aktif,nonaktif'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function payload(Supplier $supplier): array
    {
        return [
            'name' => $supplier->name,
            'code' => $supplier->code,
            'phone' => $supplier->phone ?? '',
            'email' => $supplier->email ?? '',
            'status' => $this->statusLabel($supplier->status),
            'address' => $supplier->address ?? '',
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
