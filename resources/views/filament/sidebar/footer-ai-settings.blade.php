@php
    use App\Filament\Pages\AiSettings;

    $url = AiSettings::getUrl();
    $active = request()->routeIs(AiSettings::getNavigationItemActiveRoutePattern());
@endphp

<div class="fi-sidebar-footer">
    <ul class="fi-sidebar-nav-groups">
        <x-filament-panels::sidebar.item
            :active="$active"
            :active-icon="AiSettings::getActiveNavigationIcon()"
            :icon="AiSettings::getNavigationIcon()"
            :url="$url"
        >
            {{ AiSettings::getNavigationLabel() }}
        </x-filament-panels::sidebar.item>
    </ul>
</div>

