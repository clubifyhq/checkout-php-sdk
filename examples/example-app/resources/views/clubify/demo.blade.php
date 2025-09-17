<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clubify Checkout SDK - Demonstração</title>
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
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="gradient-bg text-white py-8">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <h1 class="text-4xl font-bold mb-2">🚀 Clubify Checkout SDK</h1>
                <p class="text-xl opacity-90">Demonstração PHP/Laravel</p>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Status Card -->
        <div class="bg-white rounded-lg card-shadow p-6 mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-semibold text-gray-800">Status do SDK</h2>
                <div class="flex items-center space-x-2">
                    @if(str_contains($sdkStatus, 'inicializado e pronto') || $sdkStatus === 'Conectado')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            {{ $sdkStatus }}
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                            {{ $sdkStatus }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-medium text-gray-700 mb-1">Tenant ID</h3>
                    <p class="text-sm text-gray-600 font-mono">{{ $config['tenant_id'] }}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-medium text-gray-700 mb-1">Ambiente</h3>
                    <p class="text-sm text-gray-600">{{ $config['environment'] }}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-medium text-gray-700 mb-1">URL Base</h3>
                    <p class="text-sm text-gray-600 font-mono">{{ $config['base_url'] }}</p>
                </div>
            </div>
        </div>

        <!-- Interactive Tests -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8" x-data="clubifyDemo()">
            <!-- Tests Panel -->
            <div class="bg-white rounded-lg card-shadow p-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Testes Interativos</h2>

                <div class="space-y-4">
                    <!-- Status Test -->
                    <div class="border rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-medium text-gray-700">Status do SDK</h3>
                            <button
                                @click="testStatus()"
                                :disabled="loading"
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
                            >
                                <span x-show="!loading">Testar</span>
                                <span x-show="loading">🔄 Testando...</span>
                            </button>
                        </div>
                        <p class="text-sm text-gray-600">Verifica o status geral do SDK e suas configurações</p>
                    </div>

                    <!-- Products Test -->
                    <div class="border rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-medium text-gray-700">Módulo de Produtos</h3>
                            <button
                                @click="testProducts()"
                                :disabled="loading"
                                class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50"
                            >
                                <span x-show="!loading">Testar</span>
                                <span x-show="loading">🔄 Testando...</span>
                            </button>
                        </div>
                        <p class="text-sm text-gray-600">Testa o carregamento do módulo de produtos</p>
                    </div>

                    <!-- Checkout Test -->
                    <div class="border rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-medium text-gray-700">Módulo de Checkout</h3>
                            <button
                                @click="testCheckout()"
                                :disabled="loading"
                                class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 disabled:opacity-50"
                            >
                                <span x-show="!loading">Testar</span>
                                <span x-show="loading">🔄 Testando...</span>
                            </button>
                        </div>
                        <p class="text-sm text-gray-600">Testa o carregamento do módulo de checkout</p>
                    </div>

                    <!-- Organization Test -->
                    <div class="border rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-medium text-gray-700">Módulo de Organização</h3>
                            <button
                                @click="testOrganization()"
                                :disabled="loading"
                                class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50"
                            >
                                <span x-show="!loading">Testar</span>
                                <span x-show="loading">🔄 Testando...</span>
                            </button>
                        </div>
                        <p class="text-sm text-gray-600">Testa o carregamento do módulo de organização</p>
                    </div>
                </div>
            </div>

            <!-- Results Panel -->
            <div class="bg-white rounded-lg card-shadow p-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Resultados</h2>

                <div
                    x-show="results.length === 0"
                    class="text-center py-8 text-gray-500"
                >
                    <svg class="w-12 h-12 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p>Execute um teste para ver os resultados</p>
                </div>

                <div
                    x-show="results.length > 0"
                    class="space-y-4 max-h-96 overflow-y-auto"
                >
                    <template x-for="result in results" :key="result.id">
                        <div
                            class="border rounded-lg p-4"
                            :class="result.success ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium" :class="result.success ? 'text-green-800' : 'text-red-800'">
                                    <span x-text="result.test"></span>
                                    <span x-show="result.success" class="ml-2">✅</span>
                                    <span x-show="!result.success" class="ml-2">❌</span>
                                </h4>
                                <small class="text-gray-500" x-text="result.timestamp"></small>
                            </div>
                            <p
                                class="text-sm"
                                :class="result.success ? 'text-green-700' : 'text-red-700'"
                                x-text="result.message"
                            ></p>
                            <template x-if="result.error">
                                <div class="mt-2 p-2 bg-gray-100 rounded text-xs font-mono text-gray-600">
                                    <div><strong>Erro:</strong> <span x-text="result.error"></span></div>
                                    <template x-if="result.file">
                                        <div><strong>Arquivo:</strong> <span x-text="result.file + ':' + result.line"></span></div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Advanced Testing -->
        <div class="mt-8 bg-white rounded-lg card-shadow p-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">🧪 Testes Avançados</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="{{ route('clubify.test.all.page') }}" class="block p-4 border rounded-lg hover:bg-gray-50 transition-colors group">
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-3 group-hover:scale-110 transition-transform">🧬</span>
                        <h3 class="font-medium text-gray-800">Teste Completo de Métodos</h3>
                    </div>
                    <p class="text-sm text-gray-600">Análise abrangente de todos os 94+ métodos disponíveis no SDK, organizados por módulos com detalhes de retorno da API</p>
                    <div class="mt-2 flex items-center text-blue-600 text-sm">
                        <span>Executar todos os testes</span>
                        <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </a>
                <div class="p-4 border rounded-lg bg-gray-50">
                    <div class="flex items-center mb-2">
                        <span class="text-2xl mr-3">📊</span>
                        <h3 class="font-medium text-gray-800">Estatísticas de Cobertura</h3>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Módulos:</span>
                            <span class="font-medium">6 módulos</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Métodos:</span>
                            <span class="font-medium">94+ métodos</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Cobertura:</span>
                            <span class="font-medium text-green-600">100%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documentation Links -->
        <div class="mt-8 bg-white rounded-lg card-shadow p-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">📚 Documentação</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <a href="#" class="block p-4 border rounded-lg hover:bg-gray-50 transition-colors">
                    <h3 class="font-medium text-gray-800 mb-1">🚀 Início Rápido</h3>
                    <p class="text-sm text-gray-600">Primeiros passos com o SDK</p>
                </a>
                <a href="#" class="block p-4 border rounded-lg hover:bg-gray-50 transition-colors">
                    <h3 class="font-medium text-gray-800 mb-1">📖 Guia de API</h3>
                    <p class="text-sm text-gray-600">Referência completa da API</p>
                </a>
                <a href="#" class="block p-4 border rounded-lg hover:bg-gray-50 transition-colors">
                    <h3 class="font-medium text-gray-800 mb-1">🔧 Laravel Integration</h3>
                    <p class="text-sm text-gray-600">Integração avançada com Laravel</p>
                </a>
            </div>
        </div>
    </main>

    <script>
        function clubifyDemo() {
            return {
                loading: false,
                results: [],

                addResult(test, success, message, error = null, file = null, line = null) {
                    this.results.unshift({
                        id: Date.now(),
                        test,
                        success,
                        message,
                        error,
                        file,
                        line,
                        timestamp: new Date().toLocaleTimeString()
                    });

                    // Manter apenas os últimos 10 resultados
                    if (this.results.length > 10) {
                        this.results = this.results.slice(0, 10);
                    }
                },

                async testStatus() {
                    this.loading = true;
                    try {
                        const response = await fetch('/clubify/status', {
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });
                        const data = await response.json();

                        if (data.success) {
                            this.addResult('Status do SDK', true, `${data.status.sdk_status} - ${data.status.available_modules.length} módulos disponíveis`);
                        } else {
                            this.addResult('Status do SDK', false, 'Falha ao verificar status', data.error);
                        }
                    } catch (error) {
                        this.addResult('Status do SDK', false, 'Erro de conexão', error.message);
                    } finally {
                        this.loading = false;
                    }
                },

                async testProducts() {
                    this.loading = true;
                    try {
                        const response = await fetch('/clubify/test-products', {
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });
                        const data = await response.json();

                        if (data.success) {
                            this.addResult('Módulo de Produtos', true, data.message);
                        } else {
                            this.addResult('Módulo de Produtos', false, 'Falha no teste', data.error, data.file, data.line);
                        }
                    } catch (error) {
                        this.addResult('Módulo de Produtos', false, 'Erro de conexão', error.message);
                    } finally {
                        this.loading = false;
                    }
                },

                async testCheckout() {
                    this.loading = true;
                    try {
                        const response = await fetch('/clubify/test-checkout', {
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });
                        const data = await response.json();

                        if (data.success) {
                            this.addResult('Módulo de Checkout', true, data.message);
                        } else {
                            this.addResult('Módulo de Checkout', false, 'Falha no teste', data.error, data.file, data.line);
                        }
                    } catch (error) {
                        this.addResult('Módulo de Checkout', false, 'Erro de conexão', error.message);
                    } finally {
                        this.loading = false;
                    }
                },

                async testOrganization() {
                    this.loading = true;
                    try {
                        const response = await fetch('/clubify/test-organization', {
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });
                        const data = await response.json();

                        if (data.success) {
                            this.addResult('Módulo de Organização', true, data.message);
                        } else {
                            this.addResult('Módulo de Organização', false, 'Falha no teste', data.error, data.file, data.line);
                        }
                    } catch (error) {
                        this.addResult('Módulo de Organização', false, 'Erro de conexão', error.message);
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }
    </script>
</body>
</html>