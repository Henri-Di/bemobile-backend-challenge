# BeMobile Backend Challenge

API RESTful desenvolvida como solução para o **Teste Prático Backend da BeMobile (Nível 3)**.

O projeto implementa um sistema de **processamento de pagamentos com múltiplos gateways**, incluindo **fallback automático**, gerenciamento de transações, registro de tentativas de pagamento e processamento de reembolsos.

A aplicação foi construída utilizando **Laravel 10**, **PHP 8.2**, **MySQL** e **Docker**, com foco em:

- arquitetura limpa
- separação de responsabilidades
- boas práticas de desenvolvimento
- testes automatizados
- organização do código

---

## 📌 Visão Geral

A API permite:

- autenticação de usuários
- gerenciamento de usuários
- gerenciamento de clientes
- gerenciamento de produtos
- criação de transações de pagamento
- integração com múltiplos gateways de pagamento
- fallback automático entre gateways
- registro de tentativas de pagamento
- processamento de reembolsos
- controle de permissões por role

---

## 🧰 Tecnologias Utilizadas

- PHP 8.2
- Laravel 10
- MySQL
- Docker
- Docker Compose
- Laravel Sanctum
- PHPUnit

---

## 🏗 Arquitetura do Projeto

Estrutura principal da aplicação:

```text
app/
├── Contracts
│   └── GatewayPaymentInterface.php
├── DataTransferObjects
│   ├── GatewayChargeResult.php
│   ├── GatewayRefundResult.php
│   └── PaymentChargeData.php
├── Enums
│   ├── GatewayCodeEnum.php
│   ├── TransactionAttemptStatusEnum.php
│   └── TransactionStatusEnum.php
├── Exceptions
│   └── GatewayIntegrationException.php
├── Http
│   ├── Controllers
│   │   └── Api
│   │       └── TransactionController.php
│   ├── Middleware
│   ├── Requests
│   │   ├── StoreTransactionRequest.php
│   │   └── TransactionIndexRequest.php
│   └── Resources
│       └── TransactionResource.php
├── Models
│   ├── Client.php
│   ├── Gateway.php
│   ├── Product.php
│   ├── Refund.php
│   ├── Transaction.php
│   ├── TransactionAttempt.php
│   └── TransactionProduct.php
├── Providers
├── Repositories
│   └── Eloquent
│       ├── EloquentGatewayRepository.php
│       └── EloquentTransactionRepository.php
└── Services
    ├── Gateways
    │   ├── GatewayOneService.php
    │   └── GatewayTwoService.php
    └── PaymentService.php
```

### Camadas

#### Contracts

Interfaces que definem contratos da aplicação, como:

- `GatewayPaymentInterface`
- `TransactionRepositoryInterface`
- `GatewayRepositoryInterface`

#### DTOs

Objetos responsáveis por transportar dados entre camadas:

- `PaymentChargeData`
- `GatewayChargeResult`
- `GatewayRefundResult`

#### Enums

Representação tipada de estados e códigos.

- `TransactionStatusEnum`
- `GatewayCodeEnum`
- `TransactionAttemptStatusEnum`

#### Services

Responsáveis pela lógica de negócio.

Exemplo:

- `PaymentService`

#### Repositories

Responsáveis pela persistência.

```text
Repositories/
└── Eloquent
    ├── EloquentGatewayRepository.php
    └── EloquentTransactionRepository.php
```

#### Controllers

Recebem requisições HTTP e delegam para serviços.

```text
Http/Controllers/Api
└── TransactionController.php
```

---

## 🗄 Modelagem do Banco

Principais entidades:

- users
- clients
- products
- transactions
- transaction_products
- transaction_attempts
- refunds
- gateways

Relacionamentos principais:

