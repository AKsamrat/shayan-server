# Profile Management API Endpoints

This document outlines all the new API endpoints for managing admin, customer, and staff profiles with image upload support.

## Base URL
All endpoints are relative to your API base URL (e.g., `http://yourdomain.com/api`)

---

## Customer Profile Endpoints (Authenticated as Customer)

### Get Customer Profile
- **Method:** `GET`
- **Endpoint:** `/customer/profile`
- **Description:** Retrieve the authenticated customer's profile data
- **Response:** Customer user object with role

### Update Customer Profile
- **Method:** `PUT`
- **Endpoint:** `/customer/profile`
- **Request Body:**
  ```json
  {
    "name": "string (optional)",
    "phone": "string (optional)",
    "email": "string (optional, unique)",
    "is_active": "boolean (optional)"
  }
  ```
- **Description:** Update customer profile data
- **Response:** Updated customer user object

### Upload Customer Avatar
- **Method:** `POST`
- **Endpoint:** `/customer/profile/image`
- **Request Body:** `multipart/form-data` with `avatar` field (image file)
- **File Types:** jpeg, png, jpg, gif
- **Max Size:** 2MB
- **Description:** Upload or replace customer profile image
- **Response:** Updated user object with avatar URL

### Remove Customer Avatar
- **Method:** `DELETE`
- **Endpoint:** `/customer/profile/image`
- **Description:** Remove customer profile image
- **Response:** Updated user object

---

## Admin Profile Endpoints (Authenticated as Admin)

### Get Admin Profile
- **Method:** `GET`
- **Endpoint:** `/admin/profile`
- **Description:** Retrieve the authenticated admin's profile data
- **Response:** Admin user object with role and permissions

### Update Admin Profile
- **Method:** `PUT`
- **Endpoint:** `/admin/profile`
- **Request Body:**
  ```json
  {
    "name": "string (optional)",
    "phone": "string (optional)",
    "email": "string (optional, unique)",
    "is_active": "boolean (optional)"
  }
  ```
- **Description:** Update admin profile data
- **Response:** Updated admin user object with role and permissions

### Upload Admin Avatar
- **Method:** `POST`
- **Endpoint:** `/admin/profile/image`
- **Request Body:** `multipart/form-data` with `avatar` field (image file)
- **File Types:** jpeg, png, jpg, gif
- **Max Size:** 2MB
- **Description:** Upload or replace admin profile image
- **Response:** Updated user object with avatar URL

### Remove Admin Avatar
- **Method:** `DELETE`
- **Endpoint:** `/admin/profile/image`
- **Description:** Remove admin profile image
- **Response:** Updated user object

---

## Admin: Manage Customer Profiles (Authenticated as Admin)

### Get Customer Profile by ID
- **Method:** `GET`
- **Endpoint:** `/admin/customers/{id}/profile`
- **Description:** Retrieve a specific customer's profile
- **Response:** Customer user object

### Update Customer Profile by Admin
- **Method:** `PUT`
- **Endpoint:** `/admin/customers/{id}/profile`
- **Request Body:**
  ```json
  {
    "name": "string (optional)",
    "phone": "string (optional)",
    "email": "string (optional, unique)",
    "is_active": "boolean (optional)",
    "is_verified": "boolean (optional)"
  }
  ```
- **Description:** Update customer profile data (admin only)
- **Response:** Updated customer user object

### Upload Customer Avatar by Admin
- **Method:** `POST`
- **Endpoint:** `/admin/customers/{id}/profile/image`
- **Request Body:** `multipart/form-data` with `avatar` field (image file)
- **File Types:** jpeg, png, jpg, gif
- **Max Size:** 2MB
- **Description:** Upload or replace customer profile image (admin only)
- **Response:** Updated user object with avatar URL

### Remove Customer Avatar by Admin
- **Method:** `DELETE`
- **Endpoint:** `/admin/customers/{id}/profile/image`
- **Description:** Remove customer profile image (admin only)
- **Response:** Updated user object

---

## Admin: Manage Staff Profiles (Authenticated as Admin)

### Get Staff Profile by ID
- **Method:** `GET`
- **Endpoint:** `/admin/staff/{id}/profile`
- **Description:** Retrieve a specific staff member's profile
- **Response:** Staff user object with role and permissions

### Update Staff Profile by Admin
- **Method:** `PUT`
- **Endpoint:** `/admin/staff/{id}/profile`
- **Request Body:**
  ```json
  {
    "name": "string (optional)",
    "phone": "string (optional)",
    "email": "string (optional, unique)",
    "is_active": "boolean (optional)",
    "is_verified": "boolean (optional)",
    "role_id": "integer (optional)"
  }
  ```
- **Description:** Update staff profile data (admin only)
- **Response:** Updated staff user object with role and permissions

### Upload Staff Avatar by Admin
- **Method:** `POST`
- **Endpoint:** `/admin/staff/{id}/profile/image`
- **Request Body:** `multipart/form-data` with `avatar` field (image file)
- **File Types:** jpeg, png, jpg, gif
- **Max Size:** 2MB
- **Description:** Upload or replace staff profile image (admin only)
- **Response:** Updated user object with avatar URL

### Remove Staff Avatar by Admin
- **Method:** `DELETE`
- **Endpoint:** `/admin/staff/{id}/profile/image`
- **Description:** Remove staff profile image (admin only)
- **Response:** Updated user object

---

## Implementation Details

### File Storage
- Images are stored in `storage/app/public/avatars/`
- Served via `public/storage/avatars/` (symbolic link)
- Supported formats: JPEG, PNG, JPG, GIF
- Maximum file size: 2MB (2048 KB)

### Authentication
- All endpoints require authentication via Sanctum
- Admin endpoints require `admin` or `super_admin` role
- Customer endpoints work with `customer` role

### Error Responses
- `404 Not Found`: User not found
- `422 Unprocessable Entity`: Validation errors
- `403 Forbidden`: Insufficient permissions

---

## Usage Examples

### Upload Customer Avatar (Customer)
```bash
curl -X POST \
  http://yourdomain.com/api/customer/profile/image \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: multipart/form-data' \
  -F 'avatar=@/path/to/your/photo.jpg'
```

### Update Staff Profile (Admin)
```bash
curl -X PUT \
  http://yourdomain.com/api/admin/staff/123/profile \
  -H 'Authorization: Bearer YOUR_ADMIN_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{"name": "Updated Name", "phone": "+1234567890"}'
```

### Get Customer Profile (Admin)
```bash
curl -X GET \
  http://yourdomain.com/api/admin/customers/456/profile \
  -H 'Authorization: Bearer YOUR_ADMIN_TOKEN'
```
