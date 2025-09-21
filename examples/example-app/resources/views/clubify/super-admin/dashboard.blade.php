@extends('layouts.super-admin')

@section('title', 'Super Admin Dashboard')

@php
    // Sample data - replace with actual data from your controllers
    $stats = [
        'total_tenants' => $totalTenants ?? 25,
        'active_tenants' => $activeTenants ?? 22,
        'total_users' => $totalUsers ?? 1247,
        'monthly_revenue' => $monthlyRevenue ?? 45750.80,
        'growth_rate' => $growthRate ?? 12.5,
        'system_health' => $systemHealth ?? 98.5
    ];

    $recentActivity = $recentActivity ?? [
        ['action' => 'New tenant created', 'tenant' => 'Acme Corp', 'time' => '2 hours ago', 'type' => 'success'],
        ['action' => 'User registration surge', 'tenant' => 'TechStart Inc', 'time' => '4 hours ago', 'type' => 'info'],
        ['action' => 'Payment processing issue', 'tenant' => 'Global Solutions', 'time' => '6 hours ago', 'type' => 'warning'],
        ['action' => 'System maintenance completed', 'tenant' => 'System', 'time' => '8 hours ago', 'type' => 'success'],
    ];

    $tenantPerformance = $tenantPerformance ?? [
        ['name' => 'Acme Corp', 'users' => 245, 'revenue' => 12500, 'growth' => 15.2],
        ['name' => 'TechStart Inc', 'users' => 189, 'revenue' => 8900, 'growth' => 8.7],
        ['name' => 'Global Solutions', 'users' => 312, 'revenue' => 18200, 'growth' => 22.1],
        ['name' => 'Innovation Hub', 'users' => 156, 'revenue' => 6150, 'growth' => -2.3],
    ];
@endphp

