# WPMatch Profile Fields Management - API Specification

## Overview

This document provides comprehensive API specifications for the WPMatch Profile Fields Management system. The API follows WordPress conventions using AJAX endpoints for admin functionality and integrates with WordPress REST API patterns for potential future extensibility.

## API Architecture

### Endpoint Structure
- **Admin AJAX Endpoints**: `wp-admin/admin-ajax.php?action=wpmatch_[action_name]`
- **REST API Endpoints**: `/wp-json/wpmatch/v1/[resource]` (future implementation)
- **Authentication**: WordPress nonce system with capability-based authorization
- **Response Format**: JSON with consistent error handling and status codes

### Common Response Format
```json
{
    "success": true|false,
    "data": {...},
    "message": "Human readable message",
    "errors": [...],
    "meta": {
        "timestamp": "2024-01-01T00:00:00Z",
        "version": "1.0.0",
        "request_id": "uuid"
    }
}
```

## Profile Fields Management API

### 1. Create Profile Field

**Endpoint**: `POST wp-admin/admin-ajax.php`
**Action**: `wpmatch_create_field`
**Capability**: `manage_profile_fields`

#### Request Parameters
```json
{
    "action": "wpmatch_create_field",
    "nonce": "wp_nonce_value",
    "field_data": {
        "field_name": "favorite_color",
        "field_label": "Favorite Color",
        "field_type": "select",
        "field_description": "Choose your favorite color",
        "placeholder_text": "Select a color...",
        "help_text": "This helps us match you with compatible people",
        "field_options": {
            "choices": ["Red", "Blue", "Green", "Yellow", "Purple"],
            "allow_other": false,
            "multiple": false
        },
        "validation_rules": {
            "required": true,
            "custom_validation": null
        },
        "is_required": true,
        "is_searchable": true,
        "is_public": true,
        "field_group": "personal",
        "field_order": 10,
        "field_width": "half",
        "field_class": "color-selector",
        "default_value": "",
        "conditional_logic": {
            "show_if": {
                "field": "relationship_status",
                "operator": "equals",
                "value": "single"
            }
        }
    }
}
```

#### Success Response
```json
{
    "success": true,
    "data": {
        "field_id": 123,
        "field_name": "favorite_color",
        "field_label": "Favorite Color",
        "field_type": "select",
        "status": "active",
        "created_at": "2024-01-01T12:00:00Z",
        "cache_cleared": true
    },
    "message": "Field created successfully",
    "meta": {
        "timestamp": "2024-01-01T12:00:00Z",
        "version": "1.0.0",
        "request_id": "req_123456"
    }
}
```

#### Error Response
```json
{
    "success": false,
    "data": null,
    "message": "Field creation failed",
    "errors": [
        {
            "code": "DUPLICATE_FIELD_NAME",
            "message": "A field with this name already exists",
            "field": "field_name"
        },
        {
            "code": "INVALID_FIELD_TYPE",
            "message": "Unsupported field type: custom_type",
            "field": "field_type"
        }
    ]
}
```

### 2. Update Profile Field

**Endpoint**: `POST wp-admin/admin-ajax.php`
**Action**: `wpmatch_update_field`
**Capability**: `manage_profile_fields`

#### Request Parameters
```json
{
    "action": "wpmatch_update_field",
    "nonce": "wp_nonce_value",
    "field_id": 123,
    "field_data": {
        "field_label": "Updated Label",
        "field_description": "Updated description",
        "is_required": false,
        "field_options": {
            "choices": ["Red", "Blue", "Green", "Yellow", "Purple", "Orange"],
            "allow_other": true,
            "multiple": false
        }
    }
}
```

#### Success Response
```json
{
    "success": true,
    "data": {
        "field_id": 123,
        "updated_fields": ["field_label", "field_description", "is_required", "field_options"],
        "migration_required": false,
        "cache_cleared": true,
        "updated_at": "2024-01-01T12:30:00Z"
    },
    "message": "Field updated successfully"
}
```

### 3. Delete Profile Field

**Endpoint**: `POST wp-admin/admin-ajax.php`
**Action**: `wpmatch_delete_field`
**Capability**: `manage_profile_fields`

#### Request Parameters
```json
{
    "action": "wpmatch_delete_field",
    "nonce": "wp_nonce_value",
    "field_id": 123,
    "force_delete": false,
    "backup_data": true
}
```

