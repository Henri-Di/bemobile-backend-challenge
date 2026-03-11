# BeMobile Backend Challenge

![PHP](https://img.shields.io/badge/PHP-8.2-blue)
![Laravel](https://img.shields.io/badge/Laravel-10-red)
![Docker](https://img.shields.io/badge/Docker-enabled-blue)
![MySQL](https://img.shields.io/badge/MySQL-8-orange)
![Tests](https://img.shields.io/badge/tests-passing-brightgreen)

API RESTful desenvolvida como solução para o **Teste Prático Backend da BeMobile (Nível 3)**.

Este projeto implementa um sistema completo de **processamento de pagamentos com múltiplos gateways**, incluindo fallback automático, registro de tentativas de pagamento, processamento de reembolsos e controle de permissões.

A aplicação foi construída utilizando:

- PHP 8.2
- Laravel 10
- MySQL
- Docker
- Laravel Sanctum
- PHPUnit

O projeto segue princípios de:

- arquitetura limpa
- separação de responsabilidades
- extensibilidade
- testabilidade
- boas práticas de engenharia

---

# 📚 Sumário

- Visão Geral
- Arquitetura
- Estrutura do Projeto
- Modelagem do Banco
- Fluxo de Pagamento
- Fluxo de Reembolso
- Autenticação e Permissões
- Endpoints
- Testes Automatizados
- Setup do Projeto
- Docker
- Decisões Técnicas
- Melhorias Futuras

---

# Visão Geral

A API fornece um backend completo para gerenciamento de pagamentos.

Funcionalidades implementadas:

- autenticação de usuários
- gerenciamento de usuários
- gerenciamento de clientes
- gerenciamento de produtos
- gerenciamento de gateways
- criação de transações
- fallback automático entre gateways
- registro de tentativas de pagamento
- processamento de reembolsos
- controle de permissões baseado em roles

---

# Arquitetura

A aplicação segue uma arquitetura em camadas para manter o código desacoplado e testável.

```
Client Request
      │
      ▼
Controllers
      │
      ▼
Request Validation
      │
      ▼
Service Layer
      │
      ▼
Gateway Integration
      │
      ▼
Repository Layer
      │
      ▼
Database
```

### Camadas

| Camada | Responsabilidade |
|------|------|
Controllers | Entrada HTTP |
Requests | Validação |
Services | Regras de negócio |
Repositories | Persistência |
DTOs | Transporte de dados |
Enums | Tipagem de estados |
Gateways | Integração externa |

---

# Estrutura do Projeto

Estrutura real baseada no projeto:

```
app
├── Console
├── Contracts
│   ├── GatewayPaymentInterface.php
│   ├── GatewayRepositoryInterface.php
│   └── TransactionRepositoryInterface.php
│
├── DataTransferObjects
│   ├── GatewayChargeResult.php
│   ├── GatewayRefundResult.php
│   └── PaymentChargeData.php
│
├── Enums
│   ├── GatewayCodeEnum.php
│   ├── RefundStatusEnum.php
│   ├── TransactionAttemptStatusEnum.php
│   ├── TransactionStatusEnum.php
│   └── UserRoleEnum.php
│
├── Exceptions
│   ├── GatewayIntegrationException.php
│   └── Handler.php
│
├── Http
│   ├── Controllers
│   │   └── Api
│   │       ├── AuthController.php
│   │       ├── ClientController.php
│   │       ├── GatewayController.php
│   │       ├── ProductController.php
│   │       ├── RefundController.php
│   │       ├── TransactionController.php
│   │       └── UserController.php
│   │
│   ├── Middleware
│   │   ├── RoleMiddleware.php
│   │   └── Authenticate.php
│   │
│   ├── Requests
│   │   ├── ClientIndexRequest.php
│   │   ├── GatewayIndexRequest.php
│   │   ├── ProductIndexRequest.php
│   │   ├── SetGatewayActiveRequest.php
│   │   ├── StoreProductRequest.php
│   │   ├── StoreRefundRequest.php
│   │   ├── StoreTransactionRequest.php
│   │   ├── StoreUserRequest.php
│   │   ├── TransactionIndexRequest.php
│   │   ├── UpdateGatewayPriorityRequest.php
│   │   ├── UpdateProductRequest.php
│   │   ├── UpdateUserRequest.php
│   │   └── UserIndexRequest.php
│   │
│   └── Resources
│       ├── ClientDetailResource.php
│       ├── ClientResource.php
│       ├── GatewayResource.php
│       ├── ProductResource.php
│       ├── RefundResource.php
│       ├── TransactionResource.php
│       └── UserResource.php
│
├── Models
│   ├── Concerns
│   │   └── HandlesBrazilianDateTimes.php
│   │
│   ├── Client.php
│   ├── Gateway.php
│   ├── Product.php
│   ├── Refund.php
│   ├── Transaction.php
│   ├── TransactionAttempt.php
│   ├── TransactionProduct.php
│   └── User.php
│
├── Providers
│   ├── AppServiceProvider.php
│   ├── AuthServiceProvider.php
│   ├── BroadcastServiceProvider.php
│   ├── EventServiceProvider.php
│   └── RouteServiceProvider.php
│
├── Repositories
│   └── Eloquent
│       ├── EloquentGatewayRepository.php
│       └── EloquentTransactionRepository.php
│
└── Services
    ├── Gateways
    │   ├── AbstractGatewayService.php
    │   ├── GatewayOneService.php
    │   └── GatewayTwoService.php
    │
    └── PaymentService.php
```

---

# Modelagem do Banco

Tabelas principais:

```
users
clients
products
gateways
transactions
transaction_products
transaction_attempts
refunds
```

Relacionamentos:

```
Client
 └── Transactions

Transaction
 ├── TransactionProducts
 ├── TransactionAttempts
 └── Refunds

Gateway
 ├── TransactionAttempts
 └── Refunds
```

---

# Fluxo de Pagamento

Fluxo simplificado:

```
Cliente cria transação
        │
        ▼
Validação de produtos
        │
        ▼
Criação da transação (PROCESSING)
        │
        ▼
Gateway 1 tenta pagamento
        │
        ├─ sucesso → status = PAID
        │
        └─ falha
             │
             ▼
        Gateway 2 tenta pagamento
             │
             ├─ sucesso → status = PAID
             │
             └─ falha → status = FAILED
```

Todas as tentativas são registradas em:

```
transaction_attempts
```

---

# Fluxo de Reembolso

Regras aplicadas:

- apenas transações `PAID` podem ser reembolsadas
- reembolso pode ser parcial ou total
- valor não pode exceder valor da transação

Após reembolso:

```
transaction.status = REFUNDED
```

---

# Autenticação e Permissões

Autenticação baseada em **Laravel Sanctum**.

Roles disponíveis:

```
ADMIN
MANAGER
USER
```

Permissões principais:

| Ação | ADMIN | MANAGER | USER |
|----|----|----|----|
Criar usuário | ✔ | ✔ | ✖ |
Criar produto | ✔ | ✔ | ✖ |
Criar cliente | ✔ | ✔ | ✔ |
Criar transação | ✔ | ✔ | ✔ |
Processar refund | ✔ | ✔ | ✖ |

---

# Endpoints

### Auth

```
POST /api/v1/login
POST /api/v1/logout
GET /api/v1/user
```

### Users

```
GET /api/v1/users
POST /api/v1/users
PUT /api/v1/users/{id}
DELETE /api/v1/users/{id}
```

### Products

```
GET /api/v1/products
POST /api/v1/products
PUT /api/v1/products/{id}
DELETE /api/v1/products/{id}
```

### Clients

```
GET /api/v1/clients
POST /api/v1/clients
```

### Gateways

```
GET /api/v1/gateways
PATCH /api/v1/gateways/{id}/priority
PATCH /api/v1/gateways/{id}/active
```

### Transactions

```
POST /api/v1/transactions
GET /api/v1/transactions
GET /api/v1/transactions/{id}
```

### Refund

```
POST /api/v1/transactions/{transaction}/refund
```

---

# Testes Automatizados

Testes cobrem:

```
Auth
AuthorizationRoles
Transactions
PaymentService
```

Executar testes:

```bash
docker exec -it bemobile_app php artisan test
```

---

# Setup do Projeto

### Clonar repositório

```bash
git clone https://github.com/Henri-Di/bemobile-backend-challenge.git
cd bemobile-backend-challenge
```

### Subir containers

```bash
docker compose up -d --build
```

### Configurar ambiente

```bash
cp .env.example .env
```

### Instalar dependências

```bash
docker exec -it bemobile_app composer install
```

### Gerar chave

```bash
docker exec -it bemobile_app php artisan key:generate
```

### Rodar migrations

```bash
docker exec -it bemobile_app php artisan migrate --seed
```

Aplicação disponível em:

```
http://localhost:9000
```

---

# Docker

Containers utilizados:

```
bemobile_app
bemobile_mysql
bemobile_nginx
```

Subir:

```bash
docker compose up -d
```

Parar:

```bash
docker compose down
```

---

# Decisões Técnicas

### Interface de Gateway

Permite adicionar novos gateways sem alterar a lógica principal.

### DTO Layer

Isola dados da camada de negócio.

### Repository Pattern

Abstrai acesso ao banco.

### Registro de Tentativas

Permite auditoria completa de pagamentos.

---

# Melhorias Futuras

- documentação OpenAPI / Swagger
- circuit breaker para gateways
- filas para pagamentos assíncronos
- observabilidade
- métricas e monitoramento
- idempotência de pagamentos

---

# Autor

Henrique Dias

Teste Técnico Backend — BeMobile