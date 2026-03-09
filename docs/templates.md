# User CRUD Templates

## Overview

Complete Twig templates for user management CRUD operations with modern styling and encryption-aware UI.

## Templates

### 1. `templates/base.html.twig`
Main layout template with:
- Navigation header with links to Users and New User
- Flash message display (success/error alerts)
- Responsive CSS styles
- Form styling (inputs, buttons, labels)
- Table styling for listing
- Grid-based form layout for passwords

**Features:**
- Mobile-responsive design
- Gradient background (#667eea to #764ba2)
- Professional color scheme
- Accessibility-friendly styling

### 2. `templates/user/list.html.twig`
Displays all users in a table.

**Columns:**
- ID: User's database identifier
- Email: First 20 chars of encrypted email (with "encrypted" note)
- First Name: First 20 chars of encrypted first name (with "encrypted" note)
- Last Name: First 20 chars of encrypted last name (with "encrypted" note)
- Actions: Edit and Delete buttons

**Features:**
- Empty state with link to create first user
- Hover effects on table rows
- Action buttons in each row
- Truncated display of encrypted values (they're unreadable anyway)
- Delete confirmation dialog

### 3. `templates/user/new.html.twig`
Create new user form.

**Fields:**
- Email: EmailType input
- First Name: TextType input
- Last Name: TextType input
- Password: RepeatedType (password confirmation)

**Features:**
- Educational info about encryption types
- Side-by-side password fields (mobile-responsive)
- Help text for each field
- Form error display
- Create/Cancel buttons

### 4. `templates/user/edit.html.twig`
Edit existing user form.

**Fields:**
- Email: EmailType input (pre-decrypted)
- First Name: TextType input (pre-decrypted)
- Last Name: TextType input (pre-decrypted)

**Features:**
- No password field by default (use `include_password: false`)
- Form pre-populated with decrypted values
- Note that password can be changed separately
- Help text explaining encryption
- Update/Cancel buttons

## Controller Integration

### List Users
```
GET /user/
Route: user_list
Template: user/list.html.twig
```

### Create User
```
GET  /user/new  → Shows form
POST /user/new  → Processes form
Route: user_new
Template: user/new.html.twig
```

### Edit User
```
GET  /user/{id}/edit  → Shows form with decrypted values
POST /user/{id}/edit  → Processes form
Route: user_edit
Template: user/edit.html.twig
```

### Delete User
```
POST /user/{id}/delete
Route: user_delete
Redirects: user_list
```

## Styling Features

### Colors
- **Primary**: #667eea (Blue)
- **Secondary**: #764ba2 (Purple)
- **Danger**: #dc3545 (Red)
- **Success**: #155724 (Green)
- **Gray**: #6c757d

### Responsive Design
- Mobile-first approach
- Breakpoint: 768px
- Password fields stack on mobile
- Form actions are full-width on mobile

### Form Elements
- 10px padding on inputs
- 4px border-radius
- Blue focus outline with shadow
- Help text in gray (#666)
- Error text in red (#721c24)

### Table Design
- Hover effects (light gray background)
- Striped header (light gray)
- Clean spacing (12px padding)
- Truncated encrypted values with indicators

## Flash Messages

The templates support Symfony flash messages:

```php
$this->addFlash('success', 'User created successfully.');
$this->addFlash('error', 'Something went wrong.');
```

Display in template automatically via base.html.twig flash loop.

## Accessibility

- Semantic HTML (table, header, nav)
- Proper label associations
- Form help text for context
- Confirmation dialogs on destructive actions
- Color contrast ratios meet WCAG AA

## Customization

### Change Button Colors
Edit CSS in `base.html.twig` `.btn-primary`, `.btn-secondary`, `.btn-danger` classes.

### Add More Fields
1. Update `UserType` form in `src/Form/UserType.php`
2. Add form field in the template
3. Update controller to handle encryption

### Modify Table Columns
Edit `templates/user/list.html.twig` table structure and adjust column display.

### Update Gradient
Change `background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);` in CSS.

## Security Notes

- All encrypted fields show truncated values (unreadable)
- Delete button requires confirmation
- Forms use Symfony's form component with CSRF protection
- Sensitive data is never displayed in plaintext
- Passwords are never shown after creation