#### Success Response
```json
{
    "success": true,
    "data": {
        "field_id": 123,
        "status": "deprecated",
        "user_data_count": 1205,
        "backup_created": true,
        "backup_file": "field_123_backup_20240101.json",
        "deletion_scheduled": "2024-02-01T00:00:00Z"
    },
    "message": "Field marked for deletion. User data preserved."
}
```

### 4. Get Profile Fields

**Endpoint**: `GET wp-admin/admin-ajax.php`
**Action**: `wpmatch_get_fields`
**Capability**: `manage_profile_fields`

#### Request Parameters
```json
{
    "action": "wpmatch_get_fields",
    "nonce": "wp_nonce_value",
    "filters": {
        "status": "active",
        "field_type": "select",
        "field_group": "personal",
        "is_searchable": true
    },
    "sort": {
        "field": "field_order",
        "direction": "asc"
    },
    "pagination": {
        "page": 1,
        "per_page": 20
    }
}
```

#### Success Response
```json
{
    "success": true,
    "data": {
        "fields": [
            {
                "id": 123,
                "field_name": "favorite_color",
                "field_label": "Favorite Color",
                "field_type": "select",
                "field_description": "Choose your favorite color",
                "field_options": {
                    "choices": ["Red", "Blue", "Green", "Yellow", "Purple"],
                    "allow_other": false,
                    "multiple": false
                },
                "is_required": true,
                "is_searchable": true,
                "is_public": true,
                "field_group": "personal",
                "field_order": 10,
                "status": "active",
                "usage_stats": {
                    "total_responses": 1205,
                    "completion_rate": 85.4,
                    "most_common_value": "Blue"
                },
                "created_at": "2024-01-01T12:00:00Z",
                "updated_at": "2024-01-01T12:30:00Z"
            }
        ],
        "pagination": {
            "current_page": 1,
            "per_page": 20,
            "total_items": 45,
            "total_pages": 3,
            "has_more": true
        }
    },
    "message": "Fields retrieved successfully"
}
```

### 5. Reorder Profile Fields

**Endpoint**: `POST wp-admin/admin-ajax.php`
**Action**: `wpmatch_reorder_fields`
**Capability**: `manage_profile_fields`

#### Request Parameters
```json
{
    "action": "wpmatch_reorder_fields",
    "nonce": "wp_nonce_value",
    "field_orders": [
        {"field_id": 123, "order": 1, "group": "personal"},
        {"field_id": 124, "order": 2, "group": "personal"},
        {"field_id": 125, "order": 3, "group": "lifestyle"}
    ]
}
```

#### Success Response
```json
{
    "success": true,
    "data": {
        "updated_fields": 3,
        "group_changes": 1,
        "cache_cleared": true
    },
    "message": "Field order updated successfully"
}
```

## Field Groups Management API

### 1. Create Field Group

**Endpoint**: `POST wp-admin/admin-ajax.php`
**Action**: `wpmatch_create_field_group`
**Capability**: `manage_profile_fields`

#### Request Parameters
```json
{
    "action": "wpmatch_create_field_group",
    "nonce": "wp_nonce_value",
    "group_data": {
        "group_name": "lifestyle",
        "group_label": "Lifestyle Preferences",
        "group_description": "Fields related to lifestyle and daily habits",
        "group_icon": "dashicons-lifestyle",
        "group_order": 20,
        "is_active": true
    }
}
```

#### Success Response
```json
{
    "success": true,
    "data": {
        "group_id": 5,
        "group_name": "lifestyle",
        "group_label": "Lifestyle Preferences",
        "created_at": "2024-01-01T13:00:00Z"
    },
    "message": "Field group created successfully"
}
```

### 2. Update Field Group

**Endpoint**: `POST wp-admin/admin-ajax.php`
**Action**: `wpmatch_update_field_group`
**Capability**: `manage_profile_fields`

#### Request Parameters
```json
{
    "action": "wpmatch_update_field_group",
    "nonce": "wp_nonce_value",
    "group_id": 5,
    "group_data": {
        "group_label": "Updated Lifestyle Preferences",
        "group_description": "Updated description for lifestyle fields",
        "group_order": 25
    }
}
```

### 3. Get Field Groups

**Endpoint**: `GET wp-admin/admin-ajax.php`
**Action**: `wpmatch_get_field_groups`
**Capability**: `manage_profile_fields`

