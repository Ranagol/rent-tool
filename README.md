# Content

- [2 APP DESCRIPTION](#2-app-description)
- [3 USED TECHNOLOGIES](#3-used-technologies)
- [4 FEATURES](#4-features)
  - [4.1 Tool inventory](#41-tool-inventory)
  - [4.2 Customer management](#42-customer-management)
  - [4.3 Rental management/history](#43-rental-managementhistory)
  - [4.4 Rental contract PDF](#44-rental-contract-pdf)
  - [4.5 Invoice PDF](#45-invoice-pdf)
  - [4.6 User accounts](#46-user-accounts)
- [5 MODELS](#5-models)
  - [5.1 Relationships](#51-relationships)
  - [5.2 Tool](#52-tool)
    - [5.2.1 Tool table](#521-tool-table)
    - [5.2.2 Tool business logic](#522-tool-business-logic)
    - [5.2.3 Tool deletion](#523-tool-deletion)
  - [5.3 User](#53-user)
  - [5.4 Customer](#54-customer)
    - [5.4.1 Customer table](#541-customer-table)
    - [5.4.2 Customer deletion](#542-customer-deletion)
  - [5.5 Rental](#55-rental)
    - [5.5.1 Rental table](#551-rental-table)
    - [5.5.2 Rental pricing](#552-rental-pricing)
    - [5.5.3 Rental business logic](#553-rental-business-logic)
    - [5.5.4 Rentals deleting](#554-rentals-deleting)
  - [5.6 RentalItem](#56-rentalitem)
    - [5.6.1 RentalItem table](#561-rentalitem-table)
- [6 RENTING PROCESS](#6-renting-process)
- [7 RETURNING RENTED TOOLS](#7-returning-rented-tools)

**RentTool 2026 - AGENTS.md**

Instructions for AI coding agents working on this project. Section numbers match the specification document. Follow the business rules below exactly; they are requirements, not suggestions.

# 2 APP DESCRIPTION

RentTool is a rental management system for companies that rent power tools professionally.

Features: tool inventory, customer management, rental history, rental invoice.

# 3 USED TECHNOLOGIES

- Symfony for the backend
- Twig for the frontend, with optional Alpine.js added later only if needed
- Docker
- PostgreSQL
- Tailwind

# 4 FEATURES

## 4.1 Tool inventory

Besides CRUD, the tool inventory needs only:

- Search: by tool name or serial number.
- Filter: by status (`available`, `rented`, `in_repair`, `retired`).
- Status management: users can mark tools as `available` or `in_repair`, etc. Rental workflows manage the `rented` status automatically.

## 4.2 Customer management

- CRUD: create, view, edit, and delete customers.
- Search: by customer name, email, or phone number.
- Customer details page: contact information, address, comments, and rental history.

## 4.3 Rental management/history

- Create rental: select available tools and an existing customer, set the rental start date and expected return date.
- Automatic status updates: set tools to `rented` when a rental is created and back to `available` when returned.
- Return tool: record the actual return date.
- Rental history: list rentals with the customer, tools, rental dates, and total cost.
- Search and filters: search by customer or tool; filter by active and returned rentals.

Important business rule: a tool in repair cannot be rented, and a rented tool cannot be rented again.

## 4.4 Rental contract PDF

- Generate a printable contract PDF from an existing rental.
- Company branding: display the company name, address, and logo.
- Contract content: customer details, tool name and serial number, rental start date, expected return date, and estimated total rental cost calculated using the expected return date.
- Signature field: provide space for the customer's handwritten signature.
- Download and print: salespeople can download and print the PDF.
- The generated PDF must open automatically in a new browser tab.

Important rule: generate the contract from the `rentals` data. Do NOT create a separate Contract model or database table.

## 4.5 Invoice PDF

- There is no Invoice model and no `invoices` table. Do not create them.
- All invoice data is already in the `rentals` table (and related RentalItem, Customer, Tool data).
- Invoice number: the Rental ID.
- Issue date: `actual_return_date`.
- Due date: 10 days after the issue date.
- The invoice PDF is generated from existing rental data.
- Generating the same invoice again must not create a second invoice.
- Cancelled rentals must not be invoiced.

Important rule: one rental can have at most one invoice.

## 4.6 User accounts

- Users can register through the application and log in with a form-based login.
- Users are ordinary salespeople with the same access; roles and permissions are not needed.
- Users must log in to use the rental-management features.

# 5 MODELS

## 5.1 Relationships

1. User to Rental
   - One User can create many Rentals.
   - Each Rental belongs to exactly one User.
2. Customer to Rental
   - One Customer can have many Rentals.
   - Each Rental belongs to exactly one Customer.
3. Rental to RentalItem
   - One Rental contains one or more RentalItems.
   - Each RentalItem belongs to exactly one Rental.
4. Tool to RentalItem
   - One Tool can appear in many RentalItems over time.
   - Each RentalItem refers to exactly one Tool.
   - A Tool can belong to only one active Rental at a time.

Contracts and invoices: PDFs are generated from existing Rental, RentalItem, Customer, and Tool data. There are no separate Contract or Invoice models or database tables.

## 5.2 Tool

### 5.2.1 Tool table

| Column | Definition |
| --- | --- |
| `id` | INT, PRIMARY KEY, AUTO_INCREMENT, NOT NULL |
| `name` | VARCHAR(255), NOT NULL |
| `serial_number` | VARCHAR(100), NOT NULL, UNIQUE |
| `description` | TEXT, NULL |
| `image_url` | VARCHAR(2048), NOT NULL. Mandatory whenever a tool is created; the salesperson enters it manually. |
| `comments` | TEXT, NULL |
| `daily_rent_cost` | DECIMAL(10,2), NOT NULL, must not be negative |
| `status` | VARCHAR(20), NOT NULL, DEFAULT `'available'`. Allowed values: `available`, `rented`, `in_repair`, `retired` |

### 5.2.2 Tool business logic

- A tool cannot have more than one active rental at a time.
- A tool can only be added to a rental if its status is `available`.
- Prevent two simultaneous active rentals of the same tool, including concurrent requests.
- Rental creation must use a database transaction and lock the selected tool records while checking availability and creating the rental.
- Rental creation and tool status updates must succeed or fail together.

### 5.2.3 Tool deletion

1. The `RentalItem.tool_id` foreign key must prevent deleting a tool that appears in rental history (RESTRICT).
2. Application: reject the deletion and display: "This tool cannot be deleted because it has rental history."
3. Result: the tool and all historical rentals remain intact.

## 5.3 User

The salespeople who operate this app. Persist registered users and use the default Symfony user model with Symfony Security's form-login authenticator. All users have the same access; do not add roles or permissions.

## 5.4 Customer

### 5.4.1 Customer table

| Column | Definition |
| --- | --- |
| `id` | INT, PRIMARY KEY, AUTO_INCREMENT |
| `name` | VARCHAR(255), NOT NULL |
| `email` | VARCHAR(255), NOT NULL, UNIQUE |
| `phone` | VARCHAR(30), NULL |
| `address` | VARCHAR(255), NULL |
| `city` | VARCHAR(100), NULL |
| `postal_code` | VARCHAR(20), NULL |
| `comments` | TEXT, NULL |

### 5.4.2 Customer deletion

1. Database: the `Rental.customer_id` foreign key must prevent deleting a customer who has rentals (RESTRICT).
2. Application: reject the operation and display a message such as: "This customer cannot be deleted because they have rental history."
3. Result: the customer and all historical rentals remain intact.

## 5.5 Rental

### 5.5.1 Rental table

| Column | Definition |
| --- | --- |
| `id` | INT, PRIMARY KEY, AUTO_INCREMENT |
| `customer_id` | INT, NOT NULL, FOREIGN KEY REFERENCES customers(id) |
| `user_id` | INT, NOT NULL, FOREIGN KEY REFERENCES users(id) |
| `rental_start_date` | DATETIME, NOT NULL |
| `expected_return_date` | DATETIME, NOT NULL. Default offered in the frontend: `rental_start_date` + 1 day |
| `actual_return_date` | DATETIME, NULL. Records the actual return date. |
| `total_rent_cost` | DECIMAL(10,2), NOT NULL. Total cost of all rental items together. |
| `is_cancelled` | BOOLEAN, false by default |

Definitions and rules:

- A rental is active when `actual_return_date` is NULL and `is_cancelled` is false.
- A rental can never be deleted; it must stay in the system. It can be cancelled (tool broke, customer changed their mind, needed a stronger model, etc.).
- A cancelled rental is excluded from active rentals and must not be invoiced.
- Cancellation does not automatically mean the tools have been returned. Tools must not be marked `available` until they are physically returned; tools returned damaged must be marked `in_repair`.
- Cancelled rentals and their RentalItems remain in the database.

Validation:

- `expected_return_date` must be strictly later than `rental_start_date`.
- `actual_return_date` must be on or after `rental_start_date` (an immediate return is possible).

### 5.5.2 Rental pricing

Rental time is measured in days, from `rental_start_date`. The `actual_return_date` is filled in when the customer returns the tools.

All prices and totals are in euros (EUR). Do not calculate or add taxes.

- Billable days = number of started 24-hour periods between `rental_start_date` and `actual_return_date`, with a minimum of 1 day.
  - 23 hours = 1 day cost
  - 24 hours = 1 day cost
  - 25 hours = 2 days cost
- Before return, the estimated cost is calculated using `expected_return_date`.
- `Rental.total_rent_cost` = sum of each RentalItem's `daily_rent_cost` multiplied by the rental's billable days.
- The total is calculated when the rental is created and finalized upon return.
- Costs must never be negative. `Tool.daily_rent_cost` and `RentalItem.daily_rent_cost` must also be nonnegative.

### 5.5.3 Rental business logic

- Only tools with status `available` can be rented.
- Tools associated with an active rental cannot have their status changed manually.
- When a rental is created, all included tools change to `rented`.
- When a rental is returned, mark each tool that needs repair as `in_repair`; mark the other returned tools `available`.
- Tools with status `in_repair` or `retired` cannot be rented.
- A tool can be changed manually between `available`, `in_repair`, and `retired` only when it is not associated with an active rental.

### 5.5.4 Rentals deleting

- Saved rentals and their associated RentalItems cannot be permanently deleted.
- A rental can be cancelled by setting `is_cancelled` to true.
- Cancellation must preserve the rental and all its RentalItems.

## 5.6 RentalItem

### 5.6.1 RentalItem table

| Column | Definition |
| --- | --- |
| `id` | INT, PRIMARY KEY, AUTO_INCREMENT, NOT NULL |
| `rental_id` | INT, NOT NULL, FOREIGN KEY REFERENCES rentals(id) |
| `tool_id` | INT, NOT NULL, FOREIGN KEY REFERENCES tools(id), ON DELETE RESTRICT |
| `daily_rent_cost` | DECIMAL(10,2), NOT NULL, nonnegative |

- `daily_rent_cost` is stored here because a tool's price may change over time while rental history must not change. When a tool is rented out, copy the current `daily_rent_cost` from the `tools` table into the RentalItem.
- Add a unique constraint on (`rental_id`, `tool_id`) so the same tool cannot be added twice to one rental.

# 6 RENTING PROCESS

1. The customer wants to rent some tools.
2. The salesperson creates a new customer in the system if none exists.
3. The salesperson creates a new rental.
4. Multiple tools with status `available` can be added to the rental.
5. On save, in one database transaction: create the rental and its RentalItems, mark the selected tools `rented` (they cannot be rented again until returned).
6. The rental contract is created automatically: generate the contract PDF and open it in a new tab.

# 7 RETURNING RENTED TOOLS

1. The customer returns all rented tools together to the shop.
2. The salesperson finds the rental in the system and closes it:
   - `actual_return_date` is set to the current date and time.
   - The salesperson marks each returned tool that needs repair as `in_repair`; other returned tools are marked `available`.
   - The final `total_rent_cost` is calculated.
   - The invoice PDF is generated automatically from the rental data (see 4.5).
