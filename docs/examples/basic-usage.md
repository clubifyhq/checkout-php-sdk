# Exemplos Básicos de Uso - Clubify Checkout SDK

Este guia apresenta exemplos práticos e básicos de como usar o Clubify Checkout SDK em projetos Laravel e PHP, com foco na simplicidade e casos de uso comuns.

## 📋 Índice

- [Configuração Inicial](#configuração-inicial)
- [Exemplos com Laravel Facade](#exemplos-com-laravel-facade)
- [Exemplos PHP Vanilla](#exemplos-php-vanilla)
- [Cenários Comuns](#cenários-comuns)
- [Padrões de Uso](#padrões-de-uso)
- [Dicas e Boas Práticas](#dicas-e-boas-práticas)

## Configuração Inicial

### Laravel

```php
// .env
CLUBIFY_API_KEY=sua_api_key_aqui
CLUBIFY_ORGANIZATION_ID=sua_organization_id
CLUBIFY_ENVIRONMENT=sandbox

// No seu Controller
use ClubifyCheckout\Facades\Clubify;
```

### PHP Vanilla

```php
<?php

require_once 'vendor/autoload.php';

use ClubifyCheckout\ClubifyCheckout;

$clubify = new ClubifyCheckout([
    'api_key' => 'sua_api_key_aqui',
    'organization_id' => 'sua_organization_id',
    'environment' => 'sandbox'
]);
```

## Exemplos com Laravel Facade

### 1. Criando um Produto Simples

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use ClubifyCheckout\Facades\Clubify;

class ProductController extends Controller
{
    public function create(Request $request)
    {
        // Dados do produto
        $productData = [
            'name' => 'Curso de PHP Avançado',
            'description' => 'Aprenda PHP do zero ao avançado',
            'price' => 19900, // R$ 199,00 em centavos
            'type' => 'digital',
            'active' => true
        ];

        try {
            // Cria o produto usando a Facade
            $product = Clubify::createProduct($productData);

            return response()->json([
                'success' => true,
                'product' => $product,
                'message' => 'Produto criado com sucesso!'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar produto: ' . $e->getMessage()
            ], 400);
        }
    }

    public function list()
    {
        // Lista produtos com paginação
        $products = Clubify::listProducts([
            'page' => request('page', 1),
            'limit' => request('limit', 10),
            'active' => true
        ]);

        return response()->json($products);
    }
}
```

### 2. Criando uma Oferta Completa

```php
<?php

namespace App\Http\Controllers;

use ClubifyCheckout\Facades\Clubify;

class OfferController extends Controller
{
    public function createCompleteOffer()
    {
        try {
            // 1. Criar produto principal
            $mainProduct = Clubify::createProduct([
                'name' => 'Curso Master de Laravel',
                'description' => 'O curso mais completo de Laravel do Brasil',
                'price' => 39900, // R$ 399,00
                'type' => 'digital'
            ]);

            // 2. Criar order bump
            $orderBump = Clubify::createProduct([
                'name' => 'Bônus: 100 Templates Laravel',
                'description' => 'Templates prontos para seus projetos',
                'price' => 9900, // R$ 99,00
                'type' => 'digital'
            ]);

            // 3. Criar oferta
            $offer = Clubify::createOffer([
                'name' => 'Oferta Master Laravel',
                'description' => 'Torne-se um expert em Laravel',
                'type' => 'single_product',
                'active' => true,
                'layout' => [
                    'theme' => 'modern',
                    'primary_color' => '#3B82F6',
                    'show_testimonials' => true,
                    'show_guarantee' => true
                ]
            ]);

            // 4. Adicionar produto principal
            Clubify::addProductToOffer($offer['id'], $mainProduct['id'], [
                'position' => 1,
                'required' => true
            ]);

            // 5. Configurar order bump
            Clubify::configureOrderBump($offer['id'], [
                'product_id' => $orderBump['id'],
                'title' => '🎁 Oferta Especial: +100 Templates',
                'description' => 'Adicione 100 templates prontos por apenas R$ 99',
                'position' => 'after_products',
                'discount_percentage' => 50, // 50% de desconto
                'show_original_price' => true
            ]);

            return response()->json([
                'success' => true,
                'offer' => $offer,
                'checkout_url' => Clubify::getCheckoutUrl($offer['id'])
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
```

### 3. Processando um Checkout

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use ClubifyCheckout\Facades\Clubify;

class CheckoutController extends Controller
{
    public function show($offerId)
    {
        try {
            // Busca dados da oferta
            $offer = Clubify::getOffer($offerId);

            if (!$offer || !$offer['active']) {
                abort(404, 'Oferta não encontrada');
            }

            // Cria sessão de checkout
            $checkout = Clubify::createCheckout([
                'offer_id' => $offerId,
                'expires_in' => 3600, // 1 hora
                'utm_source' => request('utm_source'),
                'utm_campaign' => request('utm_campaign'),
                'utm_medium' => request('utm_medium')
            ]);

            return view('checkout.show', [
                'offer' => $offer,
                'checkout' => $checkout
            ]);

        } catch (Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function process(Request $request, $checkoutId)
    {
        // Validação
        $request->validate([
            'customer.name' => 'required|string|max:255',
            'customer.email' => 'required|email',
            'customer.cpf' => 'required|cpf', // Usando validation rule brasileira
            'customer.phone' => 'required|telefone',
            'payment.method' => 'required|in:credit_card,pix,boleto',
        ]);

        try {
            // Processa o checkout
            $result = Clubify::processCheckout($checkoutId, [
                'customer' => $request->input('customer'),
                'payment' => $request->input('payment'),
                'billing_address' => $request->input('billing_address')
            ]);

            if ($result['status'] === 'approved') {
                return redirect()->route('checkout.success', $result['order_id']);
            } else {
                return back()->withErrors(['payment' => 'Pagamento não aprovado']);
            }

        } catch (Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }
}
```

### 4. Gerenciando Customers

```php
<?php

namespace App\Http\Controllers;

use ClubifyCheckout\Facades\Clubify;

class CustomerController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:customers',
            'cpf' => 'required|cpf',
            'phone' => 'required|telefone',
            'birth_date' => 'required|data_brasileira'
        ]);

        try {
            $customer = Clubify::createCustomer([
                'name' => $request->name,
                'email' => $request->email,
                'cpf' => $request->cpf,
                'phone' => $request->phone,
                'birth_date' => $request->birth_date,
                'tags' => ['lead', 'website'],
                'source' => 'website_form'
            ]);

            // Adiciona a lista de email marketing
            Clubify::addCustomerToEmailList($customer['id'], 'newsletter');

            return response()->json([
                'success' => true,
                'customer' => $customer
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function search(Request $request)
    {
        $query = $request->input('q');

        // Busca por email, nome ou CPF
        $customers = Clubify::searchCustomers([
            'query' => $query,
            'fields' => ['email', 'name', 'cpf'],
            'limit' => 20
        ]);

        return response()->json($customers);
    }
}
```

### 5. Configurando Webhooks

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use ClubifyCheckout\Facades\Clubify;

class WebhookController extends Controller
{
    public function setup()
    {
        try {
            // Configura webhooks principais
            $webhooks = [
                [
                    'event' => 'order.completed',
                    'url' => route('webhooks.order.completed'),
                    'active' => true
                ],
                [
                    'event' => 'payment.approved',
                    'url' => route('webhooks.payment.approved'),
                    'active' => true
                ],
                [
                    'event' => 'payment.failed',
                    'url' => route('webhooks.payment.failed'),
                    'active' => true
                ]
            ];

            foreach ($webhooks as $webhook) {
                Clubify::createWebhook($webhook);
            }

            return response()->json(['message' => 'Webhooks configurados com sucesso']);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function handleOrderCompleted(Request $request)
    {
        // Valida assinatura do webhook
        if (!Clubify::validateWebhookSignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $order = $request->input('data');

        // Lógica de negócio para pedido concluído
        // Ex: enviar email de boas-vindas, liberar acesso ao produto, etc.

        logger()->info('Order completed', ['order_id' => $order['id']]);

        return response()->json(['status' => 'processed']);
    }
}
```

## Exemplos PHP Vanilla

### 1. Script Simples de Checkout

```php
<?php

require_once 'vendor/autoload.php';

use ClubifyCheckout\ClubifyCheckout;

// Configuração
$clubify = new ClubifyCheckout([
    'api_key' => 'sua_api_key',
    'organization_id' => 'sua_org_id',
    'environment' => 'sandbox'
]);

// Criar produto
$product = $clubify->products()->create([
    'name' => 'Ebook: PHP para Iniciantes',
    'price' => 2990, // R$ 29,90
    'type' => 'digital'
]);

echo "Produto criado: {$product['name']} (ID: {$product['id']})\n";

// Criar oferta
$offer = $clubify->offers()->create([
    'name' => 'Oferta Ebook PHP',
    'type' => 'single_product'
]);

// Adicionar produto à oferta
$clubify->offers()->addProduct($offer['id'], $product['id']);

// Gerar URL de checkout
$checkoutUrl = $clubify->checkout()->getUrl($offer['id']);

echo "Checkout disponível em: {$checkoutUrl}\n";
```

### 2. Sistema de Relatórios Simples

```php
<?php

require_once 'vendor/autoload.php';

use ClubifyCheckout\ClubifyCheckout;

$clubify = new ClubifyCheckout([/* config */]);

// Relatório de vendas do mês
$startDate = date('Y-m-01'); // Primeiro dia do mês
$endDate = date('Y-m-t');    // Último dia do mês

$orders = $clubify->orders()->list([
    'status' => 'completed',
    'created_from' => $startDate,
    'created_to' => $endDate,
    'limit' => 1000
]);

$totalRevenue = 0;
$totalOrders = count($orders['data']);

foreach ($orders['data'] as $order) {
    $totalRevenue += $order['total_amount'];
}

echo "=== RELATÓRIO DE VENDAS ===\n";
echo "Período: {$startDate} a {$endDate}\n";
echo "Total de pedidos: {$totalOrders}\n";
echo "Receita total: R$ " . number_format($totalRevenue / 100, 2, ',', '.') . "\n";
echo "Ticket médio: R$ " . number_format(($totalRevenue / $totalOrders) / 100, 2, ',', '.') . "\n";

// Top 5 produtos mais vendidos
$productSales = [];
foreach ($orders['data'] as $order) {
    foreach ($order['items'] as $item) {
        $productId = $item['product_id'];
        if (!isset($productSales[$productId])) {
            $productSales[$productId] = [
                'name' => $item['product_name'],
                'quantity' => 0,
                'revenue' => 0
            ];
        }
        $productSales[$productId]['quantity'] += $item['quantity'];
        $productSales[$productId]['revenue'] += $item['total_amount'];
    }
}

arsort($productSales);
$top5 = array_slice($productSales, 0, 5, true);

echo "\n=== TOP 5 PRODUTOS ===\n";
foreach ($top5 as $productId => $data) {
    echo "{$data['name']}: {$data['quantity']} vendas - R$ " .
         number_format($data['revenue'] / 100, 2, ',', '.') . "\n";
}
```

## Cenários Comuns

### 1. Landing Page com Checkout

```php
// routes/web.php
Route::get('/oferta/{slug}', [LandingController::class, 'show']);
Route::post('/oferta/{slug}/checkout', [LandingController::class, 'checkout']);

// LandingController.php
class LandingController extends Controller
{
    public function show($slug)
    {
        // Busca oferta por slug
        $offer = Clubify::getOfferBySlug($slug);

        if (!$offer || !$offer['active']) {
            abort(404);
        }

        // Dados para a landing page
        return view('landing.show', [
            'offer' => $offer,
            'products' => $offer['products'],
            'testimonials' => $offer['testimonials'] ?? [],
            'checkout_url' => route('landing.checkout', $slug)
        ]);
    }

    public function checkout($slug, Request $request)
    {
        $offer = Clubify::getOfferBySlug($slug);

        // Validação rápida
        $request->validate([
            'email' => 'required|email',
            'name' => 'required|string'
        ]);

        // Cria checkout one-click
        $checkout = Clubify::createOneClickCheckout($offer['id'], [
            'customer_email' => $request->email,
            'customer_name' => $request->name,
            'payment_method' => $request->payment_method ?? 'pix'
        ]);

        return redirect($checkout['payment_url']);
    }
}
```

### 2. Sistema de Afiliados Simples

```php
// AffiliateController.php
class AffiliateController extends Controller
{
    public function track($affliateCode, $offerId)
    {
        // Registra click do afiliado
        Clubify::trackAffiliateClick([
            'affiliate_code' => $affliateCode,
            'offer_id' => $offerId,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'referrer' => request()->header('referer')
        ]);

        // Redireciona para checkout com tracking
        $checkoutUrl = Clubify::getCheckoutUrl($offerId, [
            'affiliate_code' => $affliateCode,
            'utm_source' => 'affiliate',
            'utm_campaign' => $affliateCode
        ]);

        return redirect($checkoutUrl);
    }

    public function commissions($affiliateCode)
    {
        // Busca comissões do afiliado
        $commissions = Clubify::getAffiliateCommissions($affiliateCode, [
            'period' => request('period', 'current_month'),
            'status' => 'paid'
        ]);

        return response()->json($commissions);
    }
}
```

### 3. Integração com Email Marketing

```php
// EmailMarketingService.php
class EmailMarketingService
{
    public function syncNewCustomer($customerId)
    {
        $customer = Clubify::getCustomer($customerId);

        // Adiciona à lista principal
        Clubify::addCustomerToEmailList($customerId, 'customers');

        // Segmentação automática
        if ($customer['total_spent'] > 50000) { // R$ 500+
            Clubify::addCustomerToEmailList($customerId, 'vip_customers');
        }

        if (count($customer['orders']) === 1) {
            Clubify::addCustomerToEmailList($customerId, 'first_time_buyers');
        }

        // Trigger de email de boas-vindas
        $this->sendWelcomeEmail($customer);
    }

    public function sendWelcomeEmail($customer)
    {
        // Usa o sistema de email do Clubify
        Clubify::sendEmail([
            'template' => 'welcome',
            'to' => $customer['email'],
            'variables' => [
                'customer_name' => $customer['name'],
                'first_product' => $customer['orders'][0]['items'][0]['product_name']
            ]
        ]);
    }
}
```

## Padrões de Uso

### 1. Service Pattern

```php
// app/Services/CheckoutService.php
class CheckoutService
{
    public function createProductOffer($productData, $offerData = [])
    {
        // Cria produto
        $product = Clubify::createProduct($productData);

        // Cria oferta
        $offer = Clubify::createOffer(array_merge([
            'name' => $productData['name'],
            'type' => 'single_product'
        ], $offerData));

        // Vincula produto
        Clubify::addProductToOffer($offer['id'], $product['id']);

        return [
            'product' => $product,
            'offer' => $offer,
            'checkout_url' => Clubify::getCheckoutUrl($offer['id'])
        ];
    }

    public function processQuickCheckout($email, $offerId, $paymentData)
    {
        // Busca ou cria customer
        $customer = Clubify::findOrCreateCustomer(['email' => $email]);

        // Cria checkout
        $checkout = Clubify::createCheckout([
            'offer_id' => $offerId,
            'customer_id' => $customer['id']
        ]);

        // Processa pagamento
        return Clubify::processPayment($checkout['id'], $paymentData);
    }
}
```

### 2. Repository Pattern

```php
// app/Repositories/ClubifyRepository.php
class ClubifyRepository
{
    public function findActiveOffers($filters = [])
    {
        return Clubify::listOffers(array_merge([
            'active' => true,
            'limit' => 50
        ], $filters));
    }

    public function getOfferWithProducts($offerId)
    {
        $offer = Clubify::getOffer($offerId);
        $offer['products'] = Clubify::getOfferProducts($offerId);

        return $offer;
    }

    public function getCustomerOrders($customerId, $limit = 20)
    {
        return Clubify::listOrders([
            'customer_id' => $customerId,
            'limit' => $limit,
            'sort' => 'created_at:desc'
        ]);
    }
}
```

## Dicas e Boas Práticas

### 1. Cache Inteligente

```php
// Use cache para dados que mudam pouco
$offer = cache()->remember("offer.{$offerId}", 3600, function () use ($offerId) {
    return Clubify::getOffer($offerId);
});

// Cache de produtos com invalidação
$products = cache()->remember('products.active', 1800, function () {
    return Clubify::listProducts(['active' => true, 'limit' => 100]);
});
```

### 2. Tratamento de Erros

```php
try {
    $result = Clubify::processCheckout($checkoutId, $data);
} catch (ClubifyCheckout\Exceptions\PaymentException $e) {
    // Erro específico de pagamento
    return back()->withErrors(['payment' => $e->getMessage()]);
} catch (ClubifyCheckout\Exceptions\ValidationException $e) {
    // Erro de validação
    return back()->withErrors($e->getErrors());
} catch (Exception $e) {
    // Erro genérico
    logger()->error('Checkout error', ['error' => $e->getMessage()]);
    return back()->withErrors(['message' => 'Erro interno. Tente novamente.']);
}
```

### 3. Validação de Entrada

```php
// Sempre valide dados antes de enviar para a API
$validated = $request->validate([
    'product.name' => 'required|string|max:255',
    'product.price' => 'required|integer|min:100', // Mínimo R$ 1,00
    'product.type' => 'required|in:digital,physical,service'
]);

$product = Clubify::createProduct($validated['product']);
```

### 4. Logging e Monitoramento

```php
// Log de operações importantes
logger()->info('Product created', [
    'product_id' => $product['id'],
    'name' => $product['name'],
    'user_id' => auth()->id()
]);

// Métricas personalizadas
Clubify::trackMetric('product.created', [
    'product_type' => $product['type'],
    'price_range' => $this->getPriceRange($product['price'])
]);
```

### 5. Jobs Assíncronos

```php
// Processe operações pesadas em background
dispatch(new ProcessOrderJob($orderId));
dispatch(new SendWelcomeEmailJob($customerId))->delay(now()->addMinutes(5));
dispatch(new SyncCustomerDataJob($customerId))->onQueue('low-priority');
```

---

## Conclusão

Estes exemplos cobrem os casos de uso mais comuns do Clubify Checkout SDK. Para cenários mais avançados, consulte:

- 🚀 [Casos de Uso Avançados](advanced-usage.md)
- 🔧 [Integração Completa Laravel](../laravel/)
- 📚 [Documentação dos Módulos](../modules/)
- ❓ [FAQ e Troubleshooting](../troubleshooting.md)

Lembre-se: comece simples e evolua conforme suas necessidades!