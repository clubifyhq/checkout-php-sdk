@extends('layouts.super-admin')

@section('title', 'Tenant Management')

@php
    // Sample data - replace with actual data from your controllers
    $tenants = $tenants ?? collect([
        (object)[
            'id' => 1,
            'name' => 'Acme Corporation',
            'domain' => 'acme.clubify.app',
            'status' => 'active',
            'users_count' => 245,
            'created_at' => now()->subDays(30),
            'last_activity' => now()->subHours(2),
            'monthly_revenue' => 12500.00,
            'plan' => 'Enterprise',
            'admin_email' => 'admin@acme.com'
        ],
        (object)[
            'id' => 2,
            'name' => 'TechStart Inc',
            'domain' => 'techstart.clubify.app',
            'status' => 'active',
            'users_count' => 189,
            'created_at' => now()->subDays(45),
            'last_activity' => now()->subMinutes(30),
            'monthly_revenue' => 8900.00,
            'plan' => 'Professional',
            'admin_email' => 'contact@techstart.com'
        ],
        (object)[
            'id' => 3,
            'name' => 'Global Solutions',
            'domain' => 'global.clubify.app',
            'status' => 'suspended',
            'users_count' => 312,
            'created_at' => now()->subDays(60),
            'last_activity' => now()->subDays(5),
            'monthly_revenue' => 18200.00,
            'plan' => 'Enterprise',
            'admin_email' => 'admin@globalsolutions.com'
        ],
        (object)[
            'id' => 4,
            'name' => 'Innovation Hub',
            'domain' => 'innovation.clubify.app',
            'status' => 'trial',
            'users_count' => 156,
            'created_at' => now()->subDays(7),
            'last_activity' => now()->subHours(1),
            'monthly_revenue' => 0.00,
            'plan' => 'Trial',
            'admin_email' => 'hello@innovationhub.com'
        ]
    ]);

    $stats = [
        'total' => $tenants->count(),
        'active' => $tenants->where('status', 'active')->count(),
        'trial' => $tenants->where('status', 'trial')->count(),
        'suspended' => $tenants->where('status', 'suspended')->count()
    ];
@endphp

