@extends('layouts.app')

@section('content')
  <div class="space-y-4">
    @php
      $subscriptionTone = match ($subscriptionStatus['tone']) {
        'success' => 'border-success-200 bg-success-50 text-success-700 dark:border-success-500/20 dark:bg-success-500/10 dark:text-success-300',
        'warning' => 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-500/20 dark:bg-warning-500/10 dark:text-warning-300',
        default => 'border-error-200 bg-error-50 text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300',
      };
    @endphp

    <div class="flex flex-col gap-3 rounded-xl border px-4 py-3 {{ $subscriptionTone }} sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="text-sm font-semibold">{{ $subscriptionStatus['label'] }}</p>
        <p class="mt-0.5 text-xs">{{ $subscriptionStatus['description'] }}</p>
      </div>
      @if ($subscriptionStatus['showAction'])
        <a href="{{ route('billings') }}" class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg bg-gray-900 px-3 text-xs font-semibold text-white transition hover:bg-gray-800 dark:bg-white dark:text-gray-900">
          Perpanjang sekarang
        </a>
      @endif
    </div>

    <x-ecommerce.ecommerce-metrics :stats="$metrics" />

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
      <x-ecommerce.monthly-sale :chart="$monthlySalesChart" />
      <x-ecommerce.statistics-chart :chart="$revenueChart" />
    </div>

    <x-ecommerce.recent-orders :products="$topProducts" />
  </div>
@endsection
