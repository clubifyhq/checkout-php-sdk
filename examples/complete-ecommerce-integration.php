<?php

/**
 * Exemplo Completo: Integração E-commerce
 *
 * Este exemplo demonstra um fluxo completo de e-commerce usando o SDK Clubify Checkout,
 * incluindo criação de produto, cliente, pedido, processamento de pagamento e notificações.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Exceptions\PaymentDeclinedException;
use Clubify\Checkout\Exceptions\ValidationException;

class CompleteEcommerceExample
{
    private ClubifyCheckoutSDK $sdk;

    public function __construct()
    {
        // Inicializar SDK
        $this->sdk = new ClubifyCheckoutSDK([
            'api_url' => 'https://api.clubify.com',
            'tenant_id' => 'seu-tenant-id',
            'api_key' => 'sua-api-key',
            'secret_key' => 'sua-secret-key',
            'debug' => true
        ]);
    }

    public function runCompleteFlow()
    {
        try {
            echo "=== INICIANDO FLUXO COMPLETO DE E-COMMERCE ===\n\n";

            // 1. Criar produto
            $product = $this->createProduct();
            echo "✅ Produto criado: {$product['name']} (ID: {$product['id']})\n";

            // 2. Criar/buscar cliente
            $customer = $this->createOrGetCustomer();
            echo "✅ Cliente processado: {$customer['name']} (ID: {$customer['id']})\n";

            // 3. Criar pedido
            $order = $this->createOrder($customer['id'], $product);
            echo "✅ Pedido criado: #{$order['id']} - R$ " . number_format($order['total'] / 100, 2) . "\n";

            // 4. Processar pagamento
            $payment = $this->processPayment($order, $customer);
            echo "✅ Pagamento processado: {$payment['status']} (ID: {$payment['id']})\n";

            // 5. Atualizar status do pedido
            if ($payment['status'] === 'approved') {
                $this->updateOrderStatus($order['id'], 'confirmed');
                echo "✅ Status do pedido atualizado para: confirmed\n";

                // 6. Enviar notificações
                $this->sendNotifications($order, $customer);
                echo "✅ Notificações enviadas\n";

                // 7. Processar entrega
                $this->processShipping($order);
                echo "✅ Processo de entrega iniciado\n";
            }

            echo "\n=== FLUXO COMPLETO FINALIZADO COM SUCESSO ===\n";

        } catch (Exception $e) {
            echo "❌ Erro no fluxo: " . $e->getMessage() . "\n";
            $this->handleError($e);
        }
    }

    private function createProduct(): array
    {
        $productData = [
            'name' => 'Smartphone Premium XYZ',
            'description' => 'Smartphone de última geração com câmera profissional',
            'price' => 199999, // R$ 1.999,99
            'currency' => 'BRL',
            'category' => 'electronics',
            'sku' => 'SPH-XYZ-001',
            'stock_quantity' => 50,
            'images' => [
                'https://exemplo.com/smartphone-1.jpg',
                'https://exemplo.com/smartphone-2.jpg'
            ],
            'specifications' => [
                'brand' => 'TechBrand',
                'model' => 'XYZ Pro',
                'storage' => '256GB',
                'ram' => '8GB',
                'color' => 'Preto'
            ],
            'shipping' => [
                'weight' => 200, // gramas
                'dimensions' => [
                    'length' => 15,
                    'width' => 7,
                    'height' => 1
                ],
                'free_shipping' => true
            ]
        ];

        return $this->sdk->products()->createProduct($productData);
    }

    private function createOrGetCustomer(): array
    {
        $email = 'cliente.exemplo@email.com';

        try {
            // Tentar buscar cliente existente
            return $this->sdk->customers()->getCustomerByEmail($email);
        } catch (Exception $e) {
            // Cliente não existe, criar novo
            $customerData = [
                'name' => 'João Silva Santos',
                'email' => $email,
                'document' => '12345678901',
                'phone' => '+5511999888777',
                'birth_date' => '1990-05-15',
                'address' => [
                    'street' => 'Rua das Tecnologias, 123',
                    'complement' => 'Apto 45B',
                    'neighborhood' => 'Tech District',
                    'city' => 'São Paulo',
                    'state' => 'SP',
                    'postal_code' => '01234-567',
                    'country' => 'BR'
                ],
                'preferences' => [
                    'language' => 'pt-BR',
                    'currency' => 'BRL',
                    'marketing_emails' => true,
                    'sms_notifications' => true
                ]
            ];

            return $this->sdk->customers()->createCustomer($customerData);
        }
    }

    private function createOrder(string $customerId, array $product): array
    {
        $orderData = [
            'customer_id' => $customerId,
            'items' => [
                [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => 1,
                    'sku' => $product['sku']
                ]
            ],
            'subtotal' => $product['price'],
            'shipping_amount' => 0, // Frete grátis
            'discount_amount' => 0,
            'total' => $product['price'],
            'currency' => 'BRL',
            'payment_method' => 'credit_card',
            'shipping_address' => [
                'street' => 'Rua das Tecnologias, 123',
                'complement' => 'Apto 45B',
                'neighborhood' => 'Tech District',
                'city' => 'São Paulo',
                'state' => 'SP',
                'postal_code' => '01234-567',
                'country' => 'BR'
            ],
            'metadata' => [
                'source' => 'website',
                'campaign' => 'black_friday_2024',
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'smartphones'
            ]
        ];

        return $this->sdk->orders()->createOrder($orderData);
    }

    private function processPayment(array $order, array $customer): array
    {
        // Simular dados do cartão (em produção, isso viria do frontend)
        $paymentData = [
            'order_id' => $order['id'],
            'amount' => $order['total'],
            'currency' => $order['currency'],
            'payment_method' => 'credit_card',
            'card_data' => [
                'number' => '4111111111111111', // Cartão de teste Visa
                'expiry_month' => '12',
                'expiry_year' => '2025',
                'cvv' => '123',
                'holder_name' => $customer['name']
            ],
            'customer' => [
                'id' => $customer['id'],
                'email' => $customer['email'],
                'document' => $customer['document']
            ],
            'billing_address' => $customer['address'],
            'installments' => 1,
            'capture' => true,
            'soft_descriptor' => 'LOJA-TECH'
        ];

        try {
            return $this->sdk->payments()->processPayment($paymentData);
        } catch (PaymentDeclinedException $e) {
            echo "⚠️ Pagamento recusado: " . $e->getDeclineReason() . "\n";

            // Tentar com método alternativo (PIX)
            return $this->processPixPayment($order, $customer);
        }
    }

    private function processPixPayment(array $order, array $customer): array
    {
        echo "🔄 Tentando pagamento alternativo via PIX...\n";

        $pixData = [
            'order_id' => $order['id'],
            'amount' => $order['total'],
            'currency' => $order['currency'],
            'payment_method' => 'pix',
            'customer' => [
                'id' => $customer['id'],
                'email' => $customer['email'],
                'document' => $customer['document']
            ],
            'expires_in' => 3600 // 1 hora para pagamento
        ];

        $pixPayment = $this->sdk->payments()->processPayment($pixData);

        echo "💰 PIX gerado:\n";
        echo "   QR Code: {$pixPayment['qr_code']}\n";
        echo "   Chave PIX: {$pixPayment['pix_key']}\n";
        echo "   Expira em: {$pixPayment['expires_at']}\n";

        return $pixPayment;
    }

    private function updateOrderStatus(string $orderId, string $status): void
    {
        $this->sdk->orders()->updateOrderStatus($orderId, $status);
    }

    private function sendNotifications(array $order, array $customer): void
    {
        // Notificação por email
        $this->sdk->notifications()->sendEmail([
            'to' => $customer['email'],
            'template' => 'order_confirmation',
            'subject' => 'Pedido confirmado #' . $order['id'],
            'variables' => [
                'customer_name' => $customer['name'],
                'order_id' => $order['id'],
                'order_total' => 'R$ ' . number_format($order['total'] / 100, 2),
                'tracking_url' => "https://loja.com/tracking/{$order['id']}"
            ]
        ]);

        // Notificação por SMS
        if (!empty($customer['phone'])) {
            $this->sdk->notifications()->sendSMS([
                'to' => $customer['phone'],
                'message' => "Olá {$customer['name']}! Seu pedido #{$order['id']} foi confirmado. Acompanhe em: https://loja.com/tracking/{$order['id']}"
            ]);
        }

        // Webhook para sistema interno
        $this->sdk->notifications()->sendWebhook([
            'url' => 'https://sistema-interno.com/webhooks/orders',
            'payload' => [
                'event' => 'order.confirmed',
                'order_id' => $order['id'],
                'customer_id' => $customer['id'],
                'total' => $order['total'],
                'timestamp' => time()
            ]
        ]);
    }

    private function processShipping(array $order): void
    {
        // Calcular frete e prazo
        $shippingOptions = $this->sdk->shipping()->calculateShipping($order['id'], [
            'postal_code' => $order['shipping_address']['postal_code']
        ]);

        echo "📦 Opções de entrega:\n";
        foreach ($shippingOptions as $option) {
            echo "   - {$option['name']}: R$ " . number_format($option['price'] / 100, 2) .
                 " ({$option['delivery_time']} dias)\n";
        }

        // Selecionar opção mais rápida
        $selectedOption = $shippingOptions[0];

        // Agendar envio
        $shipping = $this->sdk->shipping()->scheduleShipping($order['id'], [
            'method' => $selectedOption['code'],
            'estimated_delivery' => date('Y-m-d', strtotime("+{$selectedOption['delivery_time']} days"))
        ]);

        // Atualizar status do pedido
        $this->updateOrderStatus($order['id'], 'processing');

        echo "✅ Envio agendado: {$shipping['tracking_code']}\n";
    }

    private function handleError(Exception $e): void
    {
        // Log do erro
        error_log("Erro no fluxo e-commerce: " . $e->getMessage());

        // Notificar equipe de suporte
        if ($e instanceof PaymentDeclinedException) {
            // Erro de pagamento - notificar time financeiro
            echo "💳 Enviando alerta para time financeiro...\n";
        } elseif ($e instanceof ValidationException) {
            // Erro de validação - notificar time de desenvolvimento
            echo "🐛 Enviando alerta para time de desenvolvimento...\n";
        } else {
            // Erro geral - notificar time de operações
            echo "🚨 Enviando alerta para time de operações...\n";
        }
    }

    public function demonstrateAnalytics(): void
    {
        echo "\n=== ANALYTICS E RELATÓRIOS ===\n";

        // Métricas de vendas
        $salesMetrics = $this->sdk->analytics()->getSalesMetrics([
            'period' => '30_days',
            'group_by' => 'day'
        ]);

        echo "📊 Vendas dos últimos 30 dias:\n";
        echo "   Total: R$ " . number_format($salesMetrics['total_revenue'] / 100, 2) . "\n";
        echo "   Pedidos: {$salesMetrics['total_orders']}\n";
        echo "   Ticket médio: R$ " . number_format($salesMetrics['avg_order_value'] / 100, 2) . "\n";

        // Top produtos
        $topProducts = $this->sdk->analytics()->getTopProducts([
            'period' => '30_days',
            'limit' => 5
        ]);

        echo "\n🏆 Top 5 produtos:\n";
        foreach ($topProducts as $index => $product) {
            echo "   " . ($index + 1) . ". {$product['name']} - {$product['sales_count']} vendas\n";
        }

        // Análise de funil
        $funnelAnalysis = $this->sdk->analytics()->getFunnelAnalysis([
            'period' => '30_days'
        ]);

        echo "\n🔍 Análise do funil de conversão:\n";
        echo "   Visitantes: {$funnelAnalysis['visitors']}\n";
        echo "   Carrinho iniciado: {$funnelAnalysis['cart_started']} ({$funnelAnalysis['cart_conversion']}%)\n";
        echo "   Checkout iniciado: {$funnelAnalysis['checkout_started']} ({$funnelAnalysis['checkout_conversion']}%)\n";
        echo "   Compra finalizada: {$funnelAnalysis['purchases']} ({$funnelAnalysis['purchase_conversion']}%)\n";
    }
}

// Executar exemplo
if (php_sapi_name() === 'cli') {
    $example = new CompleteEcommerceExample();
    $example->runCompleteFlow();
    $example->demonstrateAnalytics();
}