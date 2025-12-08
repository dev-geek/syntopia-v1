@props([
    'variant' => 'main',
    'class' => 'logo',
    'alt' => 'Syntopia Logo',
    'width' => null,
])

@php
    $logoUrl = match($variant) {
        'sidebar' => asset('syntopia-logo.webp'),
        'main' => 'https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp',
        default => 'https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp',
    };
    
    $style = $width ? "width: {$width};" : '';
@endphp

<img src="{{ $logoUrl }}" alt="{{ $alt }}" class="{{ $class }}" @if($style) style="{{ $style }}" @endif>

