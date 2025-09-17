<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Mensagens do SDK - Português (Brasil)
    |--------------------------------------------------------------------------
    |
    | As seguintes linhas de idioma são usadas pelo SDK Clubify Checkout
    | para várias mensagens que precisamos exibir ao usuário.
    |
    */

    'sdk' => [
        'name' => 'SDK Clubify Checkout',
        'version' => 'Versão :version',
        'initialized' => 'SDK inicializado com sucesso',
        'not_initialized' => 'SDK não inicializado',
        'initialization_failed' => 'Falha na inicialização do SDK: :error',
    ],

    'auth' => [
        'success' => 'Autenticação realizada com sucesso',
        'failed' => 'Falha na autenticação',
        'invalid_credentials' => 'Credenciais inválidas',
        'token_expired' => 'Token de autenticação expirado',
        'token_refreshed' => 'Token de autenticação renovado',
        'insufficient_permissions' => 'Permissões insuficientes para esta operação',
        'tenant_invalid' => 'ID do tenant inválido',
    ],

    'validation' => [
        'required' => 'O campo :attribute é obrigatório',
        'string' => 'O campo :attribute deve ser uma string',
        'integer' => 'O campo :attribute deve ser um número inteiro',
        'email' => 'O campo :attribute deve ser um endereço de email válido',
        'uuid' => 'O campo :attribute deve ser um UUID válido',
        'date' => 'O campo :attribute deve ser uma data válida',
        'cpf' => 'O campo :attribute deve ser um CPF válido',
        'cnpj' => 'O campo :attribute deve ser um CNPJ válido',
        'phone' => 'O campo :attribute deve ser um número de telefone válido',
        'credit_card' => 'O campo :attribute deve ser um número de cartão de crédito válido',
        'currency' => 'O campo :attribute deve ser um valor monetário válido',
        'array' => 'O campo :attribute deve ser um array',
        'boolean' => 'O campo :attribute deve ser verdadeiro ou falso',
        'url' => 'O campo :attribute deve ser uma URL válida',
        'json' => 'O campo :attribute deve ser um JSON válido',
        'min_length' => 'O campo :attribute deve ter pelo menos :min caracteres',
        'max_length' => 'O campo :attribute não deve exceder :max caracteres',
        'numeric' => 'O campo :attribute deve ser numérico',
        'positive' => 'O campo :attribute deve ser positivo',
    ],

    'modules' => [
        'organization' => [
            'name' => 'Organização',
            'setup_success' => 'Configuração da organização concluída com sucesso',
            'setup_failed' => 'Falha na configuração da organização: :error',
            'not_found' => 'Organização não encontrada',
            'status_healthy' => 'Status da organização está saudável',
            'status_unhealthy' => 'Status da organização não está saudável',
        ],

        'products' => [
            'name' => 'Produtos',
            'created' => 'Produto criado com sucesso',
            'updated' => 'Produto atualizado com sucesso',
            'deleted' => 'Produto excluído com sucesso',
            'not_found' => 'Produto não encontrado',
            'invalid_sku' => 'SKU do produto inválido',
            'out_of_stock' => 'Produto fora de estoque',
        ],

        'checkout' => [
            'name' => 'Checkout',
            'session_created' => 'Sessão de checkout criada com sucesso',
            'session_expired' => 'Sessão de checkout expirou',
            'session_not_found' => 'Sessão de checkout não encontrada',
            'cart_empty' => 'Carrinho de compras está vazio',
            'cart_updated' => 'Carrinho de compras atualizado com sucesso',
        ],

        'payments' => [
            'name' => 'Pagamentos',
            'processing' => 'Processando pagamento...',
            'success' => 'Pagamento processado com sucesso',
            'failed' => 'Falha no processamento do pagamento: :error',
            'declined' => 'Pagamento foi recusado',
            'gateway_error' => 'Erro no gateway de pagamento: :error',
            'invalid_card' => 'Informações do cartão de crédito inválidas',
            'insufficient_funds' => 'Fundos insuficientes',
        ],

        'customers' => [
            'name' => 'Clientes',
            'created' => 'Cliente criado com sucesso',
            'updated' => 'Cliente atualizado com sucesso',
            'not_found' => 'Cliente não encontrado',
            'duplicate' => 'Cliente já existe',
            'merged' => 'Dados do cliente mesclados com sucesso',
        ],

        'webhooks' => [
            'name' => 'Webhooks',
            'configured' => 'Webhook configurado com sucesso',
            'delivered' => 'Webhook entregue com sucesso',
            'failed' => 'Falha na entrega do webhook: :error',
            'invalid_signature' => 'Assinatura do webhook inválida',
            'expired' => 'Webhook expirou',
        ],
    ],

    'operations' => [
        'create' => 'Criar',
        'read' => 'Ler',
        'update' => 'Atualizar',
        'delete' => 'Excluir',
        'list' => 'Listar',
        'search' => 'Buscar',
        'sync' => 'Sincronizar',
        'process' => 'Processar',
        'cancel' => 'Cancelar',
        'refund' => 'Estornar',
    ],

    'status' => [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'pending' => 'Pendente',
        'processing' => 'Processando',
        'completed' => 'Concluído',
        'failed' => 'Falhou',
        'cancelled' => 'Cancelado',
        'expired' => 'Expirado',
        'healthy' => 'Saudável',
        'unhealthy' => 'Não saudável',
    ],

    'errors' => [
        'network' => 'Erro de rede ocorreu',
        'timeout' => 'Timeout da requisição',
        'rate_limit' => 'Limite de taxa excedido',
        'unauthorized' => 'Acesso não autorizado',
        'forbidden' => 'Acesso proibido',
        'not_found' => 'Recurso não encontrado',
        'conflict' => 'Conflito de recurso',
        'validation' => 'Erro de validação',
        'server_error' => 'Erro interno do servidor',
        'gateway_error' => 'Erro do gateway',
        'configuration' => 'Erro de configuração',
        'unknown' => 'Erro desconhecido ocorreu',
    ],

    'commands' => [
        'install' => [
            'description' => 'Instalar e configurar o SDK Clubify Checkout para Laravel',
            'success' => 'SDK instalado com sucesso',
            'failed' => 'Falha na instalação do SDK',
        ],

        'publish' => [
            'description' => 'Publicar assets específicos do SDK Clubify Checkout',
            'success' => 'Assets publicados com sucesso',
            'failed' => 'Falha na publicação dos assets',
        ],

        'sync' => [
            'description' => 'Sincronizar dados e testar conectividade com a API Clubify Checkout',
            'success' => 'Sincronização concluída com sucesso',
            'failed' => 'Falha na sincronização',
            'testing_connectivity' => 'Testando conectividade...',
            'syncing_data' => 'Sincronizando dados...',
        ],
    ],

    'jobs' => [
        'payment' => [
            'processing' => 'Processando job de pagamento...',
            'success' => 'Job de pagamento concluído com sucesso',
            'failed' => 'Job de pagamento falhou',
            'retrying' => 'Tentando novamente job de pagamento...',
        ],

        'webhook' => [
            'sending' => 'Enviando webhook...',
            'success' => 'Webhook enviado com sucesso',
            'failed' => 'Falha no envio do webhook',
            'retrying' => 'Tentando enviar webhook novamente...',
        ],

        'customer' => [
            'syncing' => 'Sincronizando cliente...',
            'success' => 'Sincronização do cliente concluída',
            'failed' => 'Falha na sincronização do cliente',
            'retrying' => 'Tentando sincronizar cliente novamente...',
        ],
    ],

    'middleware' => [
        'auth' => [
            'unauthorized' => 'Autenticação do SDK necessária',
            'invalid_token' => 'Token de autenticação inválido ou expirado',
            'insufficient_permissions' => 'Permissões insuficientes',
        ],

        'webhook' => [
            'invalid_signature' => 'Assinatura do webhook inválida',
            'expired' => 'Timestamp do webhook expirado',
            'invalid_payload' => 'Payload do webhook inválido',
        ],
    ],
];