@extends('layouts.app')

@section('content')
  <div class="space-y-4">
    <x-ecommerce.ecommerce-metrics :stats="$metrics" />

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
      <x-ecommerce.monthly-sale :chart="$monthlySalesChart" />
      <x-ecommerce.statistics-chart :chart="$revenueChart" />
    </div>

    <x-ecommerce.recent-orders :products="$topProducts" />
  </div>
@endsection
