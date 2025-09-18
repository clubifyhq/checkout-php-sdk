<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests\Unit\Orders;

use Clubify\Checkout\Tests\TestCase;
use Clubify\Checkout\Modules\Orders\DTOs\OrderData;
use Clubify\Checkout\Modules\Orders\DTOs\OrderItemData;
use Clubify\Checkout\ValueObjects\Money;

/**
 * Testes unitários para OrderData DTO
 *
 * Testa todas as funcionalidades do DTO de pedidos:
 * - Criação e validação
 * - Transformação de dados
 * - Serialização/deserialização
 * - Métodos utilitários
 * - Validação de regras de negócio
 * - Tratamento de erros
 *
 * @covers \Clubify\Checkout\Modules\Orders\DTOs\OrderData
 * @group unit
 * @group orders
 * @group dtos
 */
class OrderDataTest extends TestCase
{
    /** @test */
    public function it_can_be_created_with_valid_data(): void
    {
        // Arrange
        $items = [
            new OrderItemData(
                id: 'item_1',
                name: 'Test Product 1',
                price: new Money(2999, 'BRL'),
                quantity: 2
            ),
            new OrderItemData(
                id: 'item_2',
                name: 'Test Product 2',
                price: new Money(1999, 'BRL'),
                quantity: 1
            )
        ];

        $total = new Money(7997, 'BRL'); // (2999 * 2) + 1999

        // Act
        $orderData = new OrderData(
            id: 'order_123',
            customerId: 'cust_456',
            status: 'pending',
            items: $items,
            total: $total,
            currency: 'BRL',
            paymentMethod: 'credit_card'
        );

        // Assert
        $this->assertInstanceOf(OrderData::class, $orderData);
        $this->assertEquals('order_123', $orderData->id);
        $this->assertEquals('cust_456', $orderData->customerId);
        $this->assertEquals('pending', $orderData->status);
        $this->assertCount(2, $orderData->items);
        $this->assertEquals(7997, $orderData->total->getAmount());
        $this->assertEquals('BRL', $orderData->currency);
        $this->assertEquals('credit_card', $orderData->paymentMethod);
    }

    /** @test */
    public function it_validates_required_fields(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OrderData(
            id: '', // Campo obrigatório vazio
            customerId: 'cust_456',
            status: 'pending',
            items: [],
            total: new Money(1000, 'BRL'),
            currency: 'BRL',
            paymentMethod: 'credit_card'
        );
    }

