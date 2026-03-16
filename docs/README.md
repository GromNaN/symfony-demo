# Documentation Index

## Getting Started

### Quick Start → [`docs/quick-start.md`](quick-start.md)
**5-minute setup guide**
- Database setup commands
- Starting the development server
- Basic CRUD usage examples
- Configuration notes

---

## Architecture & Design

### Skills & Entity Authoring → [`docs/skills-entities.md`](skills-entities.md)
**How to create encrypted entities**
- Entity design patterns
- Encryption rules (deterministic vs random)
- Password hashing with Symfony Security
- Form field mapping
- Code examples

### CRUD Implementation → [`docs/CRUD-IMPLEMENTATION.md`](CRUD-IMPLEMENTATION.md)
**Complete CRUD system overview**
- All created files and their purpose
- Security implementation details
- Design decisions
- Routes and workflows
- Testing information

---

## Implementation Guides

### Local KMS → [`docs/local-kms.md`](local-kms.md)
**Create local master key and configure MASTER_KEY_FILE**
- Key generation command
- Env variable setup
- Validation test command

### Vault KMS → [`docs/vault-agent-kms.md`](vault-agent-kms.md)
**Run Vault in Docker and create transit master key**
- Docker Compose startup
- Transit key creation
- Direct Vault API smoke tests
- Symfony wiring example

### Templates Reference → [`docs/templates.md`](templates.md)
**User interface documentation**
- Template structure (base, list, new, edit)
- Styling features and colors
- Responsive design breakpoints
- Customization instructions
- Accessibility notes

### Form Usage → [`docs/form-usage.md`](form-usage.md)
**Working with UserType form**
- Creating new users with encryption
- Editing existing users with decryption
- Form options and conditional fields
- Best practices for encryption in forms
- Code examples for controllers

---

## Code Files Reference

### Controllers
- **`src/Controller/UserController.php`**
  - Routes: `/user/`, `/user/new`, `/user/{id}/edit`, `/user/{id}/delete`
  - Handles encryption/decryption in form submission
  - Flash messages for user feedback

### Forms
- **`src/Form/UserType.php`**
  - Unmapped encrypted fields
  - Conditional password field
  - Password confirmation with RepeatedType

### Entities
- **`src/Entity/User.php`**
  - Public encrypted properties: `email`, `firstName`, `lastName`
  - Password hashing with `PasswordAuthenticatedUserInterface`
  - Doctrine ORM mapping

### Repositories
- **`src/Repository/UserRepository.php`**
  - Database access for User entity
  - Extends ServiceEntityRepository

### Encryption
- **`src/Encryption/Encryptor.php`**
  - `encryptRandom()` - Random IV for each encryption
  - `encryptDeterministic()` - Deterministic IV for searchable fields
  - `decrypt()` - Decrypts both types

### Templates
- **`templates/base.html.twig`** - Master layout with styles
- **`templates/user/list.html.twig`** - User listing
- **`templates/user/new.html.twig`** - Create form
- **`templates/user/edit.html.twig`** - Edit form

---

## Routes

| HTTP | Path | Name | Action | Notes |
|------|------|------|--------|-------|
| GET | `/user/` | `user_list` | List | Show all users |
| GET | `/user/new` | `user_new` | New | Show create form |
| POST | `/user/new` | `user_new` | New | Process creation |
| GET | `/user/{id}/edit` | `user_edit` | Edit | Show edit form |
| POST | `/user/{id}/edit` | `user_edit` | Edit | Process update |
| POST | `/user/{id}/delete` | `user_delete` | Delete | Remove user |

---

## Features Implemented

### ✅ Encryption
- [x] Deterministic email encryption (unique, searchable)
- [x] Random name encryption (prevents correlation)
- [x] Base64 encoding for storage
- [x] Encryption/decryption at form boundaries

### ✅ Authentication
- [x] Password hashing with bcrypt/argon2
- [x] `PasswordAuthenticatedUserInterface` implementation
- [x] Secure password storage

### ✅ User Interface
- [x] Professional styling with gradients
- [x] Mobile-responsive design
- [x] Flash messages (success/error)
- [x] Form validation error display
- [x] Empty state handling
- [x] Accessible HTML

### ✅ CRUD Operations
- [x] Create users with encrypted fields
- [x] Read users (display encrypted preview)
- [x] Update users (decrypt, edit, re-encrypt)
- [x] Delete users with confirmation

