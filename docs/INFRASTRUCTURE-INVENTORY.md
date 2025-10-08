# Clubify-Checkout Infrastructure Inventory

## Current Architecture Overview

### Container Services (Production Environment)

#### Core Microservices
1. **cart-service** (Port 3001)
   - Technology: NestJS
   - Dependencies: MongoDB, Redis
   - Dockerfile: Dockerfile.production
   - Purpose: Shopping cart management

2. **payment-service** (Port 3002, 9090)
   - Technology: NestJS
   - Dependencies: MongoDB, Redis, LocalStack
   - Dockerfile: Dockerfile.production
   - Purpose: Payment processing and transactions

3. **order-service** (Port 3003)
   - Technology: NestJS
   - Dependencies: MongoDB, Redis
   - Dockerfile: Dockerfile.production
   - Purpose: Order management and fulfillment

4. **product-service** (Port 3004)
   - Technology: NestJS
   - Dependencies: MongoDB, Redis
   - Dockerfile: Dockerfile.production
   - Purpose: Product catalog and inventory

5. **checkout-service** (Port 3005)
   - Technology: NestJS
   - Dependencies: MongoDB, Redis
   - Dockerfile: Dockerfile.production
   - Purpose: Checkout process coordination

6. **offer-service** (Port 3006)
   - Technology: NestJS
   - Dependencies: MongoDB, Redis
   - Dockerfile: Dockerfile.production
   - Purpose: Promotional offers and discounts

7. **tracking-service** (Port 3008)
   - Technology: NestJS
   - Dependencies: MongoDB, Redis
   - Dockerfile: Dockerfile.production
   - Purpose: Order and shipment tracking

8. **notification-service** (Port 3009)
   - Technology: NestJS
   - Dependencies: MongoDB, Redis
   - Dockerfile: Dockerfile.production
   - Purpose: User notifications and messaging

9. **customer-service** (Port 3010)
   - Technology: NestJS
   - Dependencies: MongoDB, Redis
   - Dockerfile: Dockerfile.production
   - Purpose: Customer data management

10. **user-management-service** (Port 3011)
    - Technology: NestJS
    - Dependencies: MongoDB, Redis
    - Dockerfile: Dockerfile.production
    - Purpose: User authentication and authorization

11. **subscription-service** (Port 3012)
    - Technology: NestJS
    - Dependencies: MongoDB, Redis
    - Dockerfile: Dockerfile.production
    - Purpose: Subscription management

### Infrastructure Services

#### Databases
- **MongoDB** (Port 27017)
  - Version: 4.4.18
  - Platform: linux/amd64
  - Authentication: admin/password
  - Database: clubify-checkout-db
  - Storage: Persistent volume (mongo_data)

- **Redis** (Port 6379)
  - Version: 7.2-alpine
  - Purpose: Caching and session management
  - Storage: Persistent volume (redis_data)

#### Development/Testing Services
- **LocalStack** (Port 4566)
  - AWS services emulation (SQS, SNS, Events)
  - Version: 4.7

#### Frontend & Proxy
- **Frontend** (Port 3000)
  - Technology: Next.js
  - Dockerfile: Dockerfile.production
  - Configuration: frontend/.env.production

- **nginx-proxy** (Ports 80, 443)
  - Technology: OpenResty (Nginx + Lua)
  - SSL/TLS termination
  - Load balancing and routing
  - Health check endpoint: /health

- **documentation-service** (Port 4000)
  - Technology: Docusaurus
  - Development documentation

#### Commented/Disabled Services
- **ai-advisor-service** (Port 3007) - Currently disabled
- **ArangoDB** (Port 8529) - Currently disabled

## Resource Analysis for Fargate Migration

### Estimated Resource Requirements

#### Per Microservice (Standard Pattern):
- **CPU**: 0.25-0.5 vCPU per service
- **Memory**: 512MB-1GB per service
- **Storage**: Ephemeral (no persistent volumes needed per service)

#### Database Requirements:
- **MongoDB**: 1-2 vCPU, 2-4GB RAM (AWS DocumentDB candidate)
- **Redis**: 0.5 vCPU, 1GB RAM (AWS ElastiCache candidate)

#### Total Current Resource Footprint:
- **Services**: 11 active microservices
- **Estimated CPU**: 5.5-8 vCPU total
- **Estimated Memory**: 8-15GB total
- **Public Ports**: 15+ port mappings

## Migration Considerations

### Fargate Task Definition Strategy:
1. **Individual Tasks**: Each microservice as separate Fargate task
2. **Resource Optimization**: Right-size CPU/Memory per service
3. **Health Checks**: Implement proper health check endpoints
4. **Service Discovery**: Use AWS Cloud Map for inter-service communication

### Networking Strategy:
- **Load Balancer**: Replace nginx-proxy with AWS Application Load Balancer
- **Service Mesh**: Consider AWS App Mesh for complex routing
- **VPC Design**: Public/Private subnet strategy

### Data Migration:
- **MongoDB → DocumentDB**: High compatibility, managed service
- **Redis → ElastiCache**: Direct migration path available
- **LocalStack → Native AWS**: Replace with actual AWS services

---
*Inventory completed: Dom 21 Set 2025 19:01*