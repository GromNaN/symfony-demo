# User CRUD Quick Start Guide

## Setup

### 1. Create Database

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 2. Start Server

```bash
symfony server:start
```

or with docker:

```bash
docker-compose up -d
```

## Usage

### Home Page
Navigate to http://localhost:8000/user/ to see the user list.

### Create User

1. Click "New User" button
2. Fill in:
   - **Email**: A valid email address (deterministically encrypted)
   - **First Name**: User's first name (randomly encrypted)
   - **Last Name**: User's last name (randomly encrypted)
   - **Password**: A secure password (must be typed twice)
3. Click "Create User"
4. See the new user in the list (encrypted values are shown)

### View Users

The list page shows:
- User ID
- Encrypted email (first 20 chars)
- Encrypted first name (first 20 chars)
- Encrypted last name (first 20 chars)
- Action buttons

**Note**: Encrypted values are unreadable and truncated - this is expected!

### Edit User

1. Click "Edit" button next to a user
2. Form is pre-populated with **decrypted** values
3. Modify any field
4. Click "Update User"
5. Return to list to see the encrypted storage

**Password**: Leave empty to keep current password. To change password, you must update the controller to handle it.

### Delete User

1. Click "Delete" button next to a user
2. Confirm the deletion
3. User is removed from database

## Encryption Details

### Deterministic Email
- Same email always encrypts to the same value
- Allows database uniqueness constraint on encrypted column
- `encryptDeterministic(plaintext, dek)`

### Random Names
- Same name encrypts to different values each time
- Prevents correlation of identical names in database
- `encryptRandom(plaintext, dek)`

### Storage Format
- All encrypted values are stored as Base64
- DEK (Data Encryption Key) must be managed securely
- Currently uses `$_ENV['DATA_ENCRYPTION_KEY']` or random_bytes(32)

## Workflow Example

```
1. User fills form with plaintext:
   - email: sarah@example.test
   - firstName: Sarah
   - lastName: Connor
   - password: SecurePassword123!

2. Controller receives form data and:
   - Encrypts email (deterministically)
   - Encrypts firstName (randomly)
   - Encrypts lastName (randomly)
   - Hashes password with bcrypt
   - Base64-encodes encrypted values

3. Database stores:
   - email: "nH7k2jR9...mL5x8qP2" (base64-encoded ciphertext)
   - firstName: "a3Bq7yN4...xZ9vM6tL" (base64-encoded ciphertext)
   - lastName: "sW2lK8...nR4bX9hJ" (base64-encoded ciphertext)
   - password: "$2y$10$..." (bcrypt hash)

4. When editing:
   - Controller reads encrypted values
   - Decrypts them to show in form
   - User sees: sarah@example.test, Sarah, Connor
   - After save, new encryption happens (random values change)
```

## Configuration

### Change Data Encryption Key

Set in `.env`:
```bash
DATA_ENCRYPTION_KEY="your-secret-hex-key-here"
```

**Important**: Never commit DEK to version control!

### Secure DEK Management

For production, use:
- AWS Secrets Manager
- HashiCorp Vault
- Azure Key Vault
- Environment variables in secure deployment

## Testing

Run tests with:
```bash
php vendor/bin/phpunit
```

Tests cover:
- Entity encryption/decryption round-trips
- Form submission and validation
- Deterministic vs random encryption behavior
- Password hashing

## Routes

| Method | Route | Name | Handler |
|--------|-------|------|---------|
| GET | /user/ | user_list | list() |
| GET | /user/new | user_new | new() |
| POST | /user/new | user_new | new() |
| GET | /user/{id}/edit | user_edit | edit() |
| POST | /user/{id}/edit | user_edit | edit() |
| POST | /user/{id}/delete | user_delete | delete() |

## File Structure

```
templates/
├── base.html.twig          # Main layout with styles
└── user/
    ├── list.html.twig      # Show all users
    ├── new.html.twig       # Create user form
    └── edit.html.twig      # Edit user form

src/
├── Controller/
│   └── UserController.php  # CRUD actions
├── Form/
│   └── UserType.php        # Form definition
├── Repository/
│   └── UserRepository.php  # Database queries
└── Entity/
    └── User.php            # Entity with encrypted fields
```

## Common Issues

### Encrypted values look like random garbage
✓ This is correct! They should be unreadable.

### Form shows encrypted values instead of plaintext
✗ Bug in controller - check `edit()` action decrypt logic.

### Delete button doesn't work
- Ensure route name is `user_delete`
- Check form method is `POST`

### Create fails with unique constraint error
- Email encryption produced same value (shouldn't happen for random plaintext)
- Or DEK is inconsistent between requests

## Next Steps

1. Add more fields to User entity
2. Implement permission/role system
3. Add search functionality (for deterministic email field)
4. Create API endpoints
5. Add data export feature