### ✅ Documentation
- [x] Skills & entity authoring guide
- [x] Template reference documentation
- [x] Form usage guide
- [x] Quick start guide
- [x] Implementation summary

---

## Technology Stack

| Component | Technology | Version |
|-----------|-----------|---------|
| Framework | Symfony | 8.0.* |
| Language | PHP | 8.4+ |
| Database | Doctrine ORM | 3.6+ |
| Templates | Twig | 3.x |
| Forms | Symfony Form | 8.0.* |
| Security | Symfony Security | 8.0.* |
| Password | bcrypt/argon2 | Built-in |

---

## Testing

Run tests with:
```bash
php vendor/bin/phpunit
```

Tests include:
- 8 entity tests (encryption/decryption round-trips)
- 5 form tests (structure and validation)
- 13 total tests with 34 assertions

---

## File Structure

```
talk-encryption/
├── docs/
│   ├── CRUD-IMPLEMENTATION.md      ← Overview of CRUD system
│   ├── form-usage.md               ← Form patterns & examples
│   ├── quick-start.md              ← 5-min setup guide
│   ├── skills-entities.md          ← Entity design guide
│   ├── templates.md                ← UI documentation
│   └── README.md                   ← This file
├── src/
│   ├── Controller/
│   │   └── UserController.php
│   ├── Entity/
│   │   └── User.php
│   ├── Encryption/
│   │   └── Encryptor.php
│   ├── Form/
│   │   └── UserType.php
│   └── Repository/
│       └── UserRepository.php
├── templates/
│   ├── base.html.twig
│   └── user/
│       ├── list.html.twig
│       ├── new.html.twig
│       └── edit.html.twig
└── tests/
    ├── Entity/
    │   └── UserTest.php
    └── Form/
        └── UserTypeTest.php
```

---

## Quick Reference

### Create a User
```bash
# Navigate to http://localhost:8000/user/new
# Fill email, names, password
# Click "Create User"
```

### View Users
```bash
# Navigate to http://localhost:8000/user/
# See all users with encrypted values
```

### Edit a User
```bash
# Click "Edit" on user row
# Form shows decrypted values
# Modify and click "Update User"
```

### Delete a User
```bash
# Click "Delete" on user row
# Confirm deletion
# User removed
```

---

## Key Concepts

### Deterministic Encryption
- Same plaintext → Same ciphertext
- Allows database uniqueness constraints
- Used for email field
- Function: `encryptDeterministic(plaintext, dek)`

### Random Encryption
- Same plaintext → Different ciphertext each time
- Prevents database-level correlation
- Used for firstName and lastName
- Function: `encryptRandom(plaintext, dek)`

### Decryption
- Extracts IV from stored payload
- Decrypts both deterministic and random
- Function: `decrypt(payload, dek)`

### Password Hashing
- One-way hashing (not encryption)
- Uses bcrypt or argon2
- Verified with `hashPassword()` and `verify()`

---

## Common Tasks

### Add a New Encrypted Field
1. Add property to `User` entity with `#[ORM\Column]`
2. Add field to `UserType` form with `'mapped' => false`
3. Encrypt in controller before persist
4. Decrypt in controller before form display
5. Update template to show field

### Change Encryption Algorithm
1. Modify `src/Encryption/Encryptor.php`
2. Update cipher algorithm (currently: `aes-256-cbc`)
3. Regenerate all encrypted fields with new algorithm
4. Consider data migration strategy

### Add Password Change
1. Create new form for password change
2. Add controller action for password update
3. Hash new password with `passwordHasher`
4. Update `$user->password` and flush

### Implement User Search
1. Create query method in `UserRepository`
2. Support searching encrypted email (deterministic)
3. Return filtered users
4. Display in template

---

## Need Help?

1. **Setup issues?** → See `docs/quick-start.md`
2. **Want to add fields?** → See `docs/skills-entities.md`
3. **Form questions?** → See `docs/form-usage.md`
4. **UI/Template?** → See `docs/templates.md`
5. **System overview?** → See `docs/CRUD-IMPLEMENTATION.md`

---

## License

Proprietary - See `LICENSE` file

---

**Last Updated**: March 2026
**Framework**: Symfony 8.0
**PHP Version**: 8.4+
