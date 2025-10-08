# SAM Template Design for Clubify-Checkout Infrastructure

## Architecture Overview

### Network Topology:
```
Internet Gateway
    ↓
Application Load Balancer (Public Subnets)
    ↓
Fargate Services (Private Subnets)
    ↓
DocumentDB + ElastiCache (Private DB Subnets)
```

## Complete SAM Template Structure

### File: `template.yaml`

```yaml
AWSTemplateFormatVersion: '2010-09-09'
Transform: AWS::Serverless-2016-10-31
Description: 'Clubify-Checkout Fargate Infrastructure'

Parameters:
  Environment:
    Type: String
    Default: production
    AllowedValues: [development, staging, production]
    Description: Environment name

  VpcCidr:
    Type: String
    Default: '10.0.0.0/16'
    Description: VPC CIDR block

  DatabasePassword:
    Type: String
    NoEcho: true
    MinLength: 8
    Description: DocumentDB master password

  ContainerImagePrefix:
    Type: String
    Default: '123456789012.dkr.ecr.us-east-1.amazonaws.com/clubify-checkout'
    Description: ECR repository prefix

Resources:
  # ===== NETWORKING =====
  VPC:
    Type: AWS::EC2::VPC
    Properties:
      CidrBlock: !Ref VpcCidr
      EnableDnsHostnames: true
      EnableDnsSupport: true
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-vpc'

  # Internet Gateway
  InternetGateway:
    Type: AWS::EC2::InternetGateway
    Properties:
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-igw'

  InternetGatewayAttachment:
    Type: AWS::EC2::VPCGatewayAttachment
    Properties:
      InternetGatewayId: !Ref InternetGateway
      VpcId: !Ref VPC

  # Public Subnets (for ALB)
  PublicSubnet1:
    Type: AWS::EC2::Subnet
    Properties:
      VpcId: !Ref VPC
      AvailabilityZone: !Select [0, !GetAZs '']
      CidrBlock: !Sub '10.0.1.0/24'
      MapPublicIpOnLaunch: true
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-public-1'

  PublicSubnet2:
    Type: AWS::EC2::Subnet
    Properties:
      VpcId: !Ref VPC
      AvailabilityZone: !Select [1, !GetAZs '']
      CidrBlock: !Sub '10.0.2.0/24'
      MapPublicIpOnLaunch: true
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-public-2'

  # Private Subnets (for Fargate services)
  PrivateSubnet1:
    Type: AWS::EC2::Subnet
    Properties:
      VpcId: !Ref VPC
      AvailabilityZone: !Select [0, !GetAZs '']
      CidrBlock: !Sub '10.0.11.0/24'
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-private-1'

  PrivateSubnet2:
    Type: AWS::EC2::Subnet
    Properties:
      VpcId: !Ref VPC
      AvailabilityZone: !Select [1, !GetAZs '']
      CidrBlock: !Sub '10.0.12.0/24'
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-private-2'

  # Database Subnets
  DatabaseSubnet1:
    Type: AWS::EC2::Subnet
    Properties:
      VpcId: !Ref VPC
      AvailabilityZone: !Select [0, !GetAZs '']
      CidrBlock: !Sub '10.0.21.0/24'
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-db-1'

  DatabaseSubnet2:
    Type: AWS::EC2::Subnet
    Properties:
      VpcId: !Ref VPC
      AvailabilityZone: !Select [1, !GetAZs '']
      CidrBlock: !Sub '10.0.22.0/24'
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-db-2'

  # NAT Gateway for private subnet internet access
  NatGateway1EIP:
    Type: AWS::EC2::EIP
    DependsOn: InternetGatewayAttachment
    Properties:
      Domain: vpc
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-nat-eip-1'

  NatGateway1:
    Type: AWS::EC2::NatGateway
    Properties:
      AllocationId: !GetAtt NatGateway1EIP.AllocationId
      SubnetId: !Ref PublicSubnet1
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-nat-1'

  # Route Tables
  PublicRouteTable:
    Type: AWS::EC2::RouteTable
    Properties:
      VpcId: !Ref VPC
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-public-rt'

  DefaultPublicRoute:
    Type: AWS::EC2::Route
    DependsOn: InternetGatewayAttachment
    Properties:
      RouteTableId: !Ref PublicRouteTable
      DestinationCidrBlock: 0.0.0.0/0
      GatewayId: !Ref InternetGateway

  PublicSubnet1RouteTableAssociation:
    Type: AWS::EC2::SubnetRouteTableAssociation
    Properties:
      RouteTableId: !Ref PublicRouteTable
      SubnetId: !Ref PublicSubnet1

  PublicSubnet2RouteTableAssociation:
    Type: AWS::EC2::SubnetRouteTableAssociation
    Properties:
      RouteTableId: !Ref PublicRouteTable
      SubnetId: !Ref PublicSubnet2

  PrivateRouteTable1:
    Type: AWS::EC2::RouteTable
    Properties:
      VpcId: !Ref VPC
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-private-rt-1'

  DefaultPrivateRoute1:
    Type: AWS::EC2::Route
    Properties:
      RouteTableId: !Ref PrivateRouteTable1
      DestinationCidrBlock: 0.0.0.0/0
      NatGatewayId: !Ref NatGateway1

  PrivateSubnet1RouteTableAssociation:
    Type: AWS::EC2::SubnetRouteTableAssociation
    Properties:
      RouteTableId: !Ref PrivateRouteTable1
      SubnetId: !Ref PrivateSubnet1

  PrivateSubnet2RouteTableAssociation:
    Type: AWS::EC2::SubnetRouteTableAssociation
    Properties:
      RouteTableId: !Ref PrivateRouteTable1
      SubnetId: !Ref PrivateSubnet2

  # ===== SECURITY GROUPS =====
  ALBSecurityGroup:
    Type: AWS::EC2::SecurityGroup
    Properties:
      GroupName: !Sub '${Environment}-clubify-checkout-alb-sg'
      GroupDescription: Security group for Application Load Balancer
      VpcId: !Ref VPC
      SecurityGroupIngress:
        - IpProtocol: tcp
          FromPort: 80
          ToPort: 80
          CidrIp: 0.0.0.0/0
        - IpProtocol: tcp
          FromPort: 443
          ToPort: 443
          CidrIp: 0.0.0.0/0
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-alb-sg'

  FargateSecurityGroup:
    Type: AWS::EC2::SecurityGroup
    Properties:
      GroupName: !Sub '${Environment}-clubify-checkout-fargate-sg'
      GroupDescription: Security group for Fargate services
      VpcId: !Ref VPC
      SecurityGroupIngress:
        - IpProtocol: tcp
          FromPort: 3000
          ToPort: 3020
          SourceSecurityGroupId: !Ref ALBSecurityGroup
        - IpProtocol: tcp
          FromPort: 3000
          ToPort: 3020
          SourceSecurityGroupId: !Ref FargateSecurityGroup  # Service-to-service communication
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-fargate-sg'

  DatabaseSecurityGroup:
    Type: AWS::EC2::SecurityGroup
    Properties:
      GroupName: !Sub '${Environment}-clubify-checkout-db-sg'
      GroupDescription: Security group for DocumentDB and ElastiCache
      VpcId: !Ref VPC
      SecurityGroupIngress:
        - IpProtocol: tcp
          FromPort: 27017
          ToPort: 27017
          SourceSecurityGroupId: !Ref FargateSecurityGroup
        - IpProtocol: tcp
          FromPort: 6379
          ToPort: 6379
          SourceSecurityGroupId: !Ref FargateSecurityGroup
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-db-sg'

  # ===== DATABASE INFRASTRUCTURE =====
  DatabaseSubnetGroup:
    Type: AWS::DocDB::DBSubnetGroup
    Properties:
      DBSubnetGroupName: !Sub '${Environment}-clubify-checkout-docdb-subnet-group'
      DBSubnetGroupDescription: Subnet group for DocumentDB cluster
      SubnetIds:
        - !Ref DatabaseSubnet1
        - !Ref DatabaseSubnet2
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-docdb-subnet-group'

  DocumentDBCluster:
    Type: AWS::DocDB::DBCluster
    Properties:
      DBClusterIdentifier: !Sub '${Environment}-clubify-checkout-docdb'
      MasterUsername: docdbadmin
      MasterUserPassword: !Ref DatabasePassword
      BackupRetentionPeriod: 7
      PreferredBackupWindow: '03:00-04:00'
      PreferredMaintenanceWindow: 'sun:04:00-sun:05:00'
      DBSubnetGroupName: !Ref DatabaseSubnetGroup
      VpcSecurityGroupIds:
        - !Ref DatabaseSecurityGroup
      StorageEncrypted: true
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-docdb'

  DocumentDBInstance:
    Type: AWS::DocDB::DBInstance
    Properties:
      DBClusterIdentifier: !Ref DocumentDBCluster
      DBInstanceIdentifier: !Sub '${Environment}-clubify-checkout-docdb-instance'
      DBInstanceClass: db.t3.medium
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-docdb-instance'

  # ElastiCache Subnet Group
  CacheSubnetGroup:
    Type: AWS::ElastiCache::SubnetGroup
    Properties:
      CacheSubnetGroupName: !Sub '${Environment}-clubify-checkout-cache-subnet-group'
      Description: Subnet group for ElastiCache
      SubnetIds:
        - !Ref DatabaseSubnet1
        - !Ref DatabaseSubnet2

  # ElastiCache Redis
  RedisCluster:
    Type: AWS::ElastiCache::CacheCluster
    Properties:
      CacheClusterId: !Sub '${Environment}-clubify-checkout-redis'
      CacheNodeType: cache.t3.micro
      Engine: redis
      NumCacheNodes: 1
      Port: 6379
      CacheSubnetGroupName: !Ref CacheSubnetGroup
      VpcSecurityGroupIds:
        - !Ref DatabaseSecurityGroup
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-redis'

  # ===== ECS CLUSTER =====
  ECSCluster:
    Type: AWS::ECS::Cluster
    Properties:
      ClusterName: !Sub '${Environment}-clubify-checkout-cluster'
      CapacityProviders:
        - FARGATE
        - FARGATE_SPOT
      DefaultCapacityProviderStrategy:
        - CapacityProvider: FARGATE
          Weight: 1
      ClusterSettings:
        - Name: containerInsights
          Value: enabled
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-cluster'

  # ===== IAM ROLES =====
  TaskExecutionRole:
    Type: AWS::IAM::Role
    Properties:
      RoleName: !Sub '${Environment}-clubify-checkout-task-execution-role'
      AssumeRolePolicyDocument:
        Version: '2012-10-17'
        Statement:
          - Effect: Allow
            Principal:
              Service: ecs-tasks.amazonaws.com
            Action: sts:AssumeRole
      ManagedPolicyArns:
        - arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy
      Policies:
        - PolicyName: ECRAccess
          PolicyDocument:
            Version: '2012-10-17'
            Statement:
              - Effect: Allow
                Action:
                  - ecr:GetAuthorizationToken
                  - ecr:BatchCheckLayerAvailability
                  - ecr:GetDownloadUrlForLayer
                  - ecr:BatchGetImage
                Resource: '*'
        - PolicyName: SecretsManagerAccess
          PolicyDocument:
            Version: '2012-10-17'
            Statement:
              - Effect: Allow
                Action:
                  - secretsmanager:GetSecretValue
                Resource: !Sub 'arn:aws:secretsmanager:${AWS::Region}:${AWS::AccountId}:secret:${Environment}/clubify-checkout/*'

  TaskRole:
    Type: AWS::IAM::Role
    Properties:
      RoleName: !Sub '${Environment}-clubify-checkout-task-role'
      AssumeRolePolicyDocument:
        Version: '2012-10-17'
        Statement:
          - Effect: Allow
            Principal:
              Service: ecs-tasks.amazonaws.com
            Action: sts:AssumeRole
      Policies:
        - PolicyName: CloudWatchLogs
          PolicyDocument:
            Version: '2012-10-17'
            Statement:
              - Effect: Allow
                Action:
                  - logs:CreateLogGroup
                  - logs:CreateLogStream
                  - logs:PutLogEvents
                Resource: !Sub 'arn:aws:logs:${AWS::Region}:${AWS::AccountId}:log-group:/ecs/${Environment}-clubify-checkout-*'

  # ===== APPLICATION LOAD BALANCER =====
  ApplicationLoadBalancer:
    Type: AWS::ElasticLoadBalancingV2::LoadBalancer
    Properties:
      Name: !Sub '${Environment}-clubify-checkout-alb'
      Scheme: internet-facing
      Type: application
      Subnets:
        - !Ref PublicSubnet1
        - !Ref PublicSubnet2
      SecurityGroups:
        - !Ref ALBSecurityGroup
      Tags:
        - Key: Name
          Value: !Sub '${Environment}-clubify-checkout-alb'

  ALBListener:
    Type: AWS::ElasticLoadBalancingV2::Listener
    Properties:
      DefaultActions:
        - Type: fixed-response
          FixedResponseConfig:
            StatusCode: 404
            ContentType: text/plain
            MessageBody: 'Service not found'
      LoadBalancerArn: !Ref ApplicationLoadBalancer
      Port: 80
      Protocol: HTTP

  # ===== SERVICE DISCOVERY =====
  ServiceDiscoveryNamespace:
    Type: AWS::ServiceDiscovery::PrivateDnsNamespace
    Properties:
      Name: !Sub '${Environment}.clubify-checkout.local'
      Vpc: !Ref VPC
      Description: Service discovery namespace for Clubify Checkout services

Outputs:
  VPCId:
    Description: VPC ID
    Value: !Ref VPC
    Export:
      Name: !Sub '${Environment}-clubify-checkout-vpc-id'

  PrivateSubnet1Id:
    Description: Private Subnet 1 ID
    Value: !Ref PrivateSubnet1
    Export:
      Name: !Sub '${Environment}-clubify-checkout-private-subnet-1'

  PrivateSubnet2Id:
    Description: Private Subnet 2 ID
    Value: !Ref PrivateSubnet2
    Export:
      Name: !Sub '${Environment}-clubify-checkout-private-subnet-2'

  ECSClusterArn:
    Description: ECS Cluster ARN
    Value: !GetAtt ECSCluster.Arn
    Export:
      Name: !Sub '${Environment}-clubify-checkout-cluster-arn'

  ApplicationLoadBalancerArn:
    Description: Application Load Balancer ARN
    Value: !Ref ApplicationLoadBalancer
    Export:
      Name: !Sub '${Environment}-clubify-checkout-alb-arn'

  ALBListenerArn:
    Description: ALB Listener ARN
    Value: !Ref ALBListener
    Export:
      Name: !Sub '${Environment}-clubify-checkout-alb-listener-arn'

  FargateSecurityGroupId:
    Description: Fargate Security Group ID
    Value: !Ref FargateSecurityGroup
    Export:
      Name: !Sub '${Environment}-clubify-checkout-fargate-sg-id'

  TaskExecutionRoleArn:
    Description: Task Execution Role ARN
    Value: !GetAtt TaskExecutionRole.Arn
    Export:
      Name: !Sub '${Environment}-clubify-checkout-task-execution-role-arn'

  TaskRoleArn:
    Description: Task Role ARN
    Value: !GetAtt TaskRole.Arn
    Export:
      Name: !Sub '${Environment}-clubify-checkout-task-role-arn'

  DocumentDBClusterEndpoint:
    Description: DocumentDB Cluster Endpoint
    Value: !GetAtt DocumentDBCluster.Endpoint
    Export:
      Name: !Sub '${Environment}-clubify-checkout-docdb-endpoint'

  RedisClusterEndpoint:
    Description: Redis Cluster Endpoint
    Value: !GetAtt RedisCluster.RedisEndpoint.Address
    Export:
      Name: !Sub '${Environment}-clubify-checkout-redis-endpoint'

  ServiceDiscoveryNamespaceId:
    Description: Service Discovery Namespace ID
    Value: !Ref ServiceDiscoveryNamespace
    Export:
      Name: !Sub '${Environment}-clubify-checkout-namespace-id'
```

