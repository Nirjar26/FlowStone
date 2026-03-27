# 🚀 FlowStone (SmartFlow)

> Enterprise-grade team operations platform for managing tasks, approvals, resources, and workflows with a modern and scalable backend.

---

## 📌 Overview

FlowStone is a full-stack work management system designed to streamline organizational workflows.

It provides:

* Task tracking
* Approval pipelines
* Resource allocation
* Analytics
* Role-based administration

All in a clean, responsive interface.

---

## ✨ Key Features

### 🔐 Authentication & Authorization

* Secure login system (email/password)
* Role-based access (Admin / User)
* Protected routes and session persistence

### 📊 Dashboard

* Real-time KPI metrics
* Task trends & resource usage charts
* Activity feed

### ✅ Task Management

* Create, update, delete tasks
* Filter, search, and status tracking
* Task detail modal view
* Priority & deadline handling

### 📝 Approval Workflow

* Approve / reject system
* Status categorization (Pending, Approved, Rejected)
* Confirmation dialogs

### 🧱 Resource Management

* Add and manage resources
* Status tracking and categorization
* Search and filtering

### 📈 Reports & Analytics

* Visual charts (Bar, Pie, Area)
* KPI summaries
* CSV export

### 🔔 Notifications System

* Real-time notifications
* Mark as read / delete
* Unread filtering

### ⚙️ Settings & Profile

* Profile editing
* Password updates
* Avatar upload
* Theme switching (Light/Dark)
* Preferences toggles

### 🛡️ Admin Panel

* Restricted access
* System-wide insights
* User and resource monitoring

---

## 🧰 Tech Stack

### Frontend

* React 18 + TypeScript
* Vite
* Tailwind CSS + shadcn/ui
* Framer Motion
* Recharts
* React Query
* React Hook Form + Zod

### Backend

* PHP (REST APIs)
* MySQL (PDO)

---

## 🏗️ Architecture

Frontend (React)
↓
Backend (PHP REST)
↓
MySQL Database

---

## ⚙️ Installation & Setup

### 1. Clone repository

git clone <your-repo-url>
cd FlowStone-1

### 2. Install dependencies

npm install
npm run dev

### 3. Start backend

cd backend
php -S localhost:8000

### 4. Setup database

php setup.php
php update_schema.php
php setup_tasks.php

### 5. Open app

http://localhost:5173

---

## 🔑 Default Login

Email: [admin@example.com](mailto:admin@example.com)
Password: password123

---

## 📂 Project Structure

src/
backend/
public/
package.json

---

## 🚀 Summary

FlowStone is a complete enterprise workflow system with:

* Task & approval management
* Resource tracking
* Analytics dashboards
* Role-based access control

Built with modern frontend tools and a structured backend.

---
