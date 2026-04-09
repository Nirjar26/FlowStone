# Testing Strategies and Test Cases

## Testing Strategies

### 1. Unit Testing
- **Scope**: Test individual functions, methods, or components in isolation.
- **Tools**: PHPUnit (for PHP), Jest (for React components).
- **Examples**:
  - Test utility functions in `utils.ts`.
  - Test React components like `ActivityFeed.tsx`.

### 2. Integration Testing
- **Scope**: Test interactions between modules (e.g., API endpoints and database).
- **Tools**: Postman, PHPUnit.
- **Examples**:
  - Test `dashboard.php` API with database queries.
  - Test `tasks.php` API with frontend components.

### 3. End-to-End Testing
- **Scope**: Test the entire workflow from frontend to backend.
- **Tools**: Cypress, Playwright.
- **Examples**:
  - Test user login and dashboard loading.
  - Test task creation and display.

### 4. Performance Testing
- **Scope**: Ensure the system performs well under load.
- **Tools**: Apache JMeter, k6.
- **Examples**:
  - Test `dashboard.php` API under concurrent requests.
  - Test frontend rendering time for `Dashboard.tsx`.

### 5. Regression Testing
- **Scope**: Verify that new changes do not break existing functionality.
- **Tools**: Automated test suites.
- **Examples**:
  - Re-run unit and integration tests after updates.
  - Verify Recent Activity limit after backend changes.

---

## Test Cases

### Backend Modules

| Module          | Test Case Description                                      | Expected Result                                      |
|-----------------|-----------------------------------------------------------|-----------------------------------------------------|
| `dashboard.php` | Verify API returns complete data for charts.              | API response includes all days/months with data.    |
| `tasks.php`     | Test task creation with valid data.                        | Task is created and stored in the database.         |
| `login.php`     | Test login with valid credentials.                         | User is authenticated and redirected to dashboard.  |
| `register.php`  | Test registration with missing fields.                     | API returns validation error.                       |
| `update_task.php`| Test task update with invalid task ID.                    | API returns error message.                          |

### Frontend Modules

| Component       | Test Case Description                                      | Expected Result                                      |
|-----------------|-----------------------------------------------------------|-----------------------------------------------------|
| `Dashboard.tsx` | Verify charts render with API data.                        | Charts display correct data from API.               |
| `Tasks.tsx`     | Test task list updates after creating a new task.          | Task list includes the newly created task.          |
| `ActivityFeed.tsx`| Verify Recent Activity shows only 5 rows.                | Only 5 rows are displayed in the activity feed.     |
| `Login.tsx`     | Test login form validation for empty fields.               | Validation error messages are displayed.            |
| `Profile.tsx`   | Verify profile updates are reflected immediately.          | Updated profile data is displayed.                  |

### Database

| Test Case Description                                      | Expected Result                                      |
|-----------------------------------------------------------|-----------------------------------------------------|
| Verify `users` table schema matches `database.sql`.       | Schema matches the expected structure.              |
| Test foreign key constraints in `tasks_schema.sql`.       | Constraints prevent invalid data insertion.         |
| Verify `tasks` table data after running `seed_large_data.php`.| Data matches the expected reduced dataset.         |
| Test `update_preferences_schema.php` script execution.    | Preferences schema is updated without errors.       |
| Verify `uploads/avatars` directory permissions.           | Directory allows file uploads.                      |

---

## Notes
- Ensure all test cases are automated where possible.
- Use mock data for testing APIs to avoid affecting production data.
- Regularly update test cases to reflect new features or changes.