# Error Codes (RFC7807)

> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:errors` to update.

_Last generated: 2025-11-11 20:19:01_

stupidCms API follows [RFC7807 Problem Details](https://tools.ietf.org/html/rfc7807) for error responses.

## Error Response Format

```json
{
  "type": "https://stupidcms.dev/problems/validation-error",
  "title": "Validation Error",
  "status": 422,
  "code": "VALIDATION_ERROR",
  "detail": "The given data was invalid.",
  "meta": {
    "request_id": "aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee",
    "errors": {
      "field_name": ["Error message"]
    }
  },
  "trace_id": "00-aaaaaaaa4ccc8dddeeeeeeeeeeee-aaaaaaaa4ccc8ddd-01"
}
```

## Standard Error Codes

| Status | Code | Title | Description |
|--------|------|-------|-------------|
| 400 | `BAD_REQUEST` | Bad Request | The request could not be understood or was missing required parameters. |
| 401 | `UNAUTHORIZED` | Unauthorized | Authentication failed or was not provided. |
| 403 | `FORBIDDEN` | Forbidden | You don't have permission to access this resource. |
| 404 | `NOT_FOUND` | Not Found | The requested resource was not found. |
| 422 | `VALIDATION_ERROR` | Validation Error | The request data failed validation. |
| 429 | `RATE_LIMIT_EXCEEDED` | Too Many Requests | Rate limit exceeded. Please try again later. |
| 500 | `INTERNAL_SERVER_ERROR` | Internal Server Error | An unexpected error occurred on the server. |

## Examples

### 400 Bad Request

**Code**: `BAD_REQUEST`

**Description**: The request could not be understood or was missing required parameters.

**Example Response**:

```json
{
    "type": "https://stupidcms.dev/problems/bad-request",
    "title": "Bad Request",
    "status": 400,
    "code": "BAD_REQUEST",
    "detail": "Invalid JSON syntax",
    "meta": {
        "request_id": "11111111-1111-4111-8111-111111111111",
        "field": "payload"
    },
    "trace_id": "00-11111111111141118111111111111111-1111111111114111-01"
}
```

### 401 Unauthorized

**Code**: `UNAUTHORIZED`

**Description**: Authentication failed or was not provided.

**Example Response**:

```json
{
    "type": "https://stupidcms.dev/problems/unauthorized",
    "title": "Unauthorized",
    "status": 401,
    "code": "UNAUTHORIZED",
    "detail": "Invalid or expired token",
    "meta": {
        "request_id": "22222222-2222-4222-8222-222222222222",
        "reason": "invalid_token"
    },
    "trace_id": "00-22222222222242228222222222222222-2222222222224222-01"
}
```

### 403 Forbidden

**Code**: `FORBIDDEN`

**Description**: You don't have permission to access this resource.

**Example Response**:

```json
{
    "type": "https://stupidcms.dev/problems/forbidden",
    "title": "Forbidden",
    "status": 403,
    "code": "FORBIDDEN",
    "detail": "Insufficient permissions to update this entry",
    "meta": {
        "request_id": "33333333-3333-4333-8333-333333333333",
        "permission": "entries.update"
    },
    "trace_id": "00-33333333333343338333333333333333-3333333333334333-01"
}
```

### 404 Not Found

**Code**: `NOT_FOUND`

**Description**: The requested resource was not found.

**Example Response**:

```json
{
    "type": "https://stupidcms.dev/problems/not-found",
    "title": "Not Found",
    "status": 404,
    "code": "NOT_FOUND",
    "detail": "Entry with slug \"non-existent\" not found",
    "meta": {
        "request_id": "44444444-4444-4444-8444-444444444444",
        "entry_slug": "non-existent"
    },
    "trace_id": "00-44444444444444448444444444444444-4444444444444444-01"
}
```

### 422 Validation Error

**Code**: `VALIDATION_ERROR`

**Description**: The request data failed validation.

**Example Response**:

```json
{
    "type": "https://stupidcms.dev/problems/validation-error",
    "title": "Validation Error",
    "status": 422,
    "code": "VALIDATION_ERROR",
    "detail": "The given data was invalid.",
    "meta": {
        "request_id": "55555555-5555-4555-8555-555555555555",
        "errors": {
            "title": [
                "The title field is required."
            ],
            "slug": [
                "The slug has already been taken."
            ]
        }
    },
    "trace_id": "00-55555555555545558555555555555555-5555555555554555-01"
}
```

### 429 Too Many Requests

**Code**: `RATE_LIMIT_EXCEEDED`

**Description**: Rate limit exceeded. Please try again later.

**Example Response**:

```json
{
    "type": "https://stupidcms.dev/problems/rate-limit-exceeded",
    "title": "Too Many Requests",
    "status": 429,
    "code": "RATE_LIMIT_EXCEEDED",
    "detail": "Rate limit of 60 requests per minute exceeded",
    "meta": {
        "request_id": "66666666-6666-4666-8666-666666666666",
        "retry_after": 60
    },
    "trace_id": "00-66666666666646668666666666666666-6666666666664666-01"
}
```

### 500 Internal Server Error

**Code**: `INTERNAL_SERVER_ERROR`

**Description**: An unexpected error occurred on the server.

**Example Response**:

```json
{
    "type": "https://stupidcms.dev/problems/internal-server-error",
    "title": "Internal Server Error",
    "status": 500,
    "code": "INTERNAL_SERVER_ERROR",
    "detail": "An unexpected error occurred. Please try again later.",
    "meta": {
        "request_id": "77777777-7777-4777-8777-777777777777"
    },
    "trace_id": "00-77777777777747778777777777777777-7777777777774777-01"
}
```

