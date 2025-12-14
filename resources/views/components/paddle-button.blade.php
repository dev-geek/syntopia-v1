@props(['checkout', 'class' => '', 'id' => null])

@if($checkout && method_exists($checkout, 'url'))
    <x-paddle-button
        :url="$checkout->url"
        class="{{ $class }}"
        @if($id) id="{{ $id }}" @endif
    >
        {{ $slot }}
    </x-paddle-button>
@elseif($checkout && isset($checkout['checkout_url']))
    <x-paddle-button
        :url="$checkout['checkout_url']"
        class="{{ $class }}"
        @if($id) id="{{ $id }}" @endif
    >
        {{ $slot }}
    </x-paddle-button>
@else
    <button type="button" class="{{ $class }}" disabled>
        {{ $slot }}
    </button>
@endif
