{{-- resources/views/components/application-logo.blade.php --}}
@props(['alt' => 'Logo'])

<img
  src="{{ asset('images/logoEnro.png') }}"   {{-- usa .png si ese es el archivo real --}}
  alt="{{ $alt }}"
  {{ $attributes->merge(['class' => 'block h-12 w-auto']) }}
/>
