# Roles and Permissions Matrix

Source: project documentation, sections 4, 5 and Phase 3. Permissions are named `resource.action`.

## 1. Two separate scopes

| Scope | Roles | Notes |
|---|---|---|
| Platform | Super Admin | Manages organizations, plans, billing, AI usage, settings, audit logs and analytics across all tenants. Works through the Filament admin panel. Not a member of tenant organizations by default. |
| Business (per organization) | Owner, Admin, Manager, Staff, Accountant, Viewer | A user has one role per organization, stored on `organization_users.role_id`. The same user can hold different roles in different organizations. |

## 2. Authorization checks (in order)

1. Authenticated (Sanctum token).
2. Member of the active organization (`organization_users` row).
3. Role holds the required permission.
4. The resource belongs to the active organization (`organization_id` matches).

A failure at step 1 returns 401, at steps 2 or 3 returns 403, and at step 4 returns 404.

## 3. Permissions

Standard CRUD set for a resource: `view`, `create`, `update`, `delete`. Extra actions are listed per resource.

| Resource | Permissions |
|---|---|
| `customers` | view, create, update, delete |
| `categories` | view, create, update, delete |
| `products` | view, create, update, delete, `adjust_stock` |
| `orders` | view, create, update, delete, `confirm`, `cancel` |
| `payments` | view, create, `refund` |
| `invoices` | view, create, update, delete, `send` |
| `transactions` | view |
| `reports` | view, create, `export` |
| `expenses` | view, create, update, delete |
| `users` (members) | view, `invite`, `update_role`, `remove` |
| `organization` | view, `update` (profile, logo, timezone, currency, settings) |
| `subscription` | view, `manage` (change plan, cancel, payment method) |
| `ai` | `chat`, `view_usage`, `manage_documents` |
| `audit_logs` | view |

## 4. Role matrix (business roles)

`Y` = allowed, `-` = not allowed.

| Permission | Owner | Admin | Manager | Staff | Accountant | Viewer |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| customers.view | Y | Y | Y | Y | Y | Y |
| customers.create | Y | Y | Y | Y | - | - |
| customers.update | Y | Y | Y | Y | - | - |
| customers.delete | Y | Y | Y | - | - | - |
| categories.view | Y | Y | Y | Y | Y | Y |
| categories.create / update | Y | Y | Y | - | - | - |
| categories.delete | Y | Y | Y | - | - | - |
| products.view | Y | Y | Y | Y | Y | Y |
| products.create / update | Y | Y | Y | - | - | - |
| products.delete | Y | Y | Y | - | - | - |
| products.adjust_stock | Y | Y | Y | Y | - | - |
| orders.view | Y | Y | Y | Y | Y | Y |
| orders.create | Y | Y | Y | Y | - | - |
| orders.update | Y | Y | Y | Y | - | - |
| orders.delete | Y | Y | Y | - | - | - |
| orders.confirm | Y | Y | Y | Y | - | - |
| orders.cancel | Y | Y | Y | - | - | - |
| payments.view | Y | Y | Y | - | Y | - |
| payments.create | Y | Y | Y | - | Y | - |
| payments.refund | Y | Y | - | - | Y | - |
| invoices.view | Y | Y | Y | - | Y | Y |
| invoices.create / update | Y | Y | Y | - | Y | - |
| invoices.delete | Y | Y | - | - | Y | - |
| invoices.send | Y | Y | Y | - | Y | - |
| transactions.view | Y | Y | Y | - | Y | Y |
| reports.view | Y | Y | Y | - | Y | Y |
| reports.create / export | Y | Y | Y | - | Y | - |
| expenses.view | Y | Y | Y | - | Y | - |
| expenses.create / update | Y | Y | - | - | Y | - |
| expenses.delete | Y | Y | - | - | Y | - |
| users.view | Y | Y | Y | - | - | - |
| users.invite | Y | Y | - | - | - | - |
| users.update_role | Y | Y | - | - | - | - |
| users.remove | Y | Y | - | - | - | - |
| organization.view | Y | Y | Y | Y | Y | Y |
| organization.update | Y | Y | - | - | - | - |
| subscription.view | Y | Y | - | - | Y | - |
| subscription.manage | Y | - | - | - | - | - |
| ai.chat | Y | Y | Y | Y | Y | - |
| ai.view_usage | Y | Y | Y | - | Y | - |
| ai.manage_documents | Y | Y | Y | - | - | - |
| audit_logs.view | Y | Y | - | - | - | - |

## 5. Role rules

- **Owner**: full access. Exactly one Owner per organization at creation (the registering user). The only role that can manage the subscription, transfer ownership or delete the organization.
- **Admin**: everything except subscription management, ownership transfer and deleting the organization.
- **Manager**: runs day-to-day operations: customers, catalog, orders, reports. No user or organization administration, no refunds.
- **Staff**: operational work only (create and update customers and orders, adjust stock). No deleting, no financial data.
- **Accountant**: financial data: payments, refunds, invoices, transactions, expenses, reports. Read-only on customers, products and orders.
- **Viewer**: read-only on business data. No AI chat.

Guard rails:

- An Admin cannot change the role of, or remove, an Owner.
- A user cannot change their own role.
- The last Owner of an organization cannot be removed or demoted.
- Invited users get a role at invite time; the default is `Viewer`.

## 6. Super Admin (platform)

| Area | Access |
|---|---|
| Organizations | view, suspend, delete |
| Users | view, disable |
| Plans and subscriptions | full management |
| AI usage and costs | view, adjust limits |
| Platform settings | full |
| Audit logs | view across all organizations |
| Tenant business data (customers, orders, ...) | read-only, only for support, and every access is written to the audit log |

Super Admin access is granted through a platform flag on the user (`is_super_admin`), never through an organization role, and is enforced with a Gate (`Gate::before`) plus the Filament panel access check.

## 7. Implementation notes (for Phase 3)

- Tables: `roles`, `permissions`, `role_permissions` (see the core database structure in the project documentation), and `organization_users.role_id`.
- Seed the six business roles and all permissions from the tables above in one seeder so this document and the database stay in sync.
- Policies (`CustomerPolicy`, `OrderPolicy`, ...) call one central check such as `$user->hasPermissionIn($organization, 'customers.view')` and also verify `organization_id`.
- Cache a user's permission set per organization and invalidate it when the role changes.
- Every role and permission combination that matters gets a feature test, plus one explicit test that organization A cannot access organization B.