#### Success Response
```json
{
    "success": true,
    "data": {
        "groups": [
            {
                "id": 1,
                "group_name": "basic",
                "group_label": "Basic Information",
                "group_description": "Essential profile information",
                "group_icon": "dashicons-admin-users",
                "group_order": 10,
                "is_active": true,
                "field_count": 8,
                "created_at": "2024-01-01T10:00:00Z"
            },
            {
                "id": 5,
                "group_name": "lifestyle",
                "group_label": "Lifestyle Preferences",
                "group_description": "Fields related to lifestyle and daily habits",
                "group_icon": "dashicons-lifestyle",
                "group_order": 20,
                "is_active": true,
                "field_count": 12,
                "created_at": "2024-01-01T13:00:00Z"
            }
        ]
    },
    "message": "Field groups retrieved successfully"
}
```

## Profile Values Management API

### 1. Get User Profile Values

**Endpoint**: `GET wp-admin/admin-ajax.php`
**Action**: `wpmatch_get_user_profile_values`
**Capability**: `edit_users` or `view_profile_data`

#### Request Parameters
```json
{
    "action": "wpmatch_get_user_profile_values",
    "nonce": "wp_nonce_value",
    "user_id": 456,
    "field_groups": ["basic", "lifestyle"],
    "include_private": false
}
```

#### Success Response
```json
{
    "success": true,
    "data": {
        "user_id": 456,
        "profile_values": {
            "favorite_color": {
                "field_id": 123,
                "field_type": "select",
                "field_label": "Favorite Color",
                "value": "Blue",
                "privacy": "public",
                "is_verified": false,
                "updated_at": "2024-01-01T14:00:00Z"
            },
            "height": {
                "field_id": 124,
                "field_type": "number",
                "field_label": "Height (cm)",
                "value": "175",
                "privacy": "members_only",
                "is_verified": true,
                "updated_at": "2024-01-01T12:00:00Z"
            }
        },
        "profile_completion": {
            "percentage": 85.7,
            "completed_fields": 12,
            "total_fields": 14,
            "missing_required": 1
        }
    },
    "message": "Profile values retrieved successfully"
}
```

### 2. Update User Profile Values

**Endpoint**: `POST wp-admin/admin-ajax.php`
**Action**: `wpmatch_update_user_profile_values`
**Capability**: `edit_users` or own profile

#### Request Parameters
```json
{
    "action": "wpmatch_update_user_profile_values",
    "nonce": "wp_nonce_value",
    "user_id": 456,
    "field_values": {
        "favorite_color": {
            "value": "Green",
            "privacy": "public"
        },
        "height": {
            "value": "177",
            "privacy": "members_only"
        }
    }
}
```

#### Success Response
```json
{
    "success": true,
    "data": {
        "user_id": 456,
        "updated_fields": 2,
        "validation_errors": [],
        "profile_completion": {
            "previous": 85.7,
            "current": 92.9,
            "change": 7.2
        },
        "updated_at": "2024-01-01T15:00:00Z"
    },
    "message": "Profile values updated successfully"
}
```

## Field Import/Export API

### 1. Export Field Configuration

**Endpoint**: `POST wp-admin/admin-ajax.php`
**Action**: `wpmatch_export_fields`
**Capability**: `export_profile_data`

#### Request Parameters
```json
{
    "action": "wpmatch_export_fields",
    "nonce": "wp_nonce_value",
    "export_options": {
        "include_groups": ["basic", "lifestyle", "preferences"],
        "include_inactive": false,
        "format": "json",
        "include_user_data": false,
        "anonymize": true
    }
}
```

#### Success Response
```json
{
    "success": true,
    "data": {
        "export_file": "wpmatch_fields_export_20240101.json",
        "download_url": "https://example.com/wp-content/uploads/wpmatch/exports/wpmatch_fields_export_20240101.json",
        "file_size": "125KB",
        "export_stats": {
            "total_fields": 25,
            "total_groups": 4,
            "export_date": "2024-01-01T16:00:00Z"
        },
        "expires_at": "2024-01-08T16:00:00Z"
    },
    "message": "Field configuration exported successfully"
}
```

### 2. Import Field Configuration

**Endpoint**: `POST wp-admin/admin-ajax.php`
**Action**: `wpmatch_import_fields`
**Capability**: `import_profile_data`

