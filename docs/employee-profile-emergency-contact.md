# Employee Profile and Emergency Contact Backend

Status: **Backend implemented**

## Data Model

### Employee Profile

One-to-one relation:

```text
employees.id -> employee_profiles.employee_id
```

Extended fields:

```text
personal_email
alternate_phone
place_of_birth
marital_status
blood_type
religion
nationality
identity_address
domicile_address
city
province
postal_code
tax_number
social_security_number
health_insurance_number
```

Core personal fields remain in `employees`:

```text
phone
address
birth_date
gender
```

`personal_email`, `tax_number`, `social_security_number`, and `health_insurance_number` are unique when populated.

### Emergency Contact

One Employee can have up to five active contacts.

```text
name
relationship
phone
alternate_phone
email
address
is_primary
notes
```

Allowed relationships:

```text
parent
spouse
sibling
child
relative
friend
other
```

Rules:

- The first contact automatically becomes primary.
- Setting another contact as primary clears the previous primary flag.
- Deleting the primary contact promotes the oldest remaining contact.
- An Employee always has exactly one primary contact while contacts exist.
- Cross-Employee contact mutation returns `404`.

## Self-Service Endpoints

Available to every authenticated user with an Employee record:

```http
GET   /api/v1/profile/me
PUT   /api/v1/profile/me
PATCH /api/v1/profile/me

GET    /api/v1/profile/me/emergency-contacts
POST   /api/v1/profile/me/emergency-contacts
PUT    /api/v1/profile/me/emergency-contacts/{emergencyContact}
PATCH  /api/v1/profile/me/emergency-contacts/{emergencyContact}
DELETE /api/v1/profile/me/emergency-contacts/{emergencyContact}
```

Users without an Employee record receive `404`.

## Administrative Endpoints

Available to Admin and HR:

```http
GET   /api/v1/employees/{employee}/profile
PUT   /api/v1/employees/{employee}/profile
PATCH /api/v1/employees/{employee}/profile

GET    /api/v1/employees/{employee}/emergency-contacts
POST   /api/v1/employees/{employee}/emergency-contacts
PUT    /api/v1/employees/{employee}/emergency-contacts/{emergencyContact}
PATCH  /api/v1/employees/{employee}/emergency-contacts/{emergencyContact}
DELETE /api/v1/employees/{employee}/emergency-contacts/{emergencyContact}
```

Manager and Employee roles cannot use administrative profile routes.

## Profile Response

The profile endpoint returns:

```json
{
  "employee": {},
  "profile": {},
  "emergency_contacts": [],
  "completion": {
    "percentage": 0,
    "completed_fields": [],
    "total_fields": 12,
    "missing_fields": []
  }
}
```

Completion tracks core personal fields, key extended profile fields, and the presence of a primary emergency contact.

## Validation

- Birth date must be before today.
- Gender: `male` or `female`.
- Marital status: `single`, `married`, `divorced`, or `widowed`.
- Blood type supports ABO and Rh variants.
- Personal identifiers are unique when populated.
- Emergency contact name, relationship, and phone are required.
- Maximum five active emergency contacts per Employee.

## Automated Coverage

```text
tests/Feature/EmployeeProfileApiTest.php
tests/Feature/EmergencyContactApiTest.php
```

Coverage includes:

- Admin and HR Employee profile access.
- Employee self-service access.
- Administrative route restrictions.
- Profile normalization and completion summary.
- Duplicate identifier validation.
- Primary contact replacement and promotion.
- Cross-Employee ownership protection.
- Maximum contact limit.
- Missing Employee handling.
