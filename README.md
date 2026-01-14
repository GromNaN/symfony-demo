# MongoDB Messenger Transport - Atlas Validation

This project validates the facile-it/mongodb-messenger-transport bundle with MongoDB Atlas.

## Prerequisites

- PHP 8.4+
- Composer
- MongoDB Atlas account with a cluster set up
- Network access configured in MongoDB Atlas (whitelist your IP)

## Configuration

### 1. MongoDB Atlas Setup

The project is configured to use MongoDB Atlas. The connection details are in `.env`:

```env
MONGODB_URI=mongodb+srv://username:password@cluster0.xxxxx.mongodb.net/?retryWrites=true&w=majority
MONGODB_DB=symfony_messenger
```

**Update the credentials in `.env` file with your MongoDB Atlas credentials.**

### 2. Facile It MongoDB Bundle Configuration

The MongoDB bundle is configured in `config/packages/facile_it_mongodb.yaml`:
```yaml
mongo_db_bundle:
   data_collection: '%kernel.debug%'
   clients:
      default:
         # For MongoDB Atlas, use the uri parameter
         uri:              '%env(MONGODB_URI)%'
         # Atlas requires SSL/TLS
         ssl:              true
         connectTimeoutMS: 3000

   connections:
      default:
         client_name:    default
         database_name:  '%env(MONGODB_DB)%'
```

Note: The `ssl: true` setting is crucial for connecting to MongoDB Atlas.

### 3. Messenger Transport

The messenger transport is configured to use MongoDB in `config/packages/messenger.yaml`:

- Transport: `facile-it-mongodb://default`
- Queue name: `async` (default)
- Messages are routed to the async transport

## Usage

### 1. Install Dependencies

```bash
composer install
```

### 2. Clear Cache

```bash
php bin/console cache:clear
```

### 3. Publish Test Messages

Use the custom command to publish test messages to MongoDB Atlas:

```bash
# Publish 5 messages (default)
php bin/console app:publish-test-messages

# Publish 10 messages
php bin/console app:publish-test-messages 10

# Publish 20 messages with 500ms delay between each
php bin/console app:publish-test-messages 20 --delay=500
```

**Command Options:**
- `count` (argument): Number of messages to publish (default: 5)
- `--delay` or `-d`: Delay between messages in milliseconds (default: 100)

### 4. Consume Messages

To process the messages from the queue, run:

```bash
# Basic consumption
php bin/console messenger:consume async

# With verbose output to see what's happening
php bin/console messenger:consume async -vv

# With limit on number of messages
php bin/console messenger:consume async --limit=10

# With time limit (in seconds)
php bin/console messenger:consume async --time-limit=60
```

**Consume Command Options:**
- `-vv` or `-vvv`: Verbose output to see detailed processing information
- `--limit=N`: Stop after consuming N messages
- `--time-limit=N`: Stop after N seconds
- `--memory-limit=128M`: Stop if memory usage exceeds limit

## Verification

### Check MongoDB Atlas

1. Log into MongoDB Atlas dashboard
2. Navigate to your cluster
3. Click "Collections"
4. You should see a database named `symfony_messenger`
5. Inside, you'll find a collection named `messenger_messages` (or similar)
6. You can browse the documents to see your queued/processed messages

### Monitor in Real-time

Open two terminal windows:

**Terminal 1 - Publish messages:**
```bash
php bin/console app:publish-test-messages 20 --delay=2000
```

**Terminal 2 - Consume messages:**
```bash
php bin/console messenger:consume async -vv
```

You'll see messages being published in Terminal 1 and consumed in Terminal 2 in real-time!

## Architecture

### Components Created

1. **Message Class** (`src/Message/TestMessage.php`)
   - Simple DTO with content and timestamp
   - Implements readonly properties for immutability

2. **Message Handler** (`src/MessageHandler/TestMessageHandler.php`)
   - Processes TestMessage instances
   - Logs message content and processing status
   - Simulates 1 second processing time

3. **Console Command** (`src/Command/PublishTestMessagesCommand.php`)
   - Publishes configurable number of test messages
   - Shows progress bar
   - Supports delay between messages

## Troubleshooting

### Connection Issues

If you get connection errors:

1. Check your MongoDB Atlas credentials in `.env`
2. Verify your IP is whitelisted in Atlas Network Access
3. Ensure your cluster is running
4. Check the connection string format

### No Messages Being Consumed

1. Verify messages were published successfully
2. Check MongoDB Atlas collections for queued messages
3. Ensure the transport name matches in both config files
4. Run with `-vv` flag for detailed output

### Alternative: Local MongoDB

To use local MongoDB instead of Atlas:

1. Update `config/packages/facile_it_mongodb.yaml` to use `hosts` configuration (see comments in file)
2. Update `.env` to use individual MongoDB settings instead of URI

## Next Steps

- Implement retry mechanism with `failure_transport`
- Add message priority handling
- Create additional message types for real-world use cases
- Set up monitoring and alerting
- Configure message TTL and cleanup strategies

