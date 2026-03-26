@props([
    'href',
    'active' => false,
    'mobile' => false,
])

@if($mobile)
    <a href="{{ $href }}"
       {{ $attributes->merge([
           'class' => 'block px-3 py-2 rounded-lg text-sm font-medium transition-colors ' .
               ($active
                   ? 'bg-indigo-50 text-indigo-700'
                   : 'text-gray-600 hover:bg-gray-100 hover:text-gray-800')
       ]) }}>
        {{ $slot }}
    </a>
@else
    <a href="{{ $href }}"
       {{ $attributes->merge([
           'class' => 'px-3 py-2 rounded-lg text-sm font-medium transition-colors ' .
               ($active
                   ? 'bg-indigo-50 text-indigo-700'
                   : 'text-gray-600 hover:bg-gray-100 hover:text-gray-800')
       ]) }}>
        {{ $slot }}
    </a>
@endif
