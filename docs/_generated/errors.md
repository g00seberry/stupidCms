# Error Codes (RFC7807)

> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:errors` to update.

_Last generated: 2025-11-08 13:52:37_

stupidCms API follows [RFC7807 Problem Details](https://tools.ietf.org/html/rfc7807) for error responses.

## Error Response Format

```json
{
  "type": "https://api.stupidcms.local/errors/validation",
  "title": "Validation Error",
  "status": 422,
  "detail": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message"]
  }
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
    "type": "https://api.stupidcms.local/errors/bad-request",
    "title": "Bad Request",
    "status": 400,
    "detail": "Invalid JSON syntax"
}
```

### 401 Unauthorized

**Code**: `UNAUTHORIZED`

**Description**: Authentication failed or was not provided.

**Example Response**:

```json
{
    "type": "https://api.stupidcms.local/errors/unauthorized",
    "title": "Unauthorized",
    "status": 401,
    "detail": "Invalid or expired token"
}
```

### 403 Forbidden

**Code**: `FORBIDDEN`

**Description**: You don't have permission to access this resource.

**Example Response**:

```json
{
    "type": "https://api.stupidcms.local/errors/forbidden",
    "title": "Forbidden",
    "status": 403,
    "detail": "Insufficient permissions to update this entry"
}
```

### 404 Not Found

**Code**: `NOT_FOUND`

**Description**: The requested resource was not found.

**Example Response**:

```json
{
    "type": "https://api.stupidcms.local/errors/not-found",
    "title": "Not Found",
    "status": 404,
    "detail": "Entry with slug \"non-existent\" not found"
}
```

### 422 Validation Error

**Code**: `VALIDATION_ERROR`

**Description**: The request data failed validation.

**Example Response**:

```json
{
    "type": "https://api.stupidcms.local/errors/validation",
    "title": "Validation Error",
    "status": 422,
    "detail": "The given data was invalid.",
    "errors": {
        "title": [
            "The title field is required."
        ],
        "slug": [
            "The slug has already been taken."
        ]
    }
}
```

### 429 Too Many Requests

**Code**: `RATE_LIMIT_EXCEEDED`

**Description**: Rate limit exceeded. Please try again later.

**Example Response**:

```json
{
    "type": "https://api.stupidcms.local/errors/rate-limit",
    "title": "Too Many Requests",
    "status": 429,
    "detail": "Rate limit of 60 requests per minute exceeded",
    "retry_after": 60
}
```

### 500 Internal Server Error

**Code**: `INTERNAL_SERVER_ERROR`

**Description**: An unexpected error occurred on the server.

**Example Response**:

```json
{
    "type": "https://api.stupidcms.local/errors/internal",
    "title": "Internal Server Error",
    "status": 500,
    "detail": "An unexpected error occurred. Please try again later."
}
```