## Deployment Commands

### Deploy Infrastructure:
```bash
# Deploy base infrastructure
aws cloudformation deploy \
  --template-file template.yaml \
  --stack-name clubify-checkout-infrastructure-production \
  --parameter-overrides \
    Environment=production \
    DatabasePassword=YourSecurePassword123! \
    ContainerImagePrefix=123456789012.dkr.ecr.us-east-1.amazonaws.com/clubify-checkout \
  --capabilities CAPABILITY_NAMED_IAM

# Get outputs
aws cloudformation describe-stacks \
  --stack-name clubify-checkout-infrastructure-production \
  --query 'Stacks[0].Outputs'
```

### Environment Variables Configuration:
```bash
# Set up Secrets Manager for sensitive data
aws secretsmanager create-secret \
  --name "production/clubify-checkout/database" \
  --description "Database credentials for Clubify Checkout" \
  --secret-string '{"username":"docdbadmin","password":"YourSecurePassword123!"}'

aws secretsmanager create-secret \
  --name "production/clubify-checkout/jwt" \
  --description "JWT secrets for Clubify Checkout" \
  --secret-string '{"secret":"your-jwt-secret-key-here"}'
```

## Next Steps

1. **Deploy this base template** to create VPC, subnets, security groups, DocumentDB, and ElastiCache
2. **Create individual service templates** for each microservice with specific Fargate task definitions
3. **Configure CI/CD pipeline** to deploy services to ECR and update ECS services
4. **Set up monitoring** with CloudWatch dashboards and alarms

---
*SAM template design completed: Dom 21 Set 2025 19:15*