```text
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

## 💳 Fluxo de Pagamento

1. Cliente realiza uma compra
2. Sistema valida produtos e valores
3. A transação é criada com status `PROCESSING`
4. Gateways ativos são consultados por prioridade
5. Cada gateway tenta processar o pagamento
6. Caso um gateway falhe, o próximo é utilizado

Se o pagamento for aprovado:

```text
transaction.status = PAID
```

Se todos os gateways falharem:

```text
transaction.status = FAILED
```

Todas as tentativas são registradas em:

```text
transaction_attempts
```

---

## 💸 Fluxo de Reembolso

Regras aplicadas:

- apenas transações `PAID` podem ser reembolsadas
- refund pode ser total ou parcial
- o valor do refund não pode exceder o valor da transação
- o refund é registrado na tabela `refunds`
- após o reembolso, a transação passa para o status `REFUNDED`

---

## 🔐 Autenticação e Autorização

A API utiliza **Laravel Sanctum**.

Roles disponíveis:

- ADMIN
- MANAGER
- USER

Permissões:

| Ação | ADMIN | MANAGER | USER |
|---|---|---|---|
| Criar usuário | ✔ | ✔ | ✖ |
| Criar produto | ✔ | ✔ | ✖ |
| Criar cliente | ✔ | ✔ | ✔ |
| Criar transação | ✔ | ✔ | ✔ |
| Processar refund | ✔ | ✔ | ✖ |

---

## 📡 Endpoints Principais

### Autenticação

```http
POST /api/v1/login
POST /api/v1/logout
GET  /api/v1/user
```

### Usuários

```http
GET    /api/v1/users
GET    /api/v1/users/{user}
POST   /api/v1/users
PUT    /api/v1/users/{user}
PATCH  /api/v1/users/{user}
DELETE /api/v1/users/{user}
```

### Produtos

```http
GET    /api/v1/products
GET    /api/v1/products/{product}
POST   /api/v1/products
PUT    /api/v1/products/{product}
PATCH  /api/v1/products/{product}
DELETE /api/v1/products/{product}
```

### Clientes

```http
GET /api/v1/clients
GET /api/v1/clients/{client}
POST /api/v1/clients
PUT /api/v1/clients/{client}
PATCH /api/v1/clients/{client}
DELETE /api/v1/clients/{client}
```

### Gateways

```http
GET   /api/v1/gateways
GET   /api/v1/gateways/{gateway}
PATCH /api/v1/gateways/{gateway}/priority
PATCH /api/v1/gateways/{gateway}/active
```

### Transações

```http
POST /api/v1/transactions
GET  /api/v1/transactions
GET  /api/v1/transactions/{transaction}
```

### Refund

```http
POST /api/v1/transactions/{transaction}/refund
```

---

## 🧪 Testes Automatizados

Os testes cobrem:

- criação de transações
- fallback entre gateways
- validações de compra
- fluxo de refund
- autenticação e permissões
- tratamento de erros de gateway
- serialização de transações

Executar todos os testes:

```bash
docker exec -it bemobile_app php artisan test
```

Executar testes específicos:

```bash
docker exec -it bemobile_app php artisan test tests/Feature/Transactions/ShowTransactionTest.php
docker exec -it bemobile_app php artisan test tests/Feature/Transactions/RefundTransactionTest.php
```

---

## 🐳 Executando o Projeto

### 1. Clonar o repositório

```bash
git clone https://github.com/Henri-Di/bemobile-backend-challenge.git
cd bemobile-backend-challenge
```

### 2. Subir os containers

```bash
docker compose up -d --build
```

### 3. Configurar o ambiente

Linux/macOS:

```bash
cp .env.example .env
```

Windows:

```bash
copy .env.example .env
```

### 4. Instalar dependências

```bash
docker exec -it bemobile_app composer install
```

### 5. Gerar a chave da aplicação

```bash
docker exec -it bemobile_app php artisan key:generate
```

### 6. Rodar migrations e seeders

```bash
docker exec -it bemobile_app php artisan migrate --seed
```

### 7. Executar a aplicação

A aplicação ficará disponível em:

```text
http://localhost:9000
```

---

## 📂 Docker

Containers utilizados:

- `bemobile_app`
- `bemobile_mysql`
- `bemobile_nginx`

Subir containers:

```bash
docker compose up -d
```

Parar containers:

```bash
docker compose down
```

Reconstruir containers:

```bash
docker compose up -d --build
```

---

## 📎 Decisões Técnicas

### Fallback entre gateways

Permite maior confiabilidade no processamento de pagamentos.

Se um gateway falhar, o próximo gateway ativo é utilizado automaticamente.

### DTOs

Separação clara entre domínio e transporte de dados.

### Repository Pattern

Abstração da camada de persistência.

### Enums

Evita uso de strings soltas no código.

### Registro de tentativas

Todas as tentativas de pagamento são armazenadas para auditoria.

### Resource Layer

A serialização da resposta foi centralizada em `TransactionResource`, garantindo consistência da API e controle do payload retornado ao cliente.

### Logs seguros

O projeto aplica sanitização e mascaramento de dados sensíveis para evitar exposição de informações de cartão e payloads críticos em logs.

---

## 📈 Possíveis Melhorias

- documentação OpenAPI / Swagger
- circuit breaker para gateways
- idempotência em pagamentos
- logs estruturados
- métricas e monitoramento
- filas para processamento assíncrono
- observabilidade com tracing
- rate limiting mais granular

---

## 👤 Autor

**Henrique Dias**

Desafio técnico backend — BeMobile

---

## 📄 Licença

Projeto desenvolvido exclusivamente para avaliação técnica.