#### Request Parameters
```json
{
    "action": "wpmatch_import_fields",
    "nonce": "wp_nonce_value",
    "import_file": "base64_encoded_file_content",
    "import_options": {
        "merge_mode": "update_existing",
        "create_missing_groups": true,
        "backup_existing": true,
        "validate_only": false
    }
}
```

#### Success Response
```json
{
    "success": true,
    "data": {
        "import_stats": {
            "fields_imported": 15,
            "fields_updated": 8,
            "fields_skipped": 2,
            "groups_created": 2,
            "groups_updated": 1
        },
        "backup_file": "wpmatch_backup_20240101_160500.json",
        "validation_warnings": [
            {
                "field": "custom_field_1",
                "warning": "Field type 'custom_type' not supported, converted to 'text'"
            }
        ],
        "imported_at": "2024-01-01T16:05:00Z"
    },
    "message": "Field configuration imported successfully"
}
```

## Field Validation API

### 1. Validate Field Configuration

**Endpoint**: `POST wp-admin/admin-ajax.php`
**Action**: `wpmatch_validate_field`
**Capability**: `manage_profile_fields`

#### Request Parameters
```json
{
    "action": "wpmatch_validate_field",
    "nonce": "wp_nonce_value",
    "field_data": {
        "field_name": "test_field",
        "field_type": "select",
        "field_options": {
            "choices": ["Option 1", "Option 2"]
        },
        "validation_rules": {
            "required": true
        }
    }
}
```

#### Success Response
```json
{
    "success": true,
    "data": {
        "is_valid": true,
        "validation_results": {
            "field_name": "valid",
            "field_type": "valid",
            "field_options": "valid",
            "validation_rules": "valid"
        },
        "suggestions": [
            "Consider adding help text for better user experience"
        ]
    },
    "message": "Field configuration is valid"
}
```

### 2. Validate Field Value

**Endpoint**: `POST wp-admin/admin-ajax.php`
**Action**: `wpmatch_validate_field_value`
**Capability**: Public (for frontend forms)

#### Request Parameters
```json
{
    "action": "wpmatch_validate_field_value",
    "nonce": "wp_nonce_value",
    "field_id": 123,
    "field_value": "Blue",
    "user_id": 456
}
```

#### Success Response
```json
{
    "success": true,
    "data": {
        "is_valid": true,
        "sanitized_value": "Blue",
        "validation_messages": [],
        "field_requirements": {
            "required": true,
            "min_length": null,
            "max_length": null,
            "pattern": null
        }
    },
    "message": "Field value is valid"
}
```

## Search and Analytics API

### 1. Search Profile Fields

**Endpoint**: `GET wp-admin/admin-ajax.php`
**Action**: `wpmatch_search_profile_fields`
**Capability**: `manage_profile_fields`

#### Request Parameters
```json
{
    "action": "wpmatch_search_profile_fields",
    "nonce": "wp_nonce_value",
    "search_query": "color",
    "filters": {
        "field_type": ["select", "text"],
        "is_searchable": true,
        "status": "active"
    },
    "sort": "relevance"
}
```

#### Success Response
```json
{
    "success": true,
    "data": {
        "search_results": [
            {
                "field_id": 123,
                "field_name": "favorite_color",
                "field_label": "Favorite Color",
                "field_type": "select",
                "relevance_score": 0.95,
                "match_reasons": ["label_match", "name_match"]
            },
            {
                "field_id": 156,
                "field_name": "eye_color",
                "field_label": "Eye Color",
                "field_type": "select",
                "relevance_score": 0.87,
                "match_reasons": ["label_match"]
            }
        ],
        "total_results": 2,
        "search_time": "0.045s"
    },
    "message": "Search completed successfully"
}
```

### 2. Get Field Analytics

**Endpoint**: `GET wp-admin/admin-ajax.php`
**Action**: `wpmatch_get_field_analytics`
**Capability**: `view_field_analytics`

#### Request Parameters
```json
{
    "action": "wpmatch_get_field_analytics",
    "nonce": "wp_nonce_value",
    "field_id": 123,
    "date_range": {
        "start": "2024-01-01",
        "end": "2024-01-31"
    },
    "metrics": ["completion_rate", "value_distribution", "search_usage"]
}
```

