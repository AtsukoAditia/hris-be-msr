# Backend Development Guide

## Local Setup

```bash
git clone https://github.com/AtsukoAditia/hris-be-msr.git
cd hris-be-msr
composer install
cp .env.example .env
php artisan key:generate
```

Configure MySQL in `.env`, then run:

```bash
php artisan migrate --seed
php artisan serve
```

Default API base URL:

```text
http://localhost:8000/api/v1
```

## Development Workflow

1. Read the active milestone in `docs/ROADMAP.md`.
2. Confirm current module status in `docs/PROJECT_STATUS.md`.
3. Inspect the related migration, model, request, service, controller, route, and test.
4. Define or update the API contract before connecting the frontend.
5. Implement authorization and business rules in the backend.
6. Add automated tests for success and failure paths.
7. Run formatting and tests.
8. Synchronize frontend integration.
9. Update documentation in both repositories.

## Recommended Module Structure

A substantial module should normally include:

```text
migration
model + relationships + casts
factory/seeder when useful
Form Request validation
policy or role/ownership authorization
service/domain class
controller
API resource or stable transformer
routes
feature tests
unit tests for calculations
activity logging coverage
documentation
```

## Coding Rules

- Keep controllers thin.
- Avoid embedding business calculations directly in controllers.
- Use named service methods for state transitions.
- Use enums or centralized constants for lifecycle statuses.
- Use decimal columns and deterministic arithmetic for money.
- Use eager loading deliberately to avoid N+1 queries.
- Paginate large lists.
- Apply database indexes to common filters and foreign keys.
- Do not trust IDs, role names, totals, or calculated values supplied by the frontend.

## Authorization Checklist

For every endpoint, verify:

- Is authentication required?
- Which roles may access it?
- Is employee ownership required?
- Is manager scope limited to direct reports?
- Can a record be accessed across branches or departments?
- Is the file download private?
- Is the action allowed in the current status?

A frontend hidden button is not authorization.

## Validation and Error Handling

- Use Form Requests for payload validation.
- Return validation errors with actionable field messages.
- Use `404` for unavailable records after authorized scoping.
- Use `403` for explicit authorization denial.
- Use `409` for state or concurrency conflicts when appropriate.
- Never expose stack traces or secrets in production responses.

## Transaction and Concurrency Checklist

Use a transaction when an action:

- Changes approval status and related records.
- Mutates leave balance.
- Generates multiple payroll items.
- Finalizes payroll.
- Replaces a document and updates its version.
- Could be submitted twice.

Use row locking where duplicate concurrent processing could corrupt state.

## File Security

- Store sensitive files on a private disk.
- Validate MIME type, extension, and file size.
- Generate controlled file names.
- Authorize every download.
- Remove or archive replaced files according to retention policy.
- Exclude binary values from activity-log payloads.

## Testing

Run locally:

```bash
composer test
vendor/bin/pint --test
```

Minimum feature-test cases:

- Unauthenticated request.
- Forbidden role.
- Ownership or manager-scope violation.
- Validation failure.
- Successful workflow.
- Invalid status transition.
- Duplicate or concurrency-sensitive request where relevant.
- Database side effects and audit records.

Calculation-heavy services such as leave duration, overtime, and payroll require focused unit tests.

## Documentation Checklist

When completing a module, update:

- `README.md`
- `docs/PROJECT_STATUS.md`
- `docs/MODULES.md`
- `docs/ROADMAP.md`
- `docs/API_MATRIX.md`
- Related frontend documentation

## Definition of Done

A module is complete when:

- Database design and migration are safe.
- API contract is stable.
- Backend authorization is enforced.
- Validation and status transitions are correct.
- Transactions and locking are applied where needed.
- Automated tests pass.
- Laravel Pint passes.
- Frontend integration is complete.
- Responsive/mobile acceptance is complete.
- Activity logging is sufficient.
- Documentation is synchronized.
