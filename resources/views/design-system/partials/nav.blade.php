@php
$groups = [
    'Foundation' => [
        ['id' => 'overview', 'label' => 'Overview'],
        ['id' => 'logo-brand', 'label' => 'Logo & Brand'],
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
            <div class="px-3 mb-1 text-[10px] font-heading font-semibold text-text-faint uppercase tracking-widest">{{ $groupName }}</div>
            <div class="space-y-0.5">
                @foreach($items as $item)
                    <a href="#{{ $item['id'] }}"
                       @click="mobileNav = false"
                       class="block px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                       :class="activeSection === '{{ $item['id'] }}'
                           ? 'text-accent-blue bg-accent-blue/10 border-l-2 border-accent-blue -ml-px'
                           : 'text-text-secondary hover:text-text-primary hover:bg-white/5'">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
