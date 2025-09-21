<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <title>{{ $title ?? 'Super Admin' }} - {{ config('app.name') }}</title>
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <!-- Super Admin Context Banner -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-2 px-4 text-center text-sm font-medium shadow-lg">
            <div class="flex items-center justify-center space-x-2">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" clip-rule="evenodd"/>
                </svg>
                <span>Super Administrator Mode</span>
                @if(isset($currentTenant))
                    <span class="text-purple-200">•</span>
                    <span class="text-purple-200">Tenant: {{ $currentTenant->name ?? 'Unknown' }}</span>
                @endif
            </div>
        </div>

        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <!-- Super Admin Logo/Branding -->
            <div class="mb-4 flex items-center space-x-3 px-2">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-indigo-600 text-white">
                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Super Admin</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">System Management</p>
                </div>
            </div>

            <!-- Context Switcher -->
            @if(isset($availableTenants) && count($availableTenants) > 0)
                <div class="mb-4 px-2">
                    <flux:select
                        name="tenant_context"
                        placeholder="Switch Tenant Context"
                        class="w-full"
                        onchange="window.location.href = this.value"
                    >
                        <option value="{{ route('super-admin.dashboard') }}">Global Context</option>
                        @foreach($availableTenants as $tenant)
                            <option
                                value="{{ route('super-admin.tenant.switch', $tenant->id) }}"
                                {{ (isset($currentTenant) && $currentTenant->id === $tenant->id) ? 'selected' : '' }}
                            >
                                {{ $tenant->name }}
                            </option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            <!-- Navigation -->
            <flux:navlist variant="outline">
                <flux:navlist.group :heading="__('Platform Management')" class="grid">
                    <flux:navlist.item
                        icon="chart-bar"
                        :href="route('super-admin.dashboard')"
                        :current="request()->routeIs('super-admin.dashboard')"
                        wire:navigate
                    >
                        {{ __('Dashboard') }}
                    </flux:navlist.item>

                    <flux:navlist.item
                        icon="building-office"
                        :href="route('super-admin.tenants.index')"
                        :current="request()->routeIs('super-admin.tenants.*')"
                        wire:navigate
                    >
                        {{ __('Tenant Management') }}
                    </flux:navlist.item>

                    <flux:navlist.item
                        icon="plus-circle"
                        :href="route('super-admin.organizations.create')"
                        :current="request()->routeIs('super-admin.organizations.*')"
                        wire:navigate
                    >
                        {{ __('Create Organization') }}
                    </flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group :heading="__('System')" class="grid">
                    <flux:navlist.item icon="cog-6-tooth" href="#" wire:navigate>
                        {{ __('System Settings') }}
                    </flux:navlist.item>

                    <flux:navlist.item icon="chart-pie" href="#" wire:navigate>
                        {{ __('Analytics') }}
                    </flux:navlist.item>

                    <flux:navlist.item icon="shield-check" href="#" wire:navigate>
                        {{ __('Security Audit') }}
                    </flux:navlist.item>
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            <!-- Quick Actions -->
            <div class="mb-4 px-2">
                <flux:button
                    size="sm"
                    variant="primary"
                    :href="route('super-admin.organizations.create')"
                    class="w-full bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700"
                    wire:navigate
                >
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                    </svg>
                    New Organization
                </flux:button>
            </div>

            <!-- Exit Super Admin Mode -->
            <div class="mb-4 px-2">
                <flux:button
                    size="sm"
                    variant="outline"
                    :href="route('dashboard')"
                    class="w-full border-orange-300 text-orange-600 hover:bg-orange-50 dark:border-orange-600 dark:text-orange-400 dark:hover:bg-orange-950"
                    wire:navigate
                >
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                    </svg>
                    Exit Super Admin
                </flux:button>
            </div>

            <!-- Documentation Links -->
            <flux:navlist variant="outline">
                <flux:navlist.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('Repository') }}
                </flux:navlist.item>

                <flux:navlist.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                    {{ __('Documentation') }}
                </flux:navlist.item>
            </flux:navlist>

            <!-- Desktop User Menu -->
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon:trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span class="flex h-full w-full items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-indigo-600 text-white font-semibold">
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                    <span class="truncate text-xs text-purple-600 dark:text-purple-400 font-medium">Super Admin</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Profile Settings') }}</flux:menu.item>
                        <flux:menu.item href="#" icon="shield-check">{{ __('Admin Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('dashboard')" icon="arrow-left" wire:navigate>{{ __('Exit Super Admin') }}</flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span class="flex h-full w-full items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-indigo-600 text-white font-semibold">
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                    <span class="truncate text-xs text-purple-600 dark:text-purple-400 font-medium">Super Admin</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Profile Settings') }}</flux:menu.item>
                        <flux:menu.item href="#" icon="shield-check">{{ __('Admin Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('dashboard')" icon="arrow-left" wire:navigate>{{ __('Exit Super Admin') }}</flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <flux:main class="px-6 pb-6">
            {{ $slot }}
        </flux:main>

        @fluxScripts
    </body>
</html>