    /** @test */
    public function it_validates_status_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OrderData(
            id: 'order_123',
            customerId: 'cust_456',
            status: 'invalid_status', // Status inválido
            items: [],
            total: new Money(1000, 'BRL'),
            currency: 'BRL',
            paymentMethod: 'credit_card'
        );
    }

    /** @test */
    public function it_validates_minimum_items(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OrderData(
            id: 'order_123',
            customerId: 'cust_456',
            status: 'pending',
            items: [], // Deve ter pelo menos 1 item
            total: new Money(1000, 'BRL'),
            currency: 'BRL',
            paymentMethod: 'credit_card'
        );
    }

    /** @test */
    public function it_validates_total_against_items(): void
    {
        // Arrange
        $items = [
            new OrderItemData(
                id: 'item_1',
                name: 'Test Product',
                price: new Money(2999, 'BRL'),
                quantity: 2
            )
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Total amount does not match items total');

        // Act - Total incorreto (deveria ser 5998)
        new OrderData(
            id: 'order_123',
            customerId: 'cust_456',
            status: 'pending',
            items: $items,
            total: new Money(1000, 'BRL'), // Total incorreto
            currency: 'BRL',
            paymentMethod: 'credit_card'
        );
    }

    /** @test */
    public function it_can_be_created_from_array(): void
    {
        // Arrange
        $data = [
            'id' => 'order_123',
            'customer_id' => 'cust_456',
            'status' => 'pending',
            'items' => [
                [
                    'id' => 'item_1',
                    'name' => 'Test Product',
                    'price' => 2999,
                    'quantity' => 1
                ]
            ],
            'total' => 2999,
            'currency' => 'BRL',
            'payment_method' => 'credit_card',
            'coupon_code' => 'SAVE10',
            'shipping_address' => [
                'street' => 'Rua Test, 123',
                'city' => 'São Paulo',
                'state' => 'SP',
                'postal_code' => '01234-567',
                'country' => 'BR'
            ],
            'metadata' => [
                'source' => 'web',
                'campaign' => 'summer_sale'
            ],
            'created_at' => '2024-01-15 10:30:00',
            'updated_at' => '2024-01-15 10:30:00'
        ];

        // Act
        $orderData = OrderData::fromArray($data);

        // Assert
        $this->assertEquals('order_123', $orderData->id);
        $this->assertEquals('cust_456', $orderData->customerId);
        $this->assertEquals('pending', $orderData->status);
        $this->assertCount(1, $orderData->items);
        $this->assertEquals(2999, $orderData->total->getAmount());
        $this->assertEquals('BRL', $orderData->currency);
        $this->assertEquals('credit_card', $orderData->paymentMethod);
        $this->assertEquals('SAVE10', $orderData->couponCode);
        $this->assertIsArray($orderData->shippingAddress);
        $this->assertIsArray($orderData->metadata);
        $this->assertInstanceOf(\DateTime::class, $orderData->createdAt);
        $this->assertInstanceOf(\DateTime::class, $orderData->updatedAt);
    }

    /** @test */
    public function it_converts_to_array_correctly(): void
    {
        // Arrange
        $items = [
            new OrderItemData(
                id: 'item_1',
                name: 'Test Product',
                price: new Money(2999, 'BRL'),
                quantity: 1
            )
        ];

        $orderData = new OrderData(
            id: 'order_123',
            customerId: 'cust_456',
            status: 'pending',
            items: $items,
            total: new Money(2999, 'BRL'),
            currency: 'BRL',
            paymentMethod: 'credit_card',
            couponCode: 'SAVE10',
            shippingAddress: [
                'street' => 'Rua Test, 123',
                'city' => 'São Paulo'
            ],
            metadata: ['source' => 'web'],
            createdAt: new \DateTime('2024-01-15 10:30:00'),
            updatedAt: new \DateTime('2024-01-15 10:30:00')
        );

        // Act
        $array = $orderData->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertEquals('order_123', $array['id']);
        $this->assertEquals('cust_456', $array['customer_id']);
        $this->assertEquals('pending', $array['status']);
        $this->assertIsArray($array['items']);
        $this->assertCount(1, $array['items']);
        $this->assertEquals(2999, $array['total']);
        $this->assertEquals('BRL', $array['currency']);
        $this->assertEquals('credit_card', $array['payment_method']);
        $this->assertEquals('SAVE10', $array['coupon_code']);
        $this->assertIsArray($array['shipping_address']);
        $this->assertIsArray($array['metadata']);
        $this->assertEquals('2024-01-15 10:30:00', $array['created_at']);
        $this->assertEquals('2024-01-15 10:30:00', $array['updated_at']);
    }

    /** @test */
    public function it_converts_to_safe_array_without_sensitive_data(): void
    {
        // Arrange
        $items = [
            new OrderItemData(
                id: 'item_1',
                name: 'Test Product',
                price: new Money(2999, 'BRL'),
                quantity: 1
            )
        ];

        $orderData = new OrderData(
            id: 'order_123',
            customerId: 'cust_456',
            status: 'pending',
            items: $items,
            total: new Money(2999, 'BRL'),
            currency: 'BRL',
            paymentMethod: 'credit_card',
            paymentDetails: [
                'card_number' => '**** **** **** 1234',
                'card_holder' => 'John Doe',
                'cvv' => '123'
            ]
        );

        // Act
        $safeArray = $orderData->toSafeArray();

        // Assert
        $this->assertIsArray($safeArray);
        $this->assertEquals('order_123', $safeArray['id']);
        $this->assertArrayNotHasKey('payment_details', $safeArray); // Dados sensíveis removidos
        $this->assertArrayHasKey('payment_method', $safeArray); // Dados seguros mantidos
    }

    /** @test */
    public function it_calculates_total_correctly(): void
    {
        // Arrange
        $items = [
            new OrderItemData(
                id: 'item_1',
                name: 'Product 1',
                price: new Money(1500, 'BRL'),
                quantity: 2
            ),
            new OrderItemData(
                id: 'item_2',
                name: 'Product 2',
                price: new Money(2500, 'BRL'),
                quantity: 1
            )
        ];

        $orderData = new OrderData(
            id: 'order_123',
            customerId: 'cust_456',
            status: 'pending',
            items: $items,
            total: new Money(5500, 'BRL'), // (1500 * 2) + 2500
            currency: 'BRL',
            paymentMethod: 'credit_card'
        );

        // Act
        $calculatedTotal = $orderData->calculateItemsTotal();

        // Assert
        $this->assertEquals(5500, $calculatedTotal->getAmount());
        $this->assertEquals('BRL', $calculatedTotal->getCurrency());
    }

    /** @test */
    public function it_checks_status_correctly(): void
    {
        // Arrange
        $items = [
            new OrderItemData(
                id: 'item_1',
                name: 'Test Product',
                price: new Money(2999, 'BRL'),
                quantity: 1
            )
        ];

        $pendingOrder = new OrderData(
            id: 'order_123',
            customerId: 'cust_456',
            status: 'pending',
            items: $items,
            total: new Money(2999, 'BRL'),
            currency: 'BRL',
            paymentMethod: 'credit_card'
        );

        $completedOrder = new OrderData(
            id: 'order_456',
            customerId: 'cust_456',
            status: 'completed',
            items: $items,
            total: new Money(2999, 'BRL'),
            currency: 'BRL',
            paymentMethod: 'credit_card'
        );

        $cancelledOrder = new OrderData(
            id: 'order_789',
            customerId: 'cust_456',
            status: 'cancelled',
            items: $items,
            total: new Money(2999, 'BRL'),
            currency: 'BRL',
            paymentMethod: 'credit_card'
        );

        // Assert
        $this->assertTrue($pendingOrder->isPending());
        $this->assertFalse($pendingOrder->isCompleted());
        $this->assertFalse($pendingOrder->isCancelled());
        $this->assertTrue($pendingOrder->canBeModified());

        $this->assertFalse($completedOrder->isPending());
        $this->assertTrue($completedOrder->isCompleted());
        $this->assertFalse($completedOrder->isCancelled());
        $this->assertFalse($completedOrder->canBeModified());

        $this->assertFalse($cancelledOrder->isPending());
        $this->assertFalse($cancelledOrder->isCompleted());
        $this->assertTrue($cancelledOrder->isCancelled());
        $this->assertFalse($cancelledOrder->canBeModified());
    }

    /** @test */
    public function it_validates_business_rules(): void
    {
        // Arrange
        $items = [
            new OrderItemData(
                id: 'item_1',
                name: 'Test Product',
                price: new Money(2999, 'BRL'),
                quantity: 1
            )
        ];

        $orderData = new OrderData(
            id: 'order_123',
            customerId: 'cust_456',
            status: 'pending',
            items: $items,
            total: new Money(2999, 'BRL'),
            currency: 'BRL',
            paymentMethod: 'credit_card'
        );

        // Act & Assert
        $this->assertTrue($orderData->hasValidTotal());
        $this->assertTrue($orderData->hasItems());
        $this->assertFalse($orderData->isEmpty());
        $this->assertTrue($orderData->isValidForProcessing());
    }

    /** @test */
    public function it_handles_different_currencies(): void
    {
        // Arrange
        $items = [
            new OrderItemData(
                id: 'item_1',
                name: 'Test Product',
                price: new Money(1999, 'USD'),
                quantity: 1
            )
        ];

        $orderData = new OrderData(
            id: 'order_123',
            customerId: 'cust_456',
            status: 'pending',
            items: $items,
            total: new Money(1999, 'USD'),
            currency: 'USD',
            paymentMethod: 'credit_card'
        );

        // Act & Assert
        $this->assertEquals('USD', $orderData->currency);
        $this->assertEquals('USD', $orderData->total->getCurrency());
        $this->assertEquals(1999, $orderData->total->getAmount());
    }

    /** @test */
    public function it_handles_discount_calculations(): void
    {
        // Arrange
        $items = [
            new OrderItemData(
                id: 'item_1',
                name: 'Test Product',
                price: new Money(10000, 'BRL'),
                quantity: 1
            )
        ];

        $orderData = new OrderData(
            id: 'order_123',
            customerId: 'cust_456',
            status: 'pending',
            items: $items,
            total: new Money(9000, 'BRL'), // Com desconto
            currency: 'BRL',
            paymentMethod: 'credit_card',
            couponCode: 'SAVE10',
            discountAmount: new Money(1000, 'BRL')
        );

        // Act
        $discountPercentage = $orderData->getDiscountPercentage();
        $hasDiscount = $orderData->hasDiscount();

        // Assert
        $this->assertTrue($hasDiscount);
        $this->assertEquals(10.0, $discountPercentage); // 10% de desconto
        $this->assertEquals(1000, $orderData->discountAmount->getAmount());
    }

    /** @test */
    public function it_formats_currency_display(): void
    {
        // Arrange
        $items = [
            new OrderItemData(
                id: 'item_1',
                name: 'Test Product',
                price: new Money(2999, 'BRL'),
                quantity: 1
            )
        ];

        $orderData = new OrderData(
            id: 'order_123',
            customerId: 'cust_456',
            status: 'pending',
            items: $items,
            total: new Money(2999, 'BRL'),
            currency: 'BRL',
            paymentMethod: 'credit_card'
        );

        // Act
        $formattedTotal = $orderData->getFormattedTotal();

        // Assert
        $this->assertEquals('R$ 29,99', $formattedTotal);
    }

    /** @test */
    public function it_handles_shipping_calculations(): void
    {
        // Arrange
        $items = [
            new OrderItemData(
                id: 'item_1',
                name: 'Test Product',
                price: new Money(5000, 'BRL'),
                quantity: 1
            )
        ];

        $orderData = new OrderData(
            id: 'order_123',
            customerId: 'cust_456',
            status: 'pending',
            items: $items,
            total: new Money(5500, 'BRL'), // Produto + frete
            currency: 'BRL',
            paymentMethod: 'credit_card',
            shippingAmount: new Money(500, 'BRL')
        );

        // Act & Assert
        $this->assertTrue($orderData->hasShipping());
        $this->assertEquals(500, $orderData->shippingAmount->getAmount());
        $this->assertEquals(5000, $orderData->getSubtotal()->getAmount());
    }
}
