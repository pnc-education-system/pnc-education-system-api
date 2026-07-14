# Postman Collection for Batch Management API

## Base URL
```
http://localhost:8000/api/v1
```

## Authentication
All endpoints require JWT authentication. First, login to get a token:

### 1. Login (Get Token)
**POST** `/auth/login`

```json
{
  "email": "admin@pnc.edu.kh",
  "password": "your_password"
}
```

**Response:**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

**Headers for all subsequent requests:**
```
Authorization: Bearer {access_token}
Content-Type: application/json
Accept: application/json
```

---

## Batch Management Endpoints

### 2. Get All Batches
**GET** `/selection-batches`

**Query Parameters (optional):**
- `year` - Filter by year (e.g., `2024`)

**Response:**
```json
{
  "status": "success",
  "message": "Selection batches retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Batch 2024",
      "year": "2024",
      "created_by": 1,
      "is_active": 1,
      "created_at": "2026-07-13T07:40:29.000000Z",
      "updated_at": "2026-07-14T02:01:40.000000Z",
      "students_count": 5,
      "creator": {
        "id": 1,
        "name": "System Administrator",
        "email": "admin@pnc.edu.kh"
      }
    }
  ]
}
```

---

### 3. Create New Batch
**POST** `/selection-batches`

**Request Body:**
```json
{
  "name": "Batch 2026",
  "year": 2026
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Selection batch created successfully",
  "data": {
    "id": 4,
    "name": "Batch 2026",
    "year": 2026,
    "created_by": 1,
    "is_active": 1,
    "created_at": "2026-07-14T02:04:32.000000Z",
    "updated_at": "2026-07-14T02:04:32.000000Z",
    "students_count": 0,
    "creator": {
      "id": 1,
      "name": "System Administrator",
      "email": "admin@pnc.edu.kh"
    }
  }
}
```

---

### 4. Get Single Batch
**GET** `/selection-batches/{id}`

**Example:** `/selection-batches/1`

**Response:**
```json
{
  "status": "success",
  "message": "Selection batch retrieved successfully",
  "data": {
    "id": 1,
    "name": "Batch 2024",
    "year": "2024",
    "created_by": 1,
    "is_active": 1,
    "created_at": "2026-07-13T07:40:29.000000Z",
    "updated_at": "2026-07-14T02:01:40.000000Z",
    "students_count": 5,
    "creator": {
      "id": 1,
      "name": "System Administrator",
      "email": "admin@pnc.edu.kh"
    }
  }
}
```

---

### 5. Update Batch
**PUT** `/selection-batches/{id}`

**Example:** `/selection-batches/1`

**Request Body:**
```json
{
  "name": "Batch 2024 Updated",
  "year": 2024
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Selection batch updated successfully",
  "data": {
    "id": 1,
    "name": "Batch 2024 Updated",
    "year": 2024,
    "created_by": 1,
    "is_active": 1,
    "created_at": "2026-07-13T07:40:29.000000Z",
    "updated_at": "2026-07-14T02:04:33.000000Z",
    "students_count": 5,
    "creator": {
      "id": 1,
      "name": "System Administrator",
      "email": "admin@pnc.edu.kh"
    }
  }
}
```

---

### 6. Delete Batch
**DELETE** `/selection-batches/{id}`

**Example:** `/selection-batches/4`

**Response:**
```json
{
  "status": "success",
  "message": "Selection batch deleted successfully"
}
```

**Error Response (if batch has students):**
```json
{
  "status": "error",
  "message": "Cannot delete selection batch with associated students",
  "code": 400
}
```

---

## Validation Rules

### Create/Update Batch
- `name` - Required, string, max 255 characters
- `year` - Required, integer, 4 digits (e.g., 2024, 2025)

---

## Error Responses

### 403 Forbidden
```json
{
  "error": {
    "code": 403,
    "message": "Missing permission: batches.manage"
  }
}
```

### 404 Not Found
```json
{
  "status": "error",
  "message": "Selection batch not found",
  "code": 404
}
```

### 422 Validation Error
```json
{
  "status": "error",
  "message": "Validation failed",
  "code": 422,
  "errors": {
    "name": ["The name field is required."],
    "year": ["The year field is required."]
  }
}
```

---

## Postman Environment Variables

Create an environment with these variables:

| Variable | Value |
|----------|-------|
| `base_url` | `http://localhost:8000/api/v1` |
| `token` | `{{access_token}}` (from login response) |

Use variables in requests:
```
{{base_url}}/selection-batches
Authorization: Bearer {{token}}
```
