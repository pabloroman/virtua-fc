@props(['notifications', 'game'])

{{-- The inbox body: department filter tabs plus the notification rows, filtered
     client-side by the tab selection. Owns its own Alpine `dept` scope so it can
     drop into any inbox surface (dashboard cards, header dropdown) with a single
     line. Expects a flat collection of GameNotification. --}}
<div x-data="{ dept: 'all' }">
    <x-notification-department-tabs :notifications="$notifications" />
    <div class="divide-y divide-border-default">
        @foreach($notifications as $notification)
            <div x-show="dept === 'all' || dept === @js($notification->getDepartment())">
                <x-notification-row :notification="$notification" :game="$game" />
            </div>
        @endforeach
    </div>
</div>