<div class="space-y-6" x-data="{
    selectedTenants: [],
    showFilters: false,
    searchQuery: '',
    statusFilter: 'all',
    planFilter: 'all',
    sortBy: 'name',
    sortDirection: 'asc'
}">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">Tenant Management</h1>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    Manage all organizations and their configurations
                </p>
            </div>
            <div class="mt-4 flex space-x-3 sm:mt-0">
                <flux:button
                    variant="outline"
                    size="sm"
                    @click="showFilters = !showFilters"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filters
                </flux:button>
                <flux:button
                    variant="outline"
                    size="sm"
                    onclick="window.print()"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Export
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
                    New Tenant
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="px-5 py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 text-white">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z"/>
                                <path fill-rule="evenodd" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5">
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total</dt>
                        <dd class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $stats['total'] }}</dd>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="px-5 py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-green-500 to-green-600 text-white">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5">
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Active</dt>
                        <dd class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $stats['active'] }}</dd>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="px-5 py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-yellow-500 to-yellow-600 text-white">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5">
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Trial</dt>
                        <dd class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $stats['trial'] }}</dd>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="px-5 py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-red-500 to-red-600 text-white">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5">
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Suspended</dt>
                        <dd class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $stats['suspended'] }}</dd>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Panel -->
    <div x-show="showFilters" x-transition class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Filters & Search</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Search -->
                <div>
                    <flux:input
                        type="text"
                        placeholder="Search tenants..."
                        x-model="searchQuery"
                        class="w-full"
                    />
                </div>

                <!-- Status Filter -->
                <div>
                    <flux:select x-model="statusFilter" class="w-full">
                        <option value="all">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="trial">Trial</option>
                        <option value="suspended">Suspended</option>
                    </flux:select>
                </div>

                <!-- Plan Filter -->
                <div>
                    <flux:select x-model="planFilter" class="w-full">
                        <option value="all">All Plans</option>
                        <option value="Trial">Trial</option>
                        <option value="Professional">Professional</option>
                        <option value="Enterprise">Enterprise</option>
                    </flux:select>
                </div>

                <!-- Sort -->
                <div>
                    <flux:select x-model="sortBy" class="w-full">
                        <option value="name">Sort by Name</option>
                        <option value="created_at">Created Date</option>
                        <option value="last_activity">Last Activity</option>
                        <option value="users_count">User Count</option>
                        <option value="monthly_revenue">Revenue</option>
                    </flux:select>
                </div>
            </div>
        </div>
    </div>

    <!-- Tenants Table -->
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">All Tenants</h3>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $tenants->count() }} tenants</span>
                    <!-- Bulk Actions -->
                    <div x-show="selectedTenants.length > 0" class="flex items-center space-x-2">
                        <span class="text-sm text-zinc-600 dark:text-zinc-300" x-text="`${selectedTenants.length} selected`"></span>
                        <flux:button variant="outline" size="sm">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"/>
                            </svg>
                            Bulk Actions
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-6 py-3 text-left">
                            <flux:checkbox
                                @change="selectedTenants = $event.target.checked ? [{{ $tenants->pluck('id')->implode(',') }}] : []"
                            />
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Organization
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Users
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Plan
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Revenue
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Last Activity
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
                    @foreach($tenants as $tenant)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <td class="px-6 py-4">
                                <flux:checkbox x-model="selectedTenants" value="{{ $tenant->id }}" />
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br {{ $tenant->status === 'active' ? 'from-green-500 to-green-600' : ($tenant->status === 'trial' ? 'from-yellow-500 to-yellow-600' : 'from-red-500 to-red-600') }} text-white text-sm font-medium">
                                        {{ strtoupper(substr($tenant->name, 0, 2)) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ $tenant->name }}</p>
                                        <p class="text-sm text-zinc-500 dark:text-zinc-400 truncate">{{ $tenant->domain }}</p>
                                        <p class="text-xs text-zinc-400 dark:text-zinc-500 truncate">{{ $tenant->admin_email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if($tenant->status === 'active')
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <svg class="mr-1.5 h-2 w-2 fill-green-500" viewBox="0 0 6 6">
                                            <circle cx="3" cy="3" r="3"/>
                                        </svg>
                                        Active
                                    </span>
                                @elseif($tenant->status === 'trial')
                                    <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        <svg class="mr-1.5 h-2 w-2 fill-yellow-500" viewBox="0 0 6 6">
                                            <circle cx="3" cy="3" r="3"/>
                                        </svg>
                                        Trial
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-200">
                                        <svg class="mr-1.5 h-2 w-2 fill-red-500" viewBox="0 0 6 6">
                                            <circle cx="3" cy="3" r="3"/>
                                        </svg>
                                        Suspended
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-zinc-900 dark:text-zinc-100">
                                {{ number_format($tenant->users_count) }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-full {{ $tenant->plan === 'Enterprise' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : ($tenant->plan === 'Professional' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-zinc-100 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-200') }} px-2.5 py-0.5 text-xs font-medium">
                                    {{ $tenant->plan }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-zinc-900 dark:text-zinc-100">
                                ${{ number_format($tenant->monthly_revenue, 2) }}
                            </td>
                            <td class="px-6 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $tenant->last_activity->diffForHumans() }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu class="w-48">
                                        <flux:menu.item icon="eye" href="#">View Details</flux:menu.item>
                                        <flux:menu.item icon="pencil" href="#">Edit Settings</flux:menu.item>
                                        <flux:menu.item icon="arrow-right-circle" href="#">Switch Context</flux:menu.item>
                                        <flux:menu.separator />
                                        @if($tenant->status === 'suspended')
                                            <flux:menu.item icon="play" href="#" class="text-green-600 dark:text-green-400">Activate</flux:menu.item>
                                        @else
                                            <flux:menu.item icon="pause" href="#" class="text-yellow-600 dark:text-yellow-400">Suspend</flux:menu.item>
                                        @endif
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" href="#" class="text-red-600 dark:text-red-400">Delete</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="border-t border-zinc-200 bg-white px-6 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-zinc-500 dark:text-zinc-400">Show</span>
                    <flux:select size="sm" class="w-16">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </flux:select>
                    <span class="text-sm text-zinc-500 dark:text-zinc-400">per page</span>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-zinc-500 dark:text-zinc-400">Showing 1 to {{ $tenants->count() }} of {{ $tenants->count() }} results</span>
                    <div class="flex space-x-1">
                        <flux:button variant="outline" size="sm" disabled>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </flux:button>
                        <flux:button variant="primary" size="sm">1</flux:button>
                        <flux:button variant="outline" size="sm" disabled>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('tenantManagement', () => ({
        selectedTenants: [],
        showFilters: false,
        searchQuery: '',
        statusFilter: 'all',
        planFilter: 'all',
        sortBy: 'name',
        sortDirection: 'asc',

        init() {
            // Initialize any component-specific logic here
        },

        toggleSelectAll() {
            if (this.selectedTenants.length === {{ $tenants->count() }}) {
                this.selectedTenants = [];
            } else {
                this.selectedTenants = [{{ $tenants->pluck('id')->implode(',') }}];
            }
        },

        bulkAction(action) {
            if (this.selectedTenants.length === 0) {
                alert('Please select at least one tenant');
                return;
            }

            // Implement bulk actions here
            console.log(`Performing ${action} on tenants:`, this.selectedTenants);
        }
    }));
});
</script>
@endpush