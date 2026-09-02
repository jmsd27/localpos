@props(['name'])

<svg {{ $attributes->merge(['class' => 'h-5 w-5', 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.5']) }} stroke-linecap="round" stroke-linejoin="round">
    @switch($name)
        @case('home')
            <path d="M3 11.5 12 4l9 7.5" /><path d="M5 9.5V20h14V9.5" /><path d="M9.5 20v-6h5v6" />
            @break
        @case('shopping-cart')
            <circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" />
            <path d="M2.5 3h2l2.4 12.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 8H6" />
            @break
        @case('table-cells')
            <rect x="3.5" y="4" width="17" height="16" rx="1.5" />
            <path d="M3.5 10h17M3.5 15h17M9.5 4v16M15 4v16" />
            @break
        @case('fire')
            <path d="M12 3s-5 4.5-5 9.5a5 5 0 0 0 10 0c0-2-1-3-1.5-4 0 1.5-1 2.5-1.5 2.5.8-2.5-.5-5-2-8Z" />
            @break
        @case('banknotes')
            <rect x="2.5" y="6.5" width="19" height="12" rx="1.5" />
            <circle cx="12" cy="12.5" r="2.5" />
            <path d="M6 6.5v12M18 6.5v12" />
            @break
        @case('archive-box')
            <rect x="3" y="4" width="18" height="4.5" rx="1" />
            <path d="M4.5 8.5V19a1 1 0 0 0 1 1h13a1 1 0 0 0 1-1V8.5" />
            <path d="M10 12.5h4" />
            @break
        @case('clipboard-document-list')
            <rect x="6" y="4" width="12" height="17" rx="1.5" />
            <rect x="9" y="2.5" width="6" height="3" rx="1" />
            <path d="M9 11h6M9 14.5h6M9 18h3.5" />
            @break
        @case('truck')
            <rect x="2.5" y="7" width="12" height="9" rx="1" />
            <path d="M14.5 10h3.5l3.5 3.5V16h-7" />
            <circle cx="6.5" cy="17.5" r="1.6" /><circle cx="17" cy="17.5" r="1.6" />
            @break
        @case('chart-bar')
            <path d="M4 20V10M10 20V4M16 20v-7M20 20H4" />
            @break
        @case('users')
            <circle cx="8.5" cy="8" r="3" /><path d="M2.5 19c.5-3.5 3-5.5 6-5.5s5.5 2 6 5.5" />
            <circle cx="17" cy="9" r="2.3" /><path d="M15.5 13.2c2.3.2 4 1.9 4.5 5" />
            @break
        @case('printer')
            <rect x="5" y="8.5" width="14" height="7" rx="1" />
            <path d="M7.5 8.5V4h9v4.5M7.5 15.5V20h9v-4.5" />
            <circle cx="16" cy="11" r="0.8" fill="currentColor" stroke="none" />
            @break
        @case('building-storefront')
            <path d="M3.5 9.5 5 4h14l1.5 5.5" />
            <path d="M4 9.5v10h16v-10" />
            <path d="M9.5 19.5v-6h5v6" />
            <path d="M3.5 9.5a2.3 2.3 0 0 0 4.5.5 2.3 2.3 0 0 0 4.5 0 2.3 2.3 0 0 0 4.5 0 2.3 2.3 0 0 0 4.5-.5" />
            @break
        @case('cog-6-tooth')
            <circle cx="12" cy="12" r="3" />
            <path d="M12 3.5v2M12 18.5v2M4.5 12h2M17.5 12h2M6.5 6.5l1.4 1.4M16.1 16.1l1.4 1.4M17.5 6.5l-1.4 1.4M7.9 16.1l-1.4 1.4" />
            @break
        @case('shield-check')
            <path d="M12 3.5 19 6v6c0 5-3 7.8-7 8.5-4-.7-7-3.5-7-8.5V6l7-2.5Z" />
            <path d="M9 12.2l2 2 4-4.2" />
            @break
        @case('cloud-arrow-down')
            <path d="M7 18a4 4 0 0 1-.5-8 5.5 5.5 0 0 1 10.7-1.7A3.8 3.8 0 0 1 17.5 18H7Z" />
            <path d="M12 9.5v6m0 0 2.2-2.2M12 15.5l-2.2-2.2" />
            @break
        @case('tag')
            <path d="M11.5 3.5H5a1.5 1.5 0 0 0-1.5 1.5v6.5a1.5 1.5 0 0 0 .44 1.06l8.94 8.94a1.5 1.5 0 0 0 2.12 0l6.5-6.5a1.5 1.5 0 0 0 0-2.12l-8.94-8.94a1.5 1.5 0 0 0-1.06-.44Z" />
            <circle cx="8.3" cy="8.3" r="1.3" fill="currentColor" stroke="none" />
            @break
        @case('arrow-left-on-rectangle')
            <path d="M9 4h9v16H9" />
            <path d="M3 12h11.5M9 8l-4 4 4 4" />
            @break
        @case('bars-3')
            <path d="M3.5 6.5h17M3.5 12h17M3.5 17.5h17" />
            @break
        @case('chevron-down')
            <path d="M5.5 8.5 12 15l6.5-6.5" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('pencil')
            <path d="M4 20l1-4.2L15.3 5.5a1.7 1.7 0 0 1 2.4 0l.8.8a1.7 1.7 0 0 1 0 2.4L8.2 19 4 20Z" />
            <path d="M13.7 7.1l3.2 3.2" />
            @break
        @case('trash')
            <path d="M5 7h14M9.5 7V5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v2M7 7l1 13h8l1-13" />
            <path d="M10 11v6M14 11v6" />
            @break
        @case('magnifying-glass')
            <circle cx="10.5" cy="10.5" r="6.5" /><path d="M20 20l-5-5" />
            @break
        @case('x-mark')
            <path d="M6 6l12 12M18 6L6 18" />
            @break
        @case('check-circle')
            <circle cx="12" cy="12" r="8.5" /><path d="M8.5 12.3l2.4 2.4 4.6-5.4" />
            @break
        @case('exclamation-triangle')
            <path d="M12 4.5 21.5 20h-19L12 4.5Z" />
            <path d="M12 10v4M12 17h.01" />
            @break
        @case('envelope')
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="M4 6.5 12 13l8-6.5" />
            @break
        @case('lock-closed')
            <rect x="5" y="10.5" width="14" height="9.5" rx="1.8" />
            <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" />
            @break
        @case('eye')
            <path d="M2.5 12S5.7 5.5 12 5.5 21.5 12 21.5 12 18.3 18.5 12 18.5 2.5 12 2.5 12Z" />
            <circle cx="12" cy="12" r="2.8" />
            @break
        @case('eye-slash')
            <path d="M3.5 3.5l17 17" />
            <path d="M6.4 6.6C4 8.3 2.5 12 2.5 12S5.7 18.5 12 18.5c2 0 3.6-.6 4.9-1.4M9.7 6.1c.7-.2 1.5-.3 2.3-.3 6.3 0 9.5 6.5 9.5 6.5a15 15 0 0 1-2.6 3.6" />
            <path d="M9.9 10a2.8 2.8 0 0 0 4 4" />
            @break
        @case('qr-code')
            <rect x="3.5" y="3.5" width="6" height="6" rx="1" />
            <rect x="14.5" y="3.5" width="6" height="6" rx="1" />
            <rect x="3.5" y="14.5" width="6" height="6" rx="1" />
            <path d="M14.5 14.5h2.5v2.5h-2.5Z" />
            <path d="M20.5 14.5v2.5M14.5 20.5h2.5M18.5 18.5h2v2h-2Z" />
            @break
        @case('book-open')
            <path d="M12 6.5C10.5 5 8 4.5 5 4.75A1 1 0 0 0 4 5.75v11.5a1 1 0 0 0 1.1 1c2.8-.25 5.2.25 6.9 1.75" />
            <path d="M12 6.5C13.5 5 16 4.5 19 4.75A1 1 0 0 1 20 5.75v11.5a1 1 0 0 1-1.1 1c-2.8-.25-5.2.25-6.9 1.75" />
            <path d="M12 6.5V20" />
            @break
        @default
            <circle cx="12" cy="12" r="8.5" />
    @endswitch
</svg>
