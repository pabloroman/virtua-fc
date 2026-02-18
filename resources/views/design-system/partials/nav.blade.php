@php
$groups = [
    'Foundation' => [
        ['id' => 'overview', 'label' => 'Overview'],
        ['id' => 'colors', 'label' => 'Colors'],
        ['id' => 'typography', 'label' => 'Typography'],
    ],
    'Components' => [
        ['id' => 'buttons', 'label' => 'Buttons'],
        ['id' => 'forms', 'label' => 'Forms'],
        ['id' => 'navigation', 'label' => 'Navigation'],
        ['id' => 'cards', 'label' => 'Cards & Containers'],
        ['id' => 'tables', 'label' => 'Tables'],
        ['id' => 'badges', 'label' => 'Badges & Pills'],
        ['id' => 'alerts', 'label' => 'Alerts'],
        ['id' => 'modals', 'label' => 'Modals'],
    ],
    'Data Visualization' => [
        ['id' => 'data-viz', 'label' => 'Bars & Sliders'],
    ],
    'Game' => [
        ['id' => 'game-components', 'label' => 'Game Components'],
        ['id' => 'shirt-badges', 'label' => 'Shirt Badges'],
    ],
    'Patterns' => [
        ['id' => 'layout-patterns', 'label' => 'Layout Patterns'],
    ],
];
@endphp

<div class="space-y-6">
    @foreach($groups as $groupName => $items)
        <div>
            <div class="px-3 mb-1 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">{{ $groupName }}</div>
            <div class="space-y-0.5">
                @foreach($items as $item)
                    <a href="#{{ $item['id'] }}"
                       @click="mobileNav = false"
                       class="block px-3 py-1.5 text-sm rounded-md transition-colors"
                       :class="activeSection === '{{ $item['id'] }}'
                           ? 'text-sky-600 font-semibold bg-sky-50 border-l-2 border-sky-500 -ml-px'
                           : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
