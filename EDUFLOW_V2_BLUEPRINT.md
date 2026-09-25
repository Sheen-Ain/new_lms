# EduFlow V2 Development Blueprint

**Complete architectural redesign with MVC structure, phased development, design system, and security framework**

---

## Executive Summary

This document outlines the complete strategy for transforming EduFlow from a procedural PHP application into a modern, professionally-designed MVC-based platform while preserving 100% of existing functionality.

### Mission
Transform EduFlow from a procedural PHP application into a modern, professionally-designed MVC-based platform while preserving 100% of existing functionality.

### Key Objectives
1. **Complete UI/UX Redesign** — TAO-inspired modern, professional interface
2. **MVC Architecture Migration** — Clean, maintainable code structure
3. **Preserve 100% Functionality** — All existing features work identically
4. **Improved Code Quality** — Reusable components, better organization
5. **AJAX-First Approach** — Smooth interactions without full page reloads

---

## Project Overview

### Existing System Analysis

**Database**: `edu_flow` (MariaDB 11.4.13)  
**Architecture**: Core PHP + Vanilla JavaScript (no framework)  
**Hosting Target**: Free ByetHost/shared hosting (no .htaccess dependency)

#### Database Tables (24 total)
- **Users & Auth**: users, user_roles, roles, email_verifications, password_resets
- **Courses**: courses, batches, batch_students, batch_teachers
- **Content**: topics, topic_files, announcements
- **Assignments**: assignments, assignment_files, submissions
- **Testing**: tests, test_questions, test_options, test_question_map, test_attempts, test_answers
- **Communication**: conversations, messages, blocked_users
- **Live Sessions**: live_sessions, session_attendees
- **Feedback**: feedback_sessions, feedback_entries, feedback_tokens
- **Activity**: activity_logs

#### Current User Roles
1. **Student** — takes courses, submits assignments, attempts tests, participates in live sessions
2. **Teacher** — manages batches, creates assignments/tests, grades submissions, conducts live sessions
3. **Admin** — manages all content, users, batches, and system administration

#### Core Features
✅ User authentication (email + password with bcrypt)  
✅ Role-based access control (3 roles: student, teacher, admin)  
✅ Multi-batch course management  
✅ Assignments with file uploads and grading  
✅ Multiple choice testing system (entry/weekly/monthly)  
✅ Live sessions via Jitsi (JaaS integration)  
✅ Real-time chat (Pusher integration)  
✅ Announcements and feedback collection  
✅ Activity logging  
✅ File management (20MB max, diverse file types)  

---

## Core Development Principles

### Rule 1: Do Not Remove Functionality
Every feature in the existing system must work exactly as it does now. UI can change completely; behavior must remain identical.

### Rule 2: Professional Design
TAO-inspired enterprise interface. Clean, consistent, aesthetically polished. Not AI-generated or templated.

### Rule 3: Clean Architecture
MVC-based with reusable components, clear separation of concerns, and maintainable code structure.

### Rule 4: No .htaccess Dependency
Routing implemented in PHP for compatibility with free/shared hosting. No Apache rewrite rules required.

### Rule 5: Security First
Prepared statements, CSRF protection, input validation, output escaping. Security baked into every module.

### Rule 6: Incremental Development
Complete in phases. Each phase reviewed and verified before moving to next. No big-bang rewrites.

---

## MVC Architecture Blueprint

### Directory Structure

```
eduflow-v2/
├── config/               # Configuration files
│   ├── config.php       # Master configuration
│   ├── database.php     # DB connection
│   └── constants.php    # Application constants
├── app/
│   ├── Core/            # MVC Core classes
│   │   ├── Router.php
│   │   ├── Controller.php
│   │   ├── Model.php
│   │   └── View.php
│   ├── Models/          # Data models
│   │   ├── User.php
│   │   ├── Course.php
│   │   ├── Assignment.php
│   │   ├── Test.php
│   │   └── ...
│   ├── Controllers/     # Route handlers
│   │   ├── AuthController.php
│   │   ├── AdminController.php
│   │   ├── StudentController.php
│   │   ├── TeacherController.php
│   │   └── ...
│   └── Services/        # Business logic
│       ├── AuthService.php
│       ├── AssignmentService.php
│       ├── TestService.php
│       └── ...
├── resources/
│   ├── views/           # View templates
│   │   ├── layouts/
│   │   ├── student/
│   │   ├── teacher/
│   │   ├── admin/
│   │   └── public/
│   ├── css/
│   │   ├── variables.css
│   │   ├── components/
│   │   ├── layouts/
│   │   └── utilities/
│   └── js/
│       ├── app.js
│       ├── components/
│       ├── services/
│       └── utils/
├── public/              # Web root
│   ├── index.php        # Entry point (router)
│   ├── uploads/
│   └── static/
├── routes/
│   ├── web.php          # Web routes
│   ├── api.php          # AJAX/API routes
│   └── auth.php         # Auth routes
├── migrations/          # Database schema
├── tests/               # Automated tests
└── .env.example         # Environment variables
```

### Key Components

#### Router
PHP-based routing without .htaccess. Parses REQUEST_URI and dispatches to appropriate controller/action.

#### Model Base Class
Extends to all data models. Handles prepared statements, validation, relationships, and queries.

#### Controller Base Class
Handles authentication middleware, request parsing, view rendering, and JSON responses.

#### Service Layer
Business logic encapsulation. Keeps controllers lean and promotes code reuse.

---

## Design System (TAO-Inspired)

### Color Palette
- **Primary Blue**: #3b82f6
- **Success Green**: #10b981
- **Warning Amber**: #f59e0b
- **Danger Red**: #ef4444
- **Purple Accent**: #8b5cf6
- **Teal**: #06b6d4

### Typography
- **Display Font**: Inter (geometric, modern, accessible)
- **Body Font**: Inter (same family for consistency)
- **Monospace**: JetBrains Mono (code display)
- **Type Scale**: 12px, 14px, 16px, 18px, 20px, 24px, 32px, 48px

### Components
- **Buttons** — Primary (filled), Secondary (outlined), Tertiary (minimal), Danger (red)
- **Forms** — Clean input fields, selects, checkboxes with inline validation
- **Tables** — Professional data tables with sorting, filtering, pagination
- **Modals** — Reusable dialogs with confirm/cancel actions
- **Notifications** — Toast messages (success, error, warning, info)
- **Cards** — Content containers with consistent spacing and elevation

---

## Phased Development Plan

### Phase 1: Audit & Foundation (Current)
**Status**: In Progress

**Objectives**:
- Analyze existing codebase
- Document all features and workflows
- Map user interactions (student, teacher, admin portals)
- Identify reusable patterns
- Document current architecture

**Deliverable**: Complete project blueprint and architecture documentation

### Phase 2: Architecture & Framework Setup
**Timeline**: 3-4 weeks | **Priority**: Critical

**Tasks**:
- Create MVC folder structure
- Implement PHP router (no .htaccess)
- Create base Controller, Model, View classes
- Setup database abstraction layer
- Implement session/auth middleware
- Create configuration system

**Deliverable**: Working MVC bootstrap with sample routes

### Phase 3: Design System & Component Library
**Timeline**: 2-3 weeks | **Priority**: Critical

**Tasks**:
- Define design tokens (colors, spacing, typography)
- Create CSS component library
- Build reusable JavaScript components
- Design assessment interface
- Create form components with validation
- Build modal, toast, confirmation components

**Deliverable**: Complete design system and component showcase

### Phase 4: Core Platform
**Timeline**: 3-4 weeks | **Priority**: Critical

**Tasks**:
- Implement authentication system
- Create user models and services
- Build role-based access control
- Implement session management
- Create activity logging
- Setup file upload system

**Deliverable**: Working login, registration, and user management

### Phase 5: Public Website
**Timeline**: 1-2 weeks

**Tasks**:
- Create public homepage
- Build course listing page
- Create about/features pages
- Implement responsive layout

**Deliverable**: Professional public-facing website

### Phase 6: Student Portal
**Timeline**: 4-5 weeks

**Tasks**:
- Build dashboard with course list
- Create assignment management interface
- Implement assignment submission
- Build test-taking interface
- Create grade/feedback viewer
- Build live session participant view

**Deliverable**: Complete student portal with all existing functionality

### Phase 7: Teacher & Admin Portals
**Timeline**: 5-6 weeks

**Tasks**:
- Build course/batch management
- Create assignment creation/grading interface
- Build test management system
- Create admin user management
- Build reporting/analytics dashboards
- Implement live session hosting

**Deliverable**: Complete admin and teacher portals

### Phase 8: AJAX & API Standardization
**Timeline**: 2-3 weeks

**Tasks**:
- Centralize AJAX endpoint handlers
- Standardize API response format
- Create reusable JavaScript service classes
- Implement error handling
- Add request/response logging

**Deliverable**: Unified AJAX API with documentation

### Phase 9: Security & Optimization
**Timeline**: 2-3 weeks

**Tasks**:
- Comprehensive security audit
- Database query optimization
- Implement caching strategy
- Performance profiling and tuning
- Code cleanup and documentation

**Deliverable**: Secure, optimized, and well-documented codebase

### Phase 10: Testing & Deployment
**Timeline**: 2-3 weeks

**Tasks**:
- Functional testing of all features
- Browser compatibility testing
- Responsive design testing
- User acceptance testing
- Migration to production hosting

**Deliverable**: Production-ready EduFlow V2

---

## Reusable Component Library

### Backend Components

| Component | Purpose | Location |
|-----------|---------|----------|
| **BaseModel** | All models extend this for CRUD, prepared statements, validation | app/Core/Model.php |
| **BaseController** | Auth middleware, request parsing, response formatting | app/Core/Controller.php |
| **AuthService** | Login, registration, password reset, token generation | app/Services/AuthService.php |
| **FileUploadService** | File validation, storage, retrieval for all contexts | app/Services/FileUploadService.php |
| **ValidationService** | Input validation rules, error messages, sanitization | app/Services/ValidationService.php |
| **NotificationService** | Email sending, toast alerts, activity logging | app/Services/NotificationService.php |

### Frontend Components

| Component | Purpose | File |
|-----------|---------|------|
| **Toast** | Non-intrusive notifications (success, error, warning, info) | js/components/Toast.js |
| **Modal** | Reusable dialogs, forms, confirmations | js/components/Modal.js |
| **ConfirmDialog** | Confirmation prompts with custom messaging | js/components/ConfirmDialog.js |
| **Form** | Form validation, error display, submission handling | js/components/Form.js |
| **ApiClient** | AJAX request wrapper with error handling | js/services/ApiClient.js |
| **Table** | Sortable, filterable data tables with pagination | js/components/Table.js |

---

## Security Implementation

### 🔐 Core Security Measures

- **Prepared Statements**: All database queries use parameterized queries to prevent SQL injection
- **CSRF Protection**: Token generation/validation on all state-changing requests
- **Input Validation**: Whitelist-based validation on all user input
- **Output Escaping**: htmlspecialchars() and context-specific escaping on all output
- **Password Security**: bcrypt hashing with appropriate cost factor
- **Session Security**: Secure session handling with proper timeout
- **File Upload Security**: MIME type validation, file extension checking, safe storage
- **Rate Limiting**: API endpoint rate limiting to prevent abuse
- **Error Handling**: Graceful errors without exposing system details

---

## Technology Stack

### Backend
- Core PHP 7.2+
- No external dependencies
- MySQL/MariaDB

### Frontend
- Vanilla JavaScript (ES6+)
- CSS3 with custom properties
- HTML5 semantics

### Real-time
- Jitsi JaaS (video)
- Pusher (chat)
- AJAX polling (status)

### Integration
- Gmail SMTP (email)
- PHPMailer library
- File uploads

---

## Next Immediate Steps

### Phase 2 Kickoff — Architecture Setup

1. **Create MVC folder structure** — Set up app/, routes/, resources/ directories
2. **Implement PHP Router** — Parse REQUEST_URI, dispatch to controllers
3. **Create base classes** — Controller, Model, View, Service base classes
4. **Setup database layer** — Prepared statement wrapper, model relationships
5. **Implement authentication** — Session middleware, login/logout logic
6. **Create configuration system** — Environment-based config (.env support)

---

## Document Information

- **Created**: 2026-09-24
- **Version**: 1.0
- **Status**: Complete audit, ready for Phase 2 implementation
- **Total Estimated Timeline**: 25-35 weeks
- **Team Size**: 1-2 developers (scalable to more for parallel development)

---

**Ready to begin Phase 2?** Confirm the architecture design above, and we'll start building the MVC foundation immediately.