<div class="space-y-6">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">Super Admin Dashboard</h1>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    Monitor and manage your entire platform ecosystem
                </p>
            </div>
            <div class="mt-4 flex space-x-3 sm:mt-0">
                <flux:button
                    variant="outline"
                    size="sm"
                    onclick="location.reload()"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Refresh
                </flux:button>
                <flux:button
                    variant="primary"
                    size="sm"
                    :href="route('super-admin.organizations.create')"
                    class="bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700"
                    wire:navigate
                >
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                    </svg>
                    New Organization
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Total Tenants -->
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="px-6 py-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 text-white">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z"/>
                                <path fill-rule="evenodd" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400 truncate">Total Tenants</dt>
                            <dd class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($stats['total_tenants']) }}</dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="flex items-center text-sm">
                        <span class="text-green-600 dark:text-green-400 font-medium">{{ $stats['active_tenants'] }} active</span>
                        <span class="ml-2 text-zinc-500 dark:text-zinc-400">of {{ $stats['total_tenants'] }} total</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Users -->
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="px-6 py-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-green-500 to-green-600 text-white">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400 truncate">Total Users</dt>
                            <dd class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($stats['total_users']) }}</dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="flex items-center text-sm">
                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="ml-1 text-green-600 dark:text-green-400 font-medium">+{{ $stats['growth_rate'] }}%</span>
                        <span class="ml-2 text-zinc-500 dark:text-zinc-400">this month</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Revenue -->
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="px-6 py-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-purple-600 text-white">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400 truncate">Monthly Revenue</dt>
                            <dd class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">${{ number_format($stats['monthly_revenue'], 2) }}</dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="flex items-center text-sm">
                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="ml-1 text-green-600 dark:text-green-400 font-medium">+{{ $stats['growth_rate'] }}%</span>
                        <span class="ml-2 text-zinc-500 dark:text-zinc-400">vs last month</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Health -->
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="px-6 py-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 text-white">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400 truncate">System Health</dt>
                            <dd class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $stats['system_health'] }}%</dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="flex items-center text-sm">
                        <div class="w-16 bg-zinc-200 rounded-full h-2 dark:bg-zinc-700">
                            <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 h-2 rounded-full" style="width: {{ $stats['system_health'] }}%"></div>
                        </div>
                        <span class="ml-2 text-emerald-600 dark:text-emerald-400 font-medium">Excellent</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Recent Activity -->
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Recent Activity</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Latest platform events and notifications</p>
                </div>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($recentActivity as $activity)
                        <div class="px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0">
                                        @if($activity['type'] === 'success')
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                                                <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                        @elseif($activity['type'] === 'warning')
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-900">
                                                <svg class="h-4 w-4 text-yellow-600 dark:text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                        @else
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                                                <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $activity['action'] }}</p>
                                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $activity['tenant'] }}</p>
                                    </div>
                                </div>
                                <div class="flex-shrink-0 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $activity['time'] }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-zinc-200 px-6 py-3 dark:border-zinc-700">
                    <flux:button variant="ghost" size="sm" class="w-full">
                        View all activity
                        <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </flux:button>
                </div>
            </div>
        </div>

        <!-- Top Performing Tenants -->
        <div>
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Top Performers</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Best performing tenants this month</p>
                </div>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($tenantPerformance as $index => $tenant)
                        <div class="px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $index === 0 ? 'bg-yellow-100 text-yellow-600 dark:bg-yellow-900 dark:text-yellow-400' : ($index === 1 ? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' : ($index === 2 ? 'bg-orange-100 text-orange-600 dark:bg-orange-900 dark:text-orange-400' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400')) }} text-sm font-medium">
                                        {{ $index + 1 }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $tenant['name'] }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($tenant['users']) }} users</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ number_format($tenant['revenue']) }}</p>
                                    <p class="text-xs {{ $tenant['growth'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $tenant['growth'] >= 0 ? '+' : '' }}{{ $tenant['growth'] }}%
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-zinc-200 px-6 py-3 dark:border-zinc-700">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        class="w-full"
                        :href="route('super-admin.tenants.index')"
                        wire:navigate
                    >
                        View all tenants
                        <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </flux:button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Grid -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Tenant Management -->
        <div class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center space-x-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 text-white">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z"/>
                        <path fill-rule="evenodd" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100">Manage Tenants</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">View and configure all tenants</p>
                </div>
            </div>
            <flux:button
                variant="ghost"
                size="sm"
                class="mt-4 w-full group-hover:bg-blue-50 dark:group-hover:bg-blue-950"
                :href="route('super-admin.tenants.index')"
                wire:navigate
            >
                Manage Tenants
                <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </flux:button>
        </div>

        <!-- Create Organization -->
        <div class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center space-x-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-green-500 to-green-600 text-white">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100">New Organization</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Create a new organization</p>
                </div>
            </div>
            <flux:button
                variant="ghost"
                size="sm"
                class="mt-4 w-full group-hover:bg-green-50 dark:group-hover:bg-green-950"
                :href="route('super-admin.organizations.create')"
                wire:navigate
            >
                Create Organization
                <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </flux:button>
        </div>

        <!-- System Settings -->
        <div class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center space-x-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-purple-600 text-white">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100">System Settings</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Configure platform settings</p>
                </div>
            </div>
            <flux:button
                variant="ghost"
                size="sm"
                class="mt-4 w-full group-hover:bg-purple-50 dark:group-hover:bg-purple-950"
                href="#"
            >
                Open Settings
                <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </flux:button>
        </div>

        <!-- Analytics -->
        <div class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center space-x-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-orange-500 to-orange-600 text-white">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"/>
                        <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100">Analytics</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">View detailed analytics</p>
                </div>
            </div>
            <flux:button
                variant="ghost"
                size="sm"
                class="mt-4 w-full group-hover:bg-orange-50 dark:group-hover:bg-orange-950"
                href="#"
            >
                View Analytics
                <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </flux:button>
        </div>
    </div>
</div>