#### Success Response
```json
{
    "success": true,
    "data": {
        "field_id": 123,
        "field_name": "favorite_color",
        "analytics": {
            "completion_rate": {
                "current_period": 85.4,
                "previous_period": 82.1,
                "change": 3.3,
                "trend": "increasing"
            },
            "value_distribution": {
                "Blue": 35.2,
                "Green": 22.8,
                "Red": 18.5,
                "Purple": 12.3,
                "Yellow": 8.7,
                "Other": 2.5
            },
            "search_usage": {
                "total_searches": 1250,
                "searches_with_filter": 890,
                "filter_usage_rate": 71.2
            },
            "user_engagement": {
                "average_time_to_complete": "45s",
                "abandonment_rate": 5.2,
                "revision_count": 245
            }
        },
        "generated_at": "2024-01-01T17:00:00Z"
    },
    "message": "Field analytics retrieved successfully"
}
```

## Error Codes and Handling

### Common Error Codes

| Code | Description | HTTP Status | Resolution |
|------|-------------|-------------|------------|
| `INVALID_NONCE` | Security nonce verification failed | 403 | Refresh page and try again |
| `INSUFFICIENT_PERMISSIONS` | User lacks required capability | 403 | Contact administrator |
| `FIELD_NOT_FOUND` | Requested field does not exist | 404 | Verify field ID |
| `DUPLICATE_FIELD_NAME` | Field name already exists | 409 | Choose unique field name |
| `INVALID_FIELD_TYPE` | Unsupported field type | 400 | Use supported field type |
| `VALIDATION_FAILED` | Field data validation failed | 422 | Fix validation errors |
| `DATABASE_ERROR` | Database operation failed | 500 | Contact administrator |
| `CACHE_ERROR` | Cache operation failed | 500 | Try again later |
| `EXPORT_FAILED` | Export operation failed | 500 | Check permissions and disk space |
| `IMPORT_FAILED` | Import operation failed | 400 | Verify file format |

### Error Response Format
```json
{
    "success": false,
    "data": null,
    "message": "Operation failed",
    "errors": [
        {
            "code": "VALIDATION_FAILED",
            "message": "Field name is required",
            "field": "field_name",
            "details": {
                "required": true,
                "provided": null
            }
        }
    ],
    "meta": {
        "timestamp": "2024-01-01T12:00:00Z",
        "version": "1.0.0",
        "request_id": "req_123456"
    }
}
```

## Rate Limiting and Security

### Rate Limiting Rules
- **Admin Operations**: 100 requests per minute per user
- **Validation Requests**: 200 requests per minute per user
- **Export Operations**: 5 requests per hour per user
- **Import Operations**: 3 requests per hour per user

### Security Headers
```http
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
```

### Authentication Requirements
- Valid WordPress session
- Appropriate user capabilities
- Valid nonce for state-changing operations
- IP-based rate limiting for admin operations

## Webhooks and Events

### Available Webhooks
- `wpmatch.field.created`
- `wpmatch.field.updated`
- `wpmatch.field.deleted`
- `wpmatch.field.status_changed`
- `wpmatch.profile.completed`
- `wpmatch.bulk_import.completed`

### Webhook Payload Example
```json
{
    "event": "wpmatch.field.created",
    "timestamp": "2024-01-01T12:00:00Z",
    "data": {
        "field_id": 123,
        "field_name": "favorite_color",
        "field_type": "select",
        "created_by": 1,
        "site_url": "https://example.com"
    },
    "signature": "sha256_signature_for_verification"
}
```

## Future API Enhancements

### Planned REST API Endpoints
- `GET /wp-json/wpmatch/v1/fields` - List fields
- `POST /wp-json/wpmatch/v1/fields` - Create field
- `PUT /wp-json/wpmatch/v1/fields/{id}` - Update field
- `DELETE /wp-json/wpmatch/v1/fields/{id}` - Delete field
- `GET /wp-json/wpmatch/v1/users/{id}/profile` - Get user profile
- `PUT /wp-json/wpmatch/v1/users/{id}/profile` - Update user profile

### GraphQL Integration
Future consideration for GraphQL endpoint to support complex queries and real-time subscriptions for profile field management.

### OpenAPI Documentation
Complete OpenAPI 3.0 specification will be generated for REST API endpoints when implemented.

This API specification provides a comprehensive foundation for all profile field management operations while maintaining security, performance, and extensibility requirements.