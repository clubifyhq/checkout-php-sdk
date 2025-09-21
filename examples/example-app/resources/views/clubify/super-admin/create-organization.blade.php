@extends('layouts.super-admin')

@section('title', 'Create Organization')

<div class="max-w-4xl mx-auto space-y-6" x-data="organizationForm()">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <flux:button
                variant="ghost"
                size="sm"
                :href="route('super-admin.tenants.index')"
                wire:navigate
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Tenants
            </flux:button>
            <div class="h-6 w-px bg-zinc-300 dark:bg-zinc-600"></div>
            <div>
                <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">Create New Organization</h1>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    Set up a new tenant organization with all required configurations
                </p>
            </div>
        </div>
    </div>

    <!-- Progress Indicator -->
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="px-6 py-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Setup Progress</h3>
                <span class="text-sm text-zinc-500 dark:text-zinc-400" x-text="`Step ${currentStep} of ${totalSteps}`"></span>
            </div>
            <div class="w-full bg-zinc-200 rounded-full h-2 dark:bg-zinc-700">
                <div class="bg-gradient-to-r from-purple-500 to-indigo-600 h-2 rounded-full transition-all duration-300"
                     :style="`width: ${(currentStep / totalSteps) * 100}%`"></div>
            </div>
            <div class="flex justify-between mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                <span :class="currentStep >= 1 ? 'text-purple-600 dark:text-purple-400 font-medium' : ''">Basic Info</span>
                <span :class="currentStep >= 2 ? 'text-purple-600 dark:text-purple-400 font-medium' : ''">Configuration</span>
                <span :class="currentStep >= 3 ? 'text-purple-600 dark:text-purple-400 font-medium' : ''">Admin Setup</span>
                <span :class="currentStep >= 4 ? 'text-purple-600 dark:text-purple-400 font-medium' : ''">Review</span>
            </div>
        </div>
    </div>

    <!-- Form Container -->
    <form @submit.prevent="submitForm" class="space-y-6">
        @csrf

        <!-- Step 1: Basic Information -->
        <div x-show="currentStep === 1" x-transition class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Basic Information</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Essential details for the new organization</p>
            </div>
            <div class="p-6 space-y-6">
                <!-- Organization Name -->
                <div>
                    <flux:label for="org_name">Organization Name *</flux:label>
                    <flux:input
                        id="org_name"
                        name="org_name"
                        type="text"
                        x-model="form.org_name"
                        @input="generateSlug"
                        placeholder="e.g., Acme Corporation"
                        required
                        class="mt-2"
                        :class="errors.org_name ? 'border-red-300 dark:border-red-600' : ''"
                    />
                    <p x-show="errors.org_name" x-text="errors.org_name" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">This will be the primary name displayed across the platform</p>
                </div>

                <!-- Domain/Subdomain -->
                <div>
                    <flux:label for="domain">Domain/Subdomain *</flux:label>
                    <div class="mt-2 flex rounded-lg shadow-sm">
                        <flux:input
                            id="domain"
                            name="domain"
                            type="text"
                            x-model="form.domain"
                            @input="validateDomain"
                            placeholder="acme"
                            required
                            class="rounded-r-none"
                            :class="errors.domain ? 'border-red-300 dark:border-red-600' : ''"
                        />
                        <span class="inline-flex items-center px-3 rounded-r-lg border border-l-0 border-zinc-300 bg-zinc-50 text-zinc-500 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-400 text-sm">
                            .clubify.app
                        </span>
                    </div>
                    <div class="mt-2 flex items-center space-x-2">
                        <div x-show="domainStatus === 'checking'" class="flex items-center text-sm text-zinc-500 dark:text-zinc-400">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Checking availability...
                        </div>
                        <div x-show="domainStatus === 'available'" class="flex items-center text-sm text-green-600 dark:text-green-400">
                            <svg class="mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            Domain available
                        </div>
                        <div x-show="domainStatus === 'unavailable'" class="flex items-center text-sm text-red-600 dark:text-red-400">
                            <svg class="mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                            Domain unavailable
                        </div>
                    </div>
                    <p x-show="errors.domain" x-text="errors.domain" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                    <p x-show="form.domain" class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        Full URL: <span class="font-mono" x-text="`https://${form.domain}.clubify.app`"></span>
                    </p>
                </div>

                <!-- Description -->
                <div>
                    <flux:label for="description">Description</flux:label>
                    <flux:textarea
                        id="description"
                        name="description"
                        x-model="form.description"
                        rows="4"
                        placeholder="Brief description of the organization and its purpose..."
                        class="mt-2"
                    />
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Optional description for internal reference</p>
                </div>

                <!-- Industry -->
                <div>
                    <flux:label for="industry">Industry</flux:label>
                    <flux:select
                        id="industry"
                        name="industry"
                        x-model="form.industry"
                        class="mt-2"
                    >
                        <option value="">Select an industry</option>
                        <option value="technology">Technology</option>
                        <option value="healthcare">Healthcare</option>
                        <option value="finance">Finance</option>
                        <option value="education">Education</option>
                        <option value="retail">Retail</option>
                        <option value="manufacturing">Manufacturing</option>
                        <option value="consulting">Consulting</option>
                        <option value="other">Other</option>
                    </flux:select>
                </div>
            </div>
        </div>

        <!-- Step 2: Configuration -->
        <div x-show="currentStep === 2" x-transition class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Configuration Settings</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Technical and business configuration options</p>
            </div>
            <div class="p-6 space-y-6">
                <!-- Plan Selection -->
                <div>
                    <flux:label>Subscription Plan *</flux:label>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div @click="form.plan = 'trial'" :class="form.plan === 'trial' ? 'ring-2 ring-purple-500 border-purple-500' : 'border-zinc-300 dark:border-zinc-600'" class="relative rounded-lg border p-4 cursor-pointer hover:border-purple-300 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <input type="radio" x-model="form.plan" value="trial" class="h-4 w-4 text-purple-600 border-zinc-300 focus:ring-purple-500">
                                    <div class="ml-3">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">Trial</div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">14 days free</div>
                                    </div>
                                </div>
                                <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">$0</div>
                            </div>
                        </div>

                        <div @click="form.plan = 'professional'" :class="form.plan === 'professional' ? 'ring-2 ring-purple-500 border-purple-500' : 'border-zinc-300 dark:border-zinc-600'" class="relative rounded-lg border p-4 cursor-pointer hover:border-purple-300 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <input type="radio" x-model="form.plan" value="professional" class="h-4 w-4 text-purple-600 border-zinc-300 focus:ring-purple-500">
                                    <div class="ml-3">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">Professional</div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">Most popular</div>
                                    </div>
                                </div>
                                <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">$49</div>
                            </div>
                        </div>

                        <div @click="form.plan = 'enterprise'" :class="form.plan === 'enterprise' ? 'ring-2 ring-purple-500 border-purple-500' : 'border-zinc-300 dark:border-zinc-600'" class="relative rounded-lg border p-4 cursor-pointer hover:border-purple-300 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <input type="radio" x-model="form.plan" value="enterprise" class="h-4 w-4 text-purple-600 border-zinc-300 focus:ring-purple-500">
                                    <div class="ml-3">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">Enterprise</div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">Advanced features</div>
                                    </div>
                                </div>
                                <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">$149</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Features -->
                <div>
                    <flux:label>Features & Settings</flux:label>
                    <div class="mt-4 space-y-4">
                        <div class="flex items-center justify-between p-4 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">Custom Branding</div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">Allow custom logos and brand colors</div>
                            </div>
                            <flux:toggle x-model="form.features.custom_branding" />
                        </div>

                        <div class="flex items-center justify-between p-4 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">Advanced Analytics</div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">Enhanced reporting and insights</div>
                            </div>
                            <flux:toggle x-model="form.features.advanced_analytics" />
                        </div>

                        <div class="flex items-center justify-between p-4 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">API Access</div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">Programmatic access to platform features</div>
                            </div>
                            <flux:toggle x-model="form.features.api_access" />
                        </div>

                        <div class="flex items-center justify-between p-4 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">Multi-language Support</div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">Internationalization capabilities</div>
                            </div>
                            <flux:toggle x-model="form.features.multi_language" />
                        </div>
                    </div>
                </div>

                <!-- User Limits -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <flux:label for="user_limit">User Limit</flux:label>
                        <flux:select
                            id="user_limit"
                            name="user_limit"
                            x-model="form.user_limit"
                            class="mt-2"
                        >
                            <option value="50">50 users</option>
                            <option value="100">100 users</option>
                            <option value="250">250 users</option>
                            <option value="500">500 users</option>
                            <option value="1000">1,000 users</option>
                            <option value="unlimited">Unlimited</option>
                        </flux:select>
                    </div>

                    <div>
                        <flux:label for="storage_limit">Storage Limit</flux:label>
                        <flux:select
                            id="storage_limit"
                            name="storage_limit"
                            x-model="form.storage_limit"
                            class="mt-2"
                        >
                            <option value="1">1 GB</option>
                            <option value="5">5 GB</option>
                            <option value="10">10 GB</option>
                            <option value="25">25 GB</option>
                            <option value="50">50 GB</option>
                            <option value="unlimited">Unlimited</option>
                        </flux:select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: Admin Setup -->
        <div x-show="currentStep === 3" x-transition class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Administrator Setup</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Create the initial administrator account</p>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <flux:label for="admin_first_name">First Name *</flux:label>
                        <flux:input
                            id="admin_first_name"
                            name="admin_first_name"
                            type="text"
                            x-model="form.admin_first_name"
                            required
                            class="mt-2"
                            :class="errors.admin_first_name ? 'border-red-300 dark:border-red-600' : ''"
                        />
                        <p x-show="errors.admin_first_name" x-text="errors.admin_first_name" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                    </div>

                    <div>
                        <flux:label for="admin_last_name">Last Name *</flux:label>
                        <flux:input
                            id="admin_last_name"
                            name="admin_last_name"
                            type="text"
                            x-model="form.admin_last_name"
                            required
                            class="mt-2"
                            :class="errors.admin_last_name ? 'border-red-300 dark:border-red-600' : ''"
                        />
                        <p x-show="errors.admin_last_name" x-text="errors.admin_last_name" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                    </div>
                </div>

                <div>
                    <flux:label for="admin_email">Email Address *</flux:label>
                    <flux:input
                        id="admin_email"
                        name="admin_email"
                        type="email"
                        x-model="form.admin_email"
                        @input="validateEmail"
                        required
                        class="mt-2"
                        :class="errors.admin_email ? 'border-red-300 dark:border-red-600' : ''"
                    />
                    <p x-show="errors.admin_email" x-text="errors.admin_email" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">This will be the primary administrator email</p>
                </div>

                <div>
                    <flux:label for="admin_phone">Phone Number</flux:label>
                    <flux:input
                        id="admin_phone"
                        name="admin_phone"
                        type="tel"
                        x-model="form.admin_phone"
                        placeholder="+1 (555) 123-4567"
                        class="mt-2"
                    />
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Optional contact number</p>
                </div>

                <div>
                    <div class="flex items-center space-x-3">
                        <flux:checkbox x-model="form.send_welcome_email" />
                        <flux:label for="send_welcome_email">Send welcome email with setup instructions</flux:label>
                    </div>
                </div>

                <div>
                    <div class="flex items-center space-x-3">
                        <flux:checkbox x-model="form.auto_activate" />
                        <flux:label for="auto_activate">Automatically activate the organization</flux:label>
                    </div>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Uncheck to create in draft mode for review</p>
                </div>
            </div>
        </div>

        <!-- Step 4: Review -->
        <div x-show="currentStep === 4" x-transition class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Review & Confirm</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Please review all settings before creating the organization</p>
            </div>
            <div class="p-6 space-y-6">
                <!-- Organization Summary -->
                <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4">
                    <h4 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Organization Details</h4>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Name</dt>
                            <dd class="text-sm text-zinc-900 dark:text-zinc-100" x-text="form.org_name || 'Not specified'"></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Domain</dt>
                            <dd class="text-sm text-zinc-900 dark:text-zinc-100" x-text="form.domain ? `${form.domain}.clubify.app` : 'Not specified'"></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Plan</dt>
                            <dd class="text-sm text-zinc-900 dark:text-zinc-100" x-text="form.plan ? form.plan.charAt(0).toUpperCase() + form.plan.slice(1) : 'Not specified'"></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Industry</dt>
                            <dd class="text-sm text-zinc-900 dark:text-zinc-100" x-text="form.industry ? form.industry.charAt(0).toUpperCase() + form.industry.slice(1) : 'Not specified'"></dd>
                        </div>
                    </dl>
                </div>

                <!-- Admin Summary -->
                <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4">
                    <h4 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Administrator</h4>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Name</dt>
                            <dd class="text-sm text-zinc-900 dark:text-zinc-100" x-text="`${form.admin_first_name || ''} ${form.admin_last_name || ''}`.trim() || 'Not specified'"></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Email</dt>
                            <dd class="text-sm text-zinc-900 dark:text-zinc-100" x-text="form.admin_email || 'Not specified'"></dd>
                        </div>
                    </dl>
                </div>

                <!-- Configuration Summary -->
                <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4">
                    <h4 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Configuration</h4>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">User Limit</dt>
                            <dd class="text-sm text-zinc-900 dark:text-zinc-100" x-text="form.user_limit || 'Not specified'"></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Storage Limit</dt>
                            <dd class="text-sm text-zinc-900 dark:text-zinc-100" x-text="form.storage_limit ? `${form.storage_limit} GB` : 'Not specified'"></dd>
                        </div>
                    </dl>
                    <div class="mt-3">
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400 mb-2">Enabled Features</dt>
                        <div class="flex flex-wrap gap-2">
                            <span x-show="form.features.custom_branding" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Custom Branding</span>
                            <span x-show="form.features.advanced_analytics" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Advanced Analytics</span>
                            <span x-show="form.features.api_access" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">API Access</span>
                            <span x-show="form.features.multi_language" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Multi-language</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-between">
            <div>
                <flux:button
                    x-show="currentStep > 1"
                    type="button"
                    variant="outline"
                    @click="previousStep"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Previous
                </flux:button>
            </div>

            <div class="flex space-x-3">
                <flux:button
                    type="button"
                    variant="ghost"
                    :href="route('super-admin.tenants.index')"
                    wire:navigate
                >
                    Cancel
                </flux:button>

                <flux:button
                    x-show="currentStep < totalSteps"
                    type="button"
                    variant="primary"
                    @click="nextStep"
                    :disabled="!canProceed()"
                    class="bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700"
                >
                    Next
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </flux:button>

                <flux:button
                    x-show="currentStep === totalSteps"
                    type="submit"
                    variant="primary"
                    :disabled="isSubmitting"
                    class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700"
                >
                    <span x-show="!isSubmitting">Create Organization</span>
                    <span x-show="isSubmitting" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Creating...
                    </span>
                </flux:button>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function organizationForm() {
    return {
        currentStep: 1,
        totalSteps: 4,
        isSubmitting: false,
        domainStatus: null,
        errors: {},

        form: {
            org_name: '',
            domain: '',
            description: '',
            industry: '',
            plan: 'trial',
            features: {
                custom_branding: false,
                advanced_analytics: false,
                api_access: false,
                multi_language: false
            },
            user_limit: '50',
            storage_limit: '5',
            admin_first_name: '',
            admin_last_name: '',
            admin_email: '',
            admin_phone: '',
            send_welcome_email: true,
            auto_activate: true
        },

        generateSlug() {
            if (this.form.org_name && !this.form.domain) {
                this.form.domain = this.form.org_name
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-|-$/g, '');
                this.validateDomain();
            }
        },

        async validateDomain() {
            if (!this.form.domain) {
                this.domainStatus = null;
                return;
            }

            this.domainStatus = 'checking';

            // Simulate domain availability check
            setTimeout(() => {
                // Mock check - in real implementation, make API call
                const unavailableDomains = ['test', 'admin', 'api', 'www', 'app'];
                this.domainStatus = unavailableDomains.includes(this.form.domain) ? 'unavailable' : 'available';
            }, 1000);
        },

        validateEmail() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.form.admin_email && !emailRegex.test(this.form.admin_email)) {
                this.errors.admin_email = 'Please enter a valid email address';
            } else {
                delete this.errors.admin_email;
            }
        },

        canProceed() {
            switch (this.currentStep) {
                case 1:
                    return this.form.org_name && this.form.domain && this.domainStatus === 'available';
                case 2:
                    return this.form.plan;
                case 3:
                    return this.form.admin_first_name && this.form.admin_last_name && this.form.admin_email && !this.errors.admin_email;
                case 4:
                    return true;
                default:
                    return false;
            }
        },

        nextStep() {
            if (this.canProceed() && this.currentStep < this.totalSteps) {
                this.currentStep++;
            }
        },

        previousStep() {
            if (this.currentStep > 1) {
                this.currentStep--;
            }
        },

        async submitForm() {
            this.isSubmitting = true;
            this.errors = {};

            try {
                // Simulate form submission
                await new Promise(resolve => setTimeout(resolve, 2000));

                // In real implementation, submit to your Laravel controller
                // const response = await fetch('/super-admin/organizations', {
                //     method: 'POST',
                //     headers: {
                //         'Content-Type': 'application/json',
                //         'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                //     },
                //     body: JSON.stringify(this.form)
                // });

                // Success notification
                alert('Organization created successfully!');
                window.location.href = '{{ route("super-admin.tenants.index") }}';

            } catch (error) {
                console.error('Submission error:', error);
                this.errors.general = 'An error occurred while creating the organization. Please try again.';
            } finally {
                this.isSubmitting = false;
            }
        }
    };
}
</script>
@endpush