<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clubify Checkout SDK - Teste Completo de Métodos</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-shadow {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .method-card {
            transition: all 0.2s ease;
        }
        .method-card:hover {
            transform: translateY(-1px);
        }
        .loading-animation {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        .detailed-info {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        .detailed-info.expanded {
            max-height: 500px;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="gradient-bg text-white py-8">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <h1 class="text-4xl font-bold mb-2">🧪 Teste Completo do SDK</h1>
                <p class="text-xl opacity-90">Análise abrangente de todos os métodos disponíveis</p>
                <div class="mt-4">
                    <a href="{{ url('/clubify') }}" class="inline-flex items-center px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Voltar à Demo
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8" x-data="clubifyTestSuite()">
        <!-- Control Panel -->
        <div class="bg-white rounded-lg card-shadow p-6 mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800">Central de Controle</h2>
                    <p class="text-gray-600">Execute testes individuais ou completos do SDK</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3">
                    <button
                        @click="runAllTests()"
                        :disabled="testing"
                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <span x-show="!testing">🚀 Executar Todos os Testes</span>
                        <span x-show="testing" class="loading-animation">⏳ Executando...</span>
                    </button>
                    <button
                        @click="clearResults()"
                        :disabled="testing"
                        class="px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        🗑️ Limpar Resultados
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div x-show="hasResults" class="bg-white rounded-lg card-shadow p-6 mb-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">📊 Resumo dos Resultados</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-blue-50 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-blue-600" x-text="stats.totalMethods"></div>
                    <div class="text-sm text-blue-700">Total de Métodos</div>
                </div>
                <div class="bg-green-50 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-green-600" x-text="stats.workingMethods"></div>
                    <div class="text-sm text-green-700">Métodos Funcionando</div>
                </div>
                <div class="bg-red-50 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-red-600" x-text="stats.errorMethods"></div>
                    <div class="text-sm text-red-700">Métodos com Erro</div>
                </div>
                <div class="bg-purple-50 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-purple-600" x-text="stats.successRate + '%'"></div>
                    <div class="text-sm text-purple-700">Taxa de Sucesso</div>
                </div>
            </div>
        </div>

        <!-- Modules Grid -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
            <!-- Organization Module -->
            <div class="bg-white rounded-lg card-shadow p-6 module-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">🏢</span>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-800">Organization Module</h3>
                            <p class="text-sm text-gray-600">17 métodos disponíveis</p>
                        </div>
                    </div>
                    <button
                        @click="testModule('organization')"
                        :disabled="testing"
                        class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-sm disabled:opacity-50"
                    >
                        Testar
                    </button>
                </div>
                <div x-show="results.organization && results.organization.length > 0" class="space-y-2">
                    <template x-for="result in results.organization" :key="result.method">
                        <div class="method-card border rounded-lg p-3 hover:bg-gray-50" x-data="{ expanded: false }">
                            <div class="flex items-center justify-between mb-2">
                                <code class="text-sm font-mono text-gray-700" x-text="result.method + '()'"></code>
                                <div class="flex items-center gap-2">
                                    <button x-show="result.success && result.detailed_info" @click="expanded = !expanded" class="text-blue-600 hover:text-blue-800 text-xs">
                                        <span x-show="!expanded">📋 Details</span>
                                        <span x-show="expanded">🔼 Hide</span>
                                    </button>
                                    <span x-show="result.success" class="text-green-600 text-lg">✅</span>
                                    <span x-show="!result.success" class="text-red-600 text-lg">❌</span>
                                </div>
                            </div>
                            <div x-show="result.success && result.result" class="text-xs text-gray-600 mt-1 mb-1" x-text="result.result"></div>
                            <div x-show="result.error" class="text-xs text-red-600 mt-1 mb-1" x-text="result.error"></div>

                            <!-- Detailed Info Section -->
                            <div x-show="expanded && result.success && result.detailed_info" class="mt-2 p-2 bg-gray-50 rounded text-xs" x-transition>
                                <div class="grid grid-cols-2 gap-2">
                                    <template x-if="result.detailed_info.id">
                                        <div><strong>🆔 ID:</strong> <span x-text="result.detailed_info.id"></span></div>
                                    </template>
                                    <template x-if="result.detailed_info.data_id">
                                        <div><strong>📦 Data ID:</strong> <span x-text="result.detailed_info.data_id"></span></div>
                                    </template>
                                    <template x-if="result.detailed_info.api_success">
                                        <div><strong>✅ API Success:</strong> <span x-text="result.detailed_info.api_success"></span></div>
                                    </template>
                                    <template x-if="result.detailed_info.status">
                                        <div><strong>📊 Status:</strong> <span x-text="result.detailed_info.status"></span></div>
                                    </template>
                                    <template x-if="result.detailed_info.operation">
                                        <div><strong>⚙️ Operation:</strong> <span x-text="result.detailed_info.operation"></span></div>
                                    </template>
                                    <template x-if="result.detailed_info.return_type">
                                        <div><strong>🔧 Type:</strong> <span x-text="result.detailed_info.return_type"></span></div>
                                    </template>
                                </div>
                                <template x-if="result.detailed_info.message">
                                    <div class="mt-1"><strong>💬 Message:</strong> <span x-text="result.detailed_info.message"></span></div>
                                </template>
                                <template x-if="result.detailed_info.keys && result.detailed_info.keys.length > 0">
                                    <div class="mt-1"><strong>🔑 Keys:</strong> <span x-text="result.detailed_info.keys.join(', ')"></span></div>
                                </template>
                                <template x-if="result.detailed_info.class">
                                    <div class="mt-1"><strong>🏗️ Class:</strong> <span x-text="result.detailed_info.class"></span></div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="!results.organization || results.organization.length === 0" class="text-center py-4 text-gray-500">
                    <p class="text-sm">Clique em "Testar" para verificar os métodos</p>
                </div>
            </div>

            <!-- Products Module -->
            <div class="bg-white rounded-lg card-shadow p-6 module-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">📦</span>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-800">Products Module</h3>
                            <p class="text-sm text-gray-600">13 métodos disponíveis</p>
                        </div>
                    </div>
                    <button
                        @click="testModule('products')"
                        :disabled="testing"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm disabled:opacity-50"
                    >
                        Testar
                    </button>
                </div>
                <div x-show="results.products && results.products.length > 0" class="space-y-2">
                    <template x-for="result in results.products" :key="result.method">
                        <div class="method-card border rounded-lg p-3 hover:bg-gray-50" x-data="{ expanded: false }">
                            <div class="flex items-center justify-between mb-2">
                                <code class="text-sm font-mono text-gray-700" x-text="result.method + '()'"></code>
                                <div class="flex items-center gap-2">
                                    <button x-show="result.success && result.detailed_info" @click="expanded = !expanded" class="text-blue-600 hover:text-blue-800 text-xs">
                                        <span x-show="!expanded">📋 Details</span>
                                        <span x-show="expanded">🔼 Hide</span>
                                    </button>
                                    <span x-show="result.success" class="text-green-600 text-lg">✅</span>
                                    <span x-show="!result.success" class="text-red-600 text-lg">❌</span>
                                </div>
                            </div>
                            <div x-show="result.success && result.result" class="text-xs text-gray-600 mt-1 mb-1" x-text="result.result"></div>
                            <div x-show="result.error" class="text-xs text-red-600 mt-1 mb-1" x-text="result.error"></div>

                            <!-- Detailed Info Section -->
                            <div x-show="expanded && result.success && result.detailed_info" class="mt-2 p-2 bg-gray-50 rounded text-xs" x-transition>
                                <div class="grid grid-cols-2 gap-2">
                                    <template x-if="result.detailed_info.id">
                                        <div><strong>🆔 ID:</strong> <span x-text="result.detailed_info.id"></span></div>
                                    </template>
                                    <template x-if="result.detailed_info.data_id">
                                        <div><strong>📦 Data ID:</strong> <span x-text="result.detailed_info.data_id"></span></div>
                                    </template>
                                    <template x-if="result.detailed_info.api_success">
                                        <div><strong>✅ API Success:</strong> <span x-text="result.detailed_info.api_success"></span></div>
                                    </template>
                                    <template x-if="result.detailed_info.status">
                                        <div><strong>📊 Status:</strong> <span x-text="result.detailed_info.status"></span></div>
                                    </template>
                                    <template x-if="result.detailed_info.operation">
                                        <div><strong>⚙️ Operation:</strong> <span x-text="result.detailed_info.operation"></span></div>
                                    </template>
                                    <template x-if="result.detailed_info.return_type">
                                        <div><strong>🔧 Type:</strong> <span x-text="result.detailed_info.return_type"></span></div>
                                    </template>
                                </div>
                                <template x-if="result.detailed_info.message">
                                    <div class="mt-1"><strong>💬 Message:</strong> <span x-text="result.detailed_info.message"></span></div>
                                </template>
                                <template x-if="result.detailed_info.keys && result.detailed_info.keys.length > 0">
                                    <div class="mt-1"><strong>🔑 Keys:</strong> <span x-text="result.detailed_info.keys.join(', ')"></span></div>
                                </template>
                                <template x-if="result.detailed_info.class">
                                    <div class="mt-1"><strong>🏗️ Class:</strong> <span x-text="result.detailed_info.class"></span></div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="!results.products || results.products.length === 0" class="text-center py-4 text-gray-500">
                    <p class="text-sm">Clique em "Testar" para verificar os métodos</p>
                </div>
            </div>

            <!-- Checkout Module -->
            <div class="bg-white rounded-lg card-shadow p-6 module-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">🛒</span>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-800">Checkout Module</h3>
                            <p class="text-sm text-gray-600">16 métodos disponíveis</p>
                        </div>
                    </div>
                    <button
                        @click="testModule('checkout')"
                        :disabled="testing"
                        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm disabled:opacity-50"
                    >
                        Testar
                    </button>
                </div>
                <div x-show="results.checkout && results.checkout.length > 0" class="space-y-2">
                    <template x-for="result in results.checkout" :key="result.method">
                        <div class="method-card border rounded-lg p-3 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <code class="text-sm font-mono text-gray-700" x-text="result.method + '()'"></code>
                                <span x-show="result.success" class="text-green-600 text-lg">✅</span>
                                <span x-show="!result.success" class="text-red-600 text-lg">❌</span>
                            </div>
                            <div x-show="result.result" class="text-xs text-gray-500 mt-1" x-text="result.result"></div>
                            <div x-show="result.error" class="text-xs text-red-600 mt-1" x-text="result.error"></div>
                        </div>
                    </template>
                </div>
                <div x-show="!results.checkout || results.checkout.length === 0" class="text-center py-4 text-gray-500">
                    <p class="text-sm">Clique em "Testar" para verificar os métodos</p>
                </div>
            </div>

            <!-- Payments Module -->
            <div class="bg-white rounded-lg card-shadow p-6 module-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">💳</span>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-800">Payments Module</h3>
                            <p class="text-sm text-gray-600">16 métodos disponíveis</p>
                        </div>
                    </div>
                    <button
                        @click="testModule('payments')"
                        :disabled="testing"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm disabled:opacity-50"
                    >
                        Testar
                    </button>
                </div>
                <div x-show="results.payments && results.payments.length > 0" class="space-y-2">
                    <template x-for="result in results.payments" :key="result.method">
                        <div class="method-card border rounded-lg p-3 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <code class="text-sm font-mono text-gray-700" x-text="result.method + '()'"></code>
                                <span x-show="result.success" class="text-green-600 text-lg">✅</span>
                                <span x-show="!result.success" class="text-red-600 text-lg">❌</span>
                            </div>
                            <div x-show="result.result" class="text-xs text-gray-500 mt-1" x-text="result.result"></div>
                            <div x-show="result.error" class="text-xs text-red-600 mt-1" x-text="result.error"></div>
                        </div>
                    </template>
                </div>
                <div x-show="!results.payments || results.payments.length === 0" class="text-center py-4 text-gray-500">
                    <p class="text-sm">Clique em "Testar" para verificar os métodos</p>
                </div>
            </div>

            <!-- Customers Module -->
            <div class="bg-white rounded-lg card-shadow p-6 module-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">👥</span>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-800">Customers Module</h3>
                            <p class="text-sm text-gray-600">17 métodos disponíveis</p>
                        </div>
                    </div>
                    <button
                        @click="testModule('customers')"
                        :disabled="testing"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm disabled:opacity-50"
                    >
                        Testar
                    </button>
                </div>
                <div x-show="results.customers && results.customers.length > 0" class="space-y-2">
                    <template x-for="result in results.customers" :key="result.method">
                        <div class="method-card border rounded-lg p-3 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <code class="text-sm font-mono text-gray-700" x-text="result.method + '()'"></code>
                                <span x-show="result.success" class="text-green-600 text-lg">✅</span>
                                <span x-show="!result.success" class="text-red-600 text-lg">❌</span>
                            </div>
                            <div x-show="result.result" class="text-xs text-gray-500 mt-1" x-text="result.result"></div>
                            <div x-show="result.error" class="text-xs text-red-600 mt-1" x-text="result.error"></div>
                        </div>
                    </template>
                </div>
                <div x-show="!results.customers || results.customers.length === 0" class="text-center py-4 text-gray-500">
                    <p class="text-sm">Clique em "Testar" para verificar os métodos</p>
                </div>
            </div>

            <!-- Webhooks Module -->
            <div class="bg-white rounded-lg card-shadow p-6 module-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">🔗</span>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-800">Webhooks Module</h3>
                            <p class="text-sm text-gray-600">15 métodos disponíveis</p>
                        </div>
                    </div>
                    <button
                        @click="testModule('webhooks')"
                        :disabled="testing"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm disabled:opacity-50"
                    >
                        Testar
                    </button>
                </div>
                <div x-show="results.webhooks && results.webhooks.length > 0" class="space-y-2">
                    <template x-for="result in results.webhooks" :key="result.method">
                        <div class="method-card border rounded-lg p-3 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <code class="text-sm font-mono text-gray-700" x-text="result.method + '()'"></code>
                                <span x-show="result.success" class="text-green-600 text-lg">✅</span>
                                <span x-show="!result.success" class="text-red-600 text-lg">❌</span>
                            </div>
                            <div x-show="result.result" class="text-xs text-gray-500 mt-1" x-text="result.result"></div>
                            <div x-show="result.error" class="text-xs text-red-600 mt-1" x-text="result.error"></div>
                        </div>
                    </template>
                </div>
                <div x-show="!results.webhooks || results.webhooks.length === 0" class="text-center py-4 text-gray-500">
                    <p class="text-sm">Clique em "Testar" para verificar os métodos</p>
                </div>
            </div>
        </div>

        <!-- Error Report -->
        <div x-show="hasErrors" class="mt-8 bg-white rounded-lg card-shadow p-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4 flex items-center">
                🚨 Relatório de Erros
                <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-sm rounded-full" x-text="stats.errorMethods"></span>
            </h2>
            <div class="space-y-3">
                <template x-for="error in errorsList" :key="error.id">
                    <div class="border-l-4 border-red-400 bg-red-50 p-4 rounded">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-medium text-red-800" x-text="error.module + '::' + error.method"></h4>
                                <p class="text-red-700 text-sm mt-1" x-text="error.error"></p>
                            </div>
                            <span class="text-xs text-red-500 bg-white px-2 py-1 rounded" x-text="error.timestamp"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </main>

    <script>
        function clubifyTestSuite() {
            return {
                testing: false,
                results: {},
                stats: {
                    totalMethods: 0,
                    workingMethods: 0,
                    errorMethods: 0,
                    successRate: 0
                },

                get hasResults() {
                    return Object.keys(this.results).length > 0;
                },

                get hasErrors() {
                    return this.stats.errorMethods > 0;
                },

                get errorsList() {
                    let errors = [];
                    Object.keys(this.results).forEach(module => {
                        this.results[module].forEach(result => {
                            if (!result.success) {
                                errors.push({
                                    id: `${module}_${result.method}`,
                                    module: module,
                                    method: result.method,
                                    error: result.error,
                                    timestamp: new Date().toLocaleTimeString()
                                });
                            }
                        });
                    });
                    return errors;
                },

                calculateStats() {
                    let total = 0, working = 0, errors = 0;

                    Object.keys(this.results).forEach(module => {
                        this.results[module].forEach(result => {
                            total++;
                            if (result.success) {
                                working++;
                            } else {
                                errors++;
                            }
                        });
                    });

                    this.stats = {
                        totalMethods: total,
                        workingMethods: working,
                        errorMethods: errors,
                        successRate: total > 0 ? Math.round((working / total) * 100) : 0
                    };
                },

                async runAllTests() {
                    this.testing = true;
                    this.results = {};

                    const modules = ['organization', 'products', 'checkout', 'payments', 'customers', 'webhooks'];

                    try {
                        const response = await fetch('/clubify/test-all-methods', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.results = data.results;
                            this.calculateStats();
                        } else {
                            alert('Erro ao executar testes: ' + data.error);
                        }
                    } catch (error) {
                        alert('Erro de conexão: ' + error.message);
                    } finally {
                        this.testing = false;
                    }
                },

                async testModule(moduleName) {
                    this.testing = true;

                    try {
                        const response = await fetch(`/clubify/test-module/${moduleName}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.results[moduleName] = data.results;
                            this.calculateStats();
                        } else {
                            alert(`Erro ao testar módulo ${moduleName}: ` + data.error);
                        }
                    } catch (error) {
                        alert('Erro de conexão: ' + error.message);
                    } finally {
                        this.testing = false;
                    }
                },

                clearResults() {
                    this.results = {};
                    this.stats = {
                        totalMethods: 0,
                        workingMethods: 0,
                        errorMethods: 0,
                        successRate: 0
                    };
                }
            }
        }
    </script>
</body>
</html>