# RentTool Implementation Plan

## Current State Analysis

### ✅ What Exists
- **Symfony 8.1** skeleton with Doctrine ORM (PostgreSQL), Twig, Tailwind
- **Docker Compose** setup: PHP (FrankenPHP) + PostgreSQL containers running
- **Security Bundle** configured with in-memory user provider (needs replacement)
- **Maker Bundle** available for scaffolding
- **Base template** at `templates/base.html.twig` (minimal)
- **Empty directories** ready for entities, controllers, repositories

### ❌ What's Missing

#### Core Models (not created)
- `User` entity (Doctrine user, not in-memory)
- `Tool` entity
- `Customer` entity
- `Rental` entity
- `RentalItem` entity

#### Authentication & Security
- Persistent User model with password hashing
- User registration form and controller
- Login form
- Security provider pointing to database instead of in-memory
- User fixtures for testing

#### Controllers & Routes
- All CRUD controllers (Tools, Customers, Rentals)
- Authentication controllers (Register, Login, Logout)
- PDF generation controllers (Contract, Invoice)
- API endpoints or form handlers

#### Views/Templates
- All Twig templates (list, create, edit, delete, detail pages)
- PDF templates for contracts and invoices
- Layout and navigation components
- Form templates

#### Business Logic Services
- Tool availability and locking logic
- Rental cost calculation
- PDF generation services
- Validation services

#### Database Migrations
- None created yet

#### Tests
- None created yet (no tests/ directory)

#### Missing Packages
- **PDF generation**: `dompdf` (or Knp Snappy for wkhtmltopdf)
- **Password validation**: May need `symfony/validator` package enhancements

---

## Proposed Implementation Plan

### Phase 1: Foundation (Authentication & Core Models)
**Goal**: Set up persistent user system and basic data models

#### Step 1.1: Create User Entity & Security System
- Run `make:user` to create Doctrine-backed User entity
- Update security.yaml to use doctrine_orm provider
- Create registration form and controller
- Create login template
- Create UserRepository

**Why first?** Everything requires authentication; unblocks all other features.

r


#### Step 1.2: Create Core Entities
- `Tool` (name, serial, status, daily_rent_cost, etc.)
- `Customer` (name, email, phone, address, city, postal_code, comments)
- `Rental` (customer_id, user_id, start/expected_return/actual_return dates, total_cost, is_cancelled)
- `RentalItem` (rental_id, tool_id, daily_rent_cost snapshot)

**Database constraints:**
- Tool.serial_number UNIQUE
- Customer.email UNIQUE
- RentalItem unique constraint on (rental_id, tool_id)
- Foreign keys with appropriate ON DELETE rules (RESTRICT for deletions, CASCADE for rentals)

#### Step 1.3: Create Database Migrations
- Run `make:migration` to generate initial schema
- Review migrations for correctness
- Test migrations locally

---

### Phase 2: Basic CRUD & Listings
**Goal**: Allow creation and viewing of data without complex business logic

#### Step 2.1: Tool Inventory CRUD
- `ToolController` with index, show, create, edit, delete actions
- `ToolRepository` with search/filter by name, serial, status
- Templates for tool list, create form, edit form, detail view
- Add validation: daily_rent_cost ≥ 0, required fields

#### Step 2.2: Customer Management CRUD
- `CustomerController` with standard CRUD
- `CustomerRepository` with search by name/email/phone
- Templates: list, create, edit, detail with rental history
- Add validation: email format, required fields

#### Step 2.3: Rental Index & Show
- `RentalController` index (list rentals with customer/tools)
- `RentalController` show (rental detail with items)
- `RentalRepository` queries for active rentals, historical, by customer/tool
- Templates: list, detail view

**No business logic yet** — just persistence and display.

---

### Phase 3: Core Rental Business Logic
**Goal**: Implement rental creation/return workflows with safety

#### Step 3.1: Rental Creation with Concurrency Control
- `RentalService` with create() method
- Database transaction + SELECT FOR UPDATE on tools
- Validate: tools exist, have status='available', not already active
- On create: store tool prices in RentalItem, mark tools 'rented', calculate estimated cost
- Auto-generate contract PDF and return URL to open in new tab

#### Step 3.2: Rental Return Handler
- `RentalService` with return() method
- Mark rental with actual_return_date
- UI: form to mark each returned tool as available or in_repair
- Calculate final total_rent_cost based on actual dates
- Mark tools accordingly (per-tool decision, not bulk)
- Auto-generate invoice PDF

#### Step 3.3: Rental Cancellation
- `RentalService` with cancel() method
- Set is_cancelled = true
- Do NOT auto-return tools (salesperson manually marks status)
- Prevent invoicing of cancelled rentals

#### Step 3.4: Tool Status Validation
- Prevent manual tool status changes when tool is active in rental
- Prevent renting tools with status != 'available'
- Prevent renting in_repair or retired tools

---

### Phase 4: PDF Generation
**Goal**: Generate printable contracts and invoices

#### Step 4.1: Add PDF Library
- Install `knplabs/knp-snappy` (requires wkhtmltopdf binary)
  OR `dompdf/dompdf` (pure PHP, no external binary)
- Recommendation: **dompdf** (simpler setup, no binary)

#### Step 4.2: Contract PDF
- Create `PDF/ContractGenerator` service
- Render `templates/pdf/contract.html.twig`
- Include: company name/address/logo, customer details, tool names/serials, rental dates, estimated cost
- Signature field
- Controller action to generate and return PDF for download

#### Step 4.3: Invoice PDF
- Create `PDF/InvoiceGenerator` service
- Render `templates/pdf/invoice.html.twig`
- Include: company name, customer, tools, rental dates, actual cost, EUR currency
- Invoice number = Rental.id
- Issue date = actual_return_date, Due date = +10 days
- Controller action to generate and return PDF

#### Step 4.4: One-Invoice-Per-Rental Rule
- Add method `Rental::hasInvoice()` (check if actual_return_date is set)
- Prevent generating invoice twice for same rental
- Template/UI: disable invoice generation if already returned

---

### Phase 5: UI & Polish
**Goal**: Complete user interface with animations and styling

#### Step 5.1: Navigation & Layout
- Create main navigation (Tools, Customers, Rentals, Logout)
- Extend base.html.twig with navigation, CSS structure
- Add Tailwind design tokens per style.md (colors, spacing, transitions)

#### Step 5.2: Forms & Components
- Build reusable form components (text, select, date, error display)
- Add hover/focus/validation states with CSS transitions
- Implement status badges (available/rented/in_repair/retired)

#### Step 5.3: Table & List Components
- Create interactive table layout with hover feedback
- Add pagination, sorting, filtering controls
- Animated status transitions

#### Step 5.4: Modals & Dialogs
- Create confirmation dialogs for deletions
- Return-tool modal for marking individual tools
- Animations for entrance/exit

---

### Phase 6: Testing & Refinement
**Goal**: Ensure reliability and handle edge cases

#### Step 6.1: Database & Constraint Tests
- Test foreign key RESTRICTs (prevent deletion of tools/customers with history)
- Test unique constraints (serial, email, rental_item combo)
- Test migration rollback

#### Step 6.2: Business Logic Tests
- Test rental creation: availability checks, locking, cost calculation
- Test rental return: tool status updates, cost finalization
- Test cancellation: is_cancelled flag, no invoice
- Test concurrent rentals (two simultaneous requests for same tool)

#### Step 6.3: Integration Tests
- Test full flows: register → create tool → rent → return → view invoice
- Test form validation and error messages
- Test PDF generation

#### Step 6.4: Security Tests
- Test unauthenticated users cannot access protected routes
- Test CSRF tokens on forms
- Test password hashing

---

## Implementation Order (Strict Sequence)

1. **Phase 1.1**: User entity & auth → can log in
2. **Phase 1.2**: Tool, Customer, Rental, RentalItem entities
3. **Phase 1.3**: Migrations → database schema in place
4. **Phase 2.1**: Tool CRUD + UI
5. **Phase 2.2**: Customer CRUD + UI
6. **Phase 2.3**: Rental index/show + basic UI
7. **Phase 3.1**: Rental creation with transactions
8. **Phase 3.2**: Rental return with tool classification
9. **Phase 3.3**: Rental cancellation
10. **Phase 3.4**: Tool status validation
11. **Phase 4.1**: Install PDF library
12. **Phase 4.2**: Contract PDF generation
13. **Phase 4.3**: Invoice PDF generation
14. **Phase 4.4**: One-invoice-per-rental safeguards
15. **Phase 5.1**: Navigation & layout
16. **Phase 5.2-4**: Forms, tables, modals, animations
17. **Phase 6.1-4**: Comprehensive testing

---

## Questions for User Decision

1. **Company Branding (PDFs)**
   - What is the company name to display on contracts/invoices?
   - Where is the company address/location?
   - Do you have a logo image to include? (If yes, provide path or file)

2. **Tool Categories**
   - Should tools be grouped by category (e.g., "Drills", "Saws")? Not in spec but mentioned in style.md filters.
   - If yes, should we add a `Tool.category` field?

3. **PDF Technology**
   - Preference: **dompdf** (pure PHP, no external binary) or **wkhtmltopdf** (external binary, better rendering)?

4. **Cost Calculation Precision**
   - Daily costs use DECIMAL(10,2). Rounding for final total: round-half-up, truncate, or other?

5. **Rental History Scope**
   - Show only customer's own rentals in "Rental History", or all rentals to all customers?

6. **Soft Deletes**
   - Should deleted tools/customers be marked as "deleted" rather than removed? (Spec says RESTRICT, so no actual deletes anyway.)

---

## Risk Factors & Mitigation

| Risk | Mitigation |
|------|-----------|
| Concurrent rental of same tool | Use SELECT FOR UPDATE in transaction (Phase 3.1) |
| Invoice generated multiple times | Check actual_return_date exists (Phase 4.4) |
| Tool price changes affecting history | Store snapshot in RentalItem (Phase 1.2) |
| Password security | Use Symfony's password hashers, never plain text |
| CSRF attacks | Symfony form protection automatic |
| Missing auth on routes | Use `#[IsGranted('ROLE_USER')]` on all rental actions |

---

## Estimated Size per Phase

- **Phase 1**: 3-4 hours (User, models, migrations)
- **Phase 2**: 5-6 hours (3 CRUD sets, templates)
- **Phase 3**: 4-5 hours (transactions, cost calc, cancellation)
- **Phase 4**: 2-3 hours (PDF setup, templates)
- **Phase 5**: 4-6 hours (UI, animations, forms)
- **Phase 6**: 3-4 hours (tests)

**Total: ~21-28 hours** for a complete, tested implementation.
