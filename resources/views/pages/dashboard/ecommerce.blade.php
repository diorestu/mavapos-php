@extends('layouts.app')

@section('content')
  <div class="space-y-4">
    <x-ecommerce.ecommerce-metrics />

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
      <x-ecommerce.monthly-sale />
      <x-ecommerce.statistics-chart />
    </div>

    <x-ecommerce.recent-orders />
  </div>
@endsection
