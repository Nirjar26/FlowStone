# FlowStone Backend API Documentation

## Database Setup

Run the setup scripts in order:
```bash
cd backend
php setup.php          # Creates database and users table
php update_schema.php  # Adds user profile fields
php setup_tasks.php    # Creates tasks tables and sample data
```

## User Authentication APIs

### Login
**POST** `/login.php`
```json
{
  "email": "admin@flowstone.com",
  "password": "password123"
}
```

### Register
**POST** `/register.php`
```json
{
  "email": "user@example.com",
  "password": "yourpassword",
  "name": "User Name"
}
```

## Profile APIs

### Get Profile
**GET** `/profile.php?id={user_id}`

### Update Profile
**POST** `/update_profile.php`
```json
{
  "id": 1,
  "name": "Updated Name",
  "phone": "+1234567890",
  "bio": "Updated bio",
  "department": "Department Name"
}
```

## Tasks APIs

### Get All Tasks
**GET** `/tasks.php`

Query Parameters:
- `status` - Filter by status (pending, in-progress, review, completed)
- `priority` - Filter by priority (low, medium, high)
- `assignee_id` - Filter by assignee
- `search` - Search in title and description

### Get Task Details
**GET** `/task_detail.php?id={task_id}`

### Create Task
**POST** `/create_task.php`
```json
{
  "title": "Task Title",
  "description": "Task Description",
  "status": "pending",
  "priority": "medium",
  "assignee_id": 1,
  "deadline": "2024-12-31",
  "user_id": 1
}
```

### Update Task
**POST** `/update_task.php`
```json
{
  "id": 1,
  "title": "Updated Title",
  "status": "in-progress",
  "priority": "high"
}
```

### Delete Task
**POST** `/delete_task.php`
```json
{
  "id": 1
}
```

## Running the Server

Start the PHP development server:
```bash
cd backend
php -S localhost:8000
```

The APIs will be available at `http://localhost:8000/`

## Seed Large Weekly Data

For demo/stress testing with a lot of realistic data for the current week, run:

```bash
cd backend
php seed_large_data.php
```

Default seed volume:
- users: 80
- tasks: 900
- approvals: 300
- resources: 180
- notifications: 1100
- activities: 1400
- comments: 1200

Use custom counts:

```bash
php seed_large_data.php --users=150 --tasks=2000 --approvals=600 --resources=300 --notifications=3000 --activities=4000 --comments=3500
```

If you want to clear existing data first (dangerous, for local dev only):

```bash
php seed_large_data.php --truncate=1
```

## Default Credentials

- **Email**: admin@flowstone.com
- **Password**: password123

## Database Schema

### users
- id, email, password, name, phone, role, department, bio, avatar, created_at

### tasks
- id, title, description, status, priority, assignee_id, created_by, deadline, created_at, updated_at

### task_comments
- id, task_id, user_id, content, created_at

### task_attachments
- id, task_id, filename, file_type, file_size, file_path, uploaded_by, created_at
