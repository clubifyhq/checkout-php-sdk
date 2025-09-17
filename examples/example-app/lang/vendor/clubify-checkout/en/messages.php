<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | SDK Messages - English
    |--------------------------------------------------------------------------
    |
    | The following language lines are used by the Clubify Checkout SDK
    | for various messages that we need to display to the user.
    |
    */

    'sdk' => [
        'name' => 'Clubify Checkout SDK',
        'version' => 'Version :version',
        'initialized' => 'SDK initialized successfully',
        'not_initialized' => 'SDK not initialized',
        'initialization_failed' => 'SDK initialization failed: :error',
    ],

    'auth' => [
        'success' => 'Authentication successful',
        'failed' => 'Authentication failed',
        'invalid_credentials' => 'Invalid credentials',
        'token_expired' => 'Authentication token expired',
        'token_refreshed' => 'Authentication token refreshed',
        'insufficient_permissions' => 'Insufficient permissions for this operation',
        'tenant_invalid' => 'Invalid tenant ID',
    ],

    'validation' => [
        'required' => 'The :attribute field is required',
        'string' => 'The :attribute field must be a string',
        'integer' => 'The :attribute field must be an integer',
        'email' => 'The :attribute field must be a valid email address',
        'uuid' => 'The :attribute field must be a valid UUID',
        'date' => 'The :attribute field must be a valid date',
        'cpf' => 'The :attribute field must be a valid CPF',
        'cnpj' => 'The :attribute field must be a valid CNPJ',
        'phone' => 'The :attribute field must be a valid phone number',
        'credit_card' => 'The :attribute field must be a valid credit card number',
        'currency' => 'The :attribute field must be a valid currency amount',
        'array' => 'The :attribute field must be an array',
        'boolean' => 'The :attribute field must be true or false',
        'url' => 'The :attribute field must be a valid URL',
        'json' => 'The :attribute field must be valid JSON',
        'min_length' => 'The :attribute field must be at least :min characters',
        'max_length' => 'The :attribute field must not exceed :max characters',
        'numeric' => 'The :attribute field must be numeric',
        'positive' => 'The :attribute field must be positive',
    ],

    'modules' => [
        'organization' => [
            'name' => 'Organization',
            'setup_success' => 'Organization setup completed successfully',
            'setup_failed' => 'Organization setup failed: :error',
            'not_found' => 'Organization not found',
            'status_healthy' => 'Organization status is healthy',
            'status_unhealthy' => 'Organization status is unhealthy',
        ],

        'products' => [
            'name' => 'Products',
            'created' => 'Product created successfully',
            'updated' => 'Product updated successfully',
            'deleted' => 'Product deleted successfully',
            'not_found' => 'Product not found',
            'invalid_sku' => 'Invalid product SKU',
            'out_of_stock' => 'Product is out of stock',
        ],

        'checkout' => [
            'name' => 'Checkout',
            'session_created' => 'Checkout session created successfully',
            'session_expired' => 'Checkout session has expired',
            'session_not_found' => 'Checkout session not found',
            'cart_empty' => 'Shopping cart is empty',
            'cart_updated' => 'Shopping cart updated successfully',
        ],

        'payments' => [
            'name' => 'Payments',
            'processing' => 'Processing payment...',
            'success' => 'Payment processed successfully',
            'failed' => 'Payment processing failed: :error',
            'declined' => 'Payment was declined',
            'gateway_error' => 'Payment gateway error: :error',
            'invalid_card' => 'Invalid credit card information',
            'insufficient_funds' => 'Insufficient funds',
        ],

        'customers' => [
            'name' => 'Customers',
            'created' => 'Customer created successfully',
            'updated' => 'Customer updated successfully',
            'not_found' => 'Customer not found',
            'duplicate' => 'Customer already exists',
            'merged' => 'Customer data merged successfully',
        ],

        'webhooks' => [
            'name' => 'Webhooks',
            'configured' => 'Webhook configured successfully',
            'delivered' => 'Webhook delivered successfully',
            'failed' => 'Webhook delivery failed: :error',
            'invalid_signature' => 'Invalid webhook signature',
            'expired' => 'Webhook has expired',
        ],
    ],

    'operations' => [
        'create' => 'Create',
        'read' => 'Read',
        'update' => 'Update',
        'delete' => 'Delete',
        'list' => 'List',
        'search' => 'Search',
        'sync' => 'Synchronize',
        'process' => 'Process',
        'cancel' => 'Cancel',
        'refund' => 'Refund',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
        'healthy' => 'Healthy',
        'unhealthy' => 'Unhealthy',
    ],

    'errors' => [
        'network' => 'Network error occurred',
        'timeout' => 'Request timeout',
        'rate_limit' => 'Rate limit exceeded',
        'unauthorized' => 'Unauthorized access',
        'forbidden' => 'Access forbidden',
        'not_found' => 'Resource not found',
        'conflict' => 'Resource conflict',
        'validation' => 'Validation error',
        'server_error' => 'Internal server error',
        'gateway_error' => 'Gateway error',
        'configuration' => 'Configuration error',
        'unknown' => 'Unknown error occurred',
    ],

    'commands' => [
        'install' => [
            'description' => 'Install and configure Clubify Checkout SDK for Laravel',
            'success' => 'SDK installed successfully',
            'failed' => 'SDK installation failed',
        ],

        'publish' => [
            'description' => 'Publish specific Clubify Checkout SDK assets',
            'success' => 'Assets published successfully',
            'failed' => 'Asset publishing failed',
        ],

        'sync' => [
            'description' => 'Synchronize data and test connectivity with Clubify Checkout API',
            'success' => 'Synchronization completed successfully',
            'failed' => 'Synchronization failed',
            'testing_connectivity' => 'Testing connectivity...',
            'syncing_data' => 'Synchronizing data...',
        ],
    ],

    'jobs' => [
        'payment' => [
            'processing' => 'Processing payment job...',
            'success' => 'Payment job completed successfully',
            'failed' => 'Payment job failed',
            'retrying' => 'Retrying payment job...',
        ],

        'webhook' => [
            'sending' => 'Sending webhook...',
            'success' => 'Webhook sent successfully',
            'failed' => 'Webhook sending failed',
            'retrying' => 'Retrying webhook...',
        ],

        'customer' => [
            'syncing' => 'Synchronizing customer...',
            'success' => 'Customer synchronization completed',
            'failed' => 'Customer synchronization failed',
            'retrying' => 'Retrying customer synchronization...',
        ],
    ],

    'middleware' => [
        'auth' => [
            'unauthorized' => 'SDK authentication required',
            'invalid_token' => 'Invalid or expired authentication token',
            'insufficient_permissions' => 'Insufficient permissions',
        ],

        'webhook' => [
            'invalid_signature' => 'Invalid webhook signature',
            'expired' => 'Webhook timestamp expired',
            'invalid_payload' => 'Invalid webhook payload',
        ],
    ],
];