# 🎓 TECHNOLOGY STACK DOCUMENTATION
## Environmental Reporting System - Capstone Project

**Project Name:** Sierra - LGU Environmental Reporting System  
**Location:** San Isidro, Nueva Ecija, Philippines  
**Purpose:** Web-based environmental incident reporting and management system

---

## 📋 TABLE OF CONTENTS
1. [Programming Languages](#programming-languages)
2. [Development Environment (IDE)](#development-environment-ide)
3. [Front-End Framework](#front-end-framework)
4. [Back-End Framework](#back-end-framework)
5. [Database Management System](#database-management-system)
6. [External APIs & Services](#external-apis--services)
7. [JavaScript Libraries](#javascript-libraries)
8. [Development Tools](#development-tools)
9. [Deployment Platform](#deployment-platform)
10. [Architecture Overview](#architecture-overview)

---

## 1. PROGRAMMING LANGUAGES

### **Primary Languages:**

#### **PHP 8.0+**
- **Purpose:** Server-side scripting and business logic
- **Usage:** 
  - MVC Controllers (AuthController, ReportController, AdminController, etc.)
  - Data Models (User, Report, Barangay, Category, ActivityLog)
  - Helper Classes (SettingsHelper, SecurityHelper, PermissionHelper)
  - API Endpoints (AJAX handlers)
- **Key Features Used:**
  - Object-Oriented Programming (OOP)
  - PDO for database operations
  - Session management
  - File handling and uploads
  - cURL for external API calls

#### **JavaScript (ES6+)**
- **Purpose:** Client-side interactivity and dynamic content
- **Usage:**
  - Interactive maps (Leaflet integration)
  - Real-time form validation
  - AJAX requests for asynchronous operations
  - Chart rendering (Chart.js)
  - Image/file uploads with preview
  - Geolocation services
- **Key Features Used:**
  - Arrow functions
  - Async/await
  - Fetch API
  - DOM manipulation
  - Event listeners

#### **SQL (MySQL dialect)**
- **Purpose:** Database queries and data management
- **Usage:**
  - Complex JOIN queries for reports
  - Aggregation functions for analytics
  - Stored procedures (via PDO)
  - Geospatial queries for location-based features
- **Key Features Used:**
  - SELECT, INSERT, UPDATE, DELETE operations
  - INNER JOIN, LEFT JOIN for relational data
  - GROUP BY, COUNT, SUM for statistics
  - WHERE clauses with complex conditions
  - ORDER BY for sorting

#### **CSS3**
- **Purpose:** Styling and layout
- **Usage:**
  - Custom component styling
  - Responsive design adjustments
  - Animation effects
  - Print media queries
- **Key Features Used:**
  - Flexbox and Grid layouts
  - CSS transitions and animations
  - Media queries for responsiveness
  - Custom properties (variables)

#### **HTML5**
- **Purpose:** Markup and structure
- **Usage:**
  - Semantic markup for accessibility
  - Form elements with validation
  - Embedded media (images, maps)
- **Key Features Used:**
  - Semantic tags (header, nav, main, footer)
  - Form validation attributes
  - Data attributes for JavaScript hooks
  - Accessibility (ARIA) attributes

---

## 2. DEVELOPMENT ENVIRONMENT (IDE)

### **Primary IDE:**

#### **Visual Studio Code (VS Code)**
- **Version:** Latest stable
- **Purpose:** Primary code editor for all development
- **Key Extensions Used:**
  - PHP Intelephense (PHP language support)
  - ESLint (JavaScript linting)
  - Prettier (Code formatting)
  - MySQL (Database management)
  - GitLens (Git integration)
  - Live Server (Local development server)

### **Alternative/Supporting Tools:**

#### **XAMPP Control Panel**
- **Version:** 8.0+
- **Purpose:** Local development server management
- **Components:**
  - Apache Web Server
  - MySQL Database Server
  - PHP Interpreter

#### **phpMyAdmin**
- **Version:** Latest (bundled with XAMPP)
- **Purpose:** Database administration and SQL query execution

---

## 3. FRONT-END FRAMEWORK

### **Primary Framework:**

#### **Tailwind CSS v3.x**
- **Type:** Utility-first CSS framework
- **Delivery:** CDN (https://cdn.tailwindcss.com)
- **Purpose:** Rapid UI development and consistent design
- **Key Features Used:**
  - Responsive utilities (sm:, md:, lg:, xl:)
  - Flexbox and Grid utilities
  - Color palette (custom green theme)
  - Spacing utilities (p-, m-, gap-)
  - Typography utilities
  - State variants (hover:, focus:, active:)
  - Dark mode utilities
- **Usage Statistics:**
  - Used in 100% of UI components
  - Custom configurations for brand colors
  - Responsive breakpoints for mobile-first design

### **UI Components:**

#### **Custom CSS Components**
- **File:** `assets/css/style.css`
- **Purpose:** Custom styles and component overrides
- **Components:**
  - Sidebar navigation
  - Status badges
  - Stat cards with hover effects
  - Map containers
  - Modal dialogs
  - Form elements

#### **Font Awesome 6.4.0**
- **Type:** Icon library
- **Delivery:** CDN
- **Purpose:** Consistent iconography across the application
- **Icons Used:** 100+ icons (fa-user, fa-map, fa-chart, fa-bell, etc.)

#### **Google Fonts - Manrope**
- **Type:** Typography
- **Weights:** 200, 300, 400, 500, 600, 700, 800
- **Purpose:** Modern, clean typography for professional appearance

---

## 4. BACK-END FRAMEWORK

### **Architecture Pattern:**

#### **Custom MVC (Model-View-Controller)**
- **Type:** Custom-built PHP MVC architecture
- **Purpose:** Organized code structure and separation of concerns

**Structure:**
```
├── models/              # Data layer (ORM-like classes)
│   ├── User.php
│   ├── Report.php
│   ├── Barangay.php
│   ├── Category.php
│   └── ActivityLog.php
├── controllers/         # Business logic layer
│   ├── AuthController.php
│   ├── ReportController.php
│   ├── AdminController.php
│   ├── BarangayController.php
│   ├── AnalyticsController.php
│   ├── AnnouncementController.php
│   └── SettingsController.php
├── views/              # Presentation layer
│   ├── admin/
│   ├── barangay/
│   ├── citizen/
│   ├── auth/
│   ├── shared/
│   └── layouts/
├── helpers/            # Utility classes
│   ├── SettingsHelper.php
│   ├── SecurityHelper.php
│   ├── PermissionHelper.php
│   └── IdGuard.php
└── config/             # Configuration files
    ├── config.php
    ├── database.php
    └── env.php
```

### **Key Features:**

#### **Database Abstraction Layer**
- **Class:** `Database.php`
- **Features:**
  - PDO-based connection management
  - Automatic migration system
  - Connection pooling
  - Error handling and logging

#### **Security Components**
- **Class:** `SecurityHelper.php`
- **Features:**
  - Input sanitization (XSS prevention)
  - CSRF token generation and validation
  - SQL injection prevention (prepared statements)
  - Password hashing (bcrypt)
  - Rate limiting for login attempts

#### **Permission System**
- **Class:** `PermissionHelper.php`
- **Features:**
  - Role-based access control (RBAC)
  - Dynamic permission checking
  - Custom role management
  - Permission inheritance

---

## 5. DATABASE MANAGEMENT SYSTEM

### **Primary Database:**

#### **MySQL 8.0+**
- **Type:** Relational Database Management System (RDBMS)
- **Engine:** InnoDB (for ACID compliance and foreign keys)
- **Charset:** utf8mb4 (full Unicode support, including emojis)
- **Collation:** utf8mb4_general_ci

### **Database Schema:**

**Tables:**
1. **users** - User accounts (citizens, barangay officials, MENRO staff, admins)
2. **reports** - Environmental incident reports
3. **categories** - Report categories (illegal dumping, flooding, pollution, etc.)
4. **barangays** - Administrative divisions
5. **activity_logs** - Audit trail for all actions
6. **report_history** - Status change history for reports
7. **announcements** - Public announcements and updates
8. **system_settings** - Application configuration (key-value store)
9. **rate_limits** - Login attempt tracking (brute-force prevention)
10. **rate_lockouts** - Temporary account lockouts

### **Key Database Features:**

#### **Relationships:**
- Foreign keys with CASCADE on delete/update
- One-to-many: User → Reports, Barangay → Reports
- Many-to-one: Reports → Category, Reports → User

#### **Indexes:**
- Primary keys (AUTO_INCREMENT)
- Foreign key indexes
- Status indexes for fast filtering
- Created_at indexes for time-based queries
- Composite indexes for complex queries

#### **Stored Procedures:**
- Implemented via PHP PDO prepared statements
- Transaction support for data integrity

---

## 6. EXTERNAL APIs & SERVICES

### **Email Service:**

#### **Brevo API (formerly Sendinblue)**
- **Endpoint:** https://api.brevo.com/v3/smtp/email
- **Purpose:** Transactional email receipts for report submissions
- **Authentication:** API Key (Header-based)
- **Method:** POST (JSON payload)
- **Free Tier:** 300 emails/day (9,000/month)
- **Features Used:**
  - HTML email templates
  - Dynamic content replacement
  - Delivery tracking
- **Usage:**
  - Report submission receipts
  - Status update notifications
  - Account creation emails

#### **Mailgun API (Backup)**
- **Endpoint:** https://api.mailgun.net/v3/{domain}/messages
- **Purpose:** Alternative email service
- **Authentication:** HTTP Basic Auth
- **Method:** POST (Form data)
- **Free Tier:** 5,000 emails/month (with custom domain)

### **SMS Service:**

#### **iProg SMS Gateway**
- **Endpoint:** https://sms.iprogtech.com/api/v1/sms_messages
- **Purpose:** SMS notifications for OTP and report updates
- **Authentication:** API Token (Query parameter)
- **Method:** POST (Form data)
- **Provider:** Philippine SMS gateway
- **Features Used:**
  - OTP for login/password reset
  - Status update notifications
  - Sender ID customization

### **Mapping Service:**

#### **OpenStreetMap (via Leaflet)**
- **Provider:** OpenStreetMap Foundation
- **Tiles:** https://tile.openstreetmap.org/{z}/{x}/{y}.png
- **License:** Open Data Commons Open Database License (ODbL)
- **Purpose:** Interactive maps for location-based reporting
- **Features Used:**
  - Map rendering
  - Marker placement
  - Geocoding
  - GeoJSON boundary overlays

### **Geolocation:**

#### **Browser Geolocation API**
- **Type:** Native browser API (HTML5)
- **Purpose:** Automatic location detection for report submissions
- **Features:**
  - GPS coordinate retrieval
  - Accuracy estimation
  - User permission handling

---

## 7. JAVASCRIPT LIBRARIES

### **Mapping:**

#### **Leaflet.js v1.9.4**
- **Type:** Open-source JavaScript map library
- **CDN:** https://unpkg.com/leaflet@1.9.4/dist/leaflet.js
- **Purpose:** Interactive maps with markers, popups, and GeoJSON
- **Features Used:**
  - Map initialization and rendering
  - Custom markers with icons
  - Popup windows with report details
  - GeoJSON layer rendering (barangay boundaries)
  - Click events for location selection
  - Zoom and pan controls

#### **Leaflet.markercluster v1.4.1**
- **Type:** Plugin for Leaflet
- **CDN:** https://unpkg.com/leaflet.markercluster@1.4.1/
- **Purpose:** Clustering multiple map markers for better visibility
- **Features Used:**
  - Automatic marker clustering
  - Custom cluster colors based on severity
  - Zoom-based cluster expansion
  - Cluster click events

### **Charts & Analytics:**

#### **Chart.js v4.x**
- **Type:** JavaScript charting library
- **CDN:** https://cdn.jsdelivr.net/npm/chart.js
- **Purpose:** Data visualization for dashboards and analytics
- **Chart Types Used:**
  - Line charts (trend analysis)
  - Bar charts (category comparison)
  - Doughnut charts (status distribution)
  - Radar charts (multi-dimensional data)
- **Features:**
  - Responsive charts
  - Interactive tooltips
  - Custom colors
  - Animation effects

### **Form Enhancement:**

#### **Select2 v4.1.0**
- **Type:** jQuery plugin for enhanced select dropdowns
- **CDN:** https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/
- **Purpose:** Searchable dropdown menus
- **Features Used:**
  - Search functionality
  - Multi-select
  - Custom templates
  - AJAX-powered options

### **Rich Text Editing:**

#### **Quill v1.3.7**
- **Type:** Modern WYSIWYG editor
- **CDN:** https://cdn.quilljs.com/1.3.7/
- **Purpose:** Rich text editing for announcements
- **Features Used:**
  - Text formatting (bold, italic, underline)
  - Lists (ordered, unordered)
  - Links and images
  - Undo/redo

### **Image Processing:**

#### **Cropper.js v1.6.2**
- **Type:** JavaScript image cropper
- **CDN:** https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/
- **Purpose:** Profile picture cropping and editing
- **Features:**
  - Drag-to-crop
  - Zoom controls
  - Aspect ratio lock
  - Preview

### **Animations:**

#### **GSAP v3.12.5**
- **Type:** GreenSock Animation Platform
- **CDN:** https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/
- **Purpose:** Smooth animations for landing page
- **Features:**
  - Fade-in animations
  - Scroll-triggered effects
  - Timeline sequences

#### **Canvas Confetti v1.x**
- **Type:** Celebration animation library
- **CDN:** https://cdn.jsdelivr.net/npm/canvas-confetti@1
- **Purpose:** Confetti animation for resolved reports
- **Features:**
  - Particle effects
  - Customizable colors
  - Performance optimized

### **PDF Generation:**

#### **jsPDF v2.x**
- **Type:** PDF generation library
- **CDN:** https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/
- **Purpose:** Export dashboards and reports to PDF
- **Features:**
  - Text rendering
  - Image embedding
  - Multi-page support

#### **html2canvas v1.4.1**
- **Type:** Screenshot library
- **CDN:** https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/
- **Purpose:** Convert DOM elements to canvas for PDF export
- **Features:**
  - Full DOM rendering
  - CSS support
  - Image capture

### **Utilities:**

#### **jQuery v3.6.0**
- **Type:** JavaScript library
- **CDN:** https://code.jquery.com/jquery-3.6.0.min.js
- **Purpose:** DOM manipulation and AJAX (required for Select2)
- **Limited Use:** Only where Select2 requires it

---

## 8. DEVELOPMENT TOOLS

### **Version Control:**

#### **Git**
- **Purpose:** Source code version control
- **Platform:** Local repository + remote (optional)
- **Branch Strategy:** Main branch for production-ready code

### **Package Management:**

#### **npm (Node Package Manager)**
- **Purpose:** JavaScript dependency management (if needed)
- **Usage:** Minimal (mostly CDN-based libraries)

### **Build Tools:**

#### **No Build Step**
- **Approach:** Direct development without transpilation/bundling
- **Reason:** Simplicity and fast deployment
- **Trade-off:** Using CDN links for all libraries

### **Testing:**

#### **Manual Testing**
- **Browser Testing:** Chrome, Firefox, Edge, Safari
- **Mobile Testing:** Responsive design testing on various screen sizes
- **User Acceptance Testing (UAT):** Tested with actual LGU staff

### **Performance Monitoring:**

#### **Browser DevTools**
- **Chrome DevTools:** Network analysis, console debugging
- **Lighthouse:** Performance, accessibility, SEO audits

---

## 9. DEPLOYMENT PLATFORM

### **Web Hosting:**

#### **InfinityFree**
- **Type:** Free web hosting service
- **Platform:** Shared hosting (Linux-based)
- **Specifications:**
  - PHP 8.0+
  - MySQL 8.0+
  - 5GB storage
  - Unlimited bandwidth
  - Free SSL certificate
- **Limitations:**
  - No PHP `mail()` function (uses external APIs)
  - SMTP ports blocked (uses HTTP APIs instead)

### **Server Configuration:**

#### **Apache Web Server**
- **.htaccess:** URL rewriting, security headers
- **mod_rewrite:** Clean URLs
- **Directory protection:** Sensitive folders blocked

### **Domain:**

#### **Free Subdomain**
- **Format:** `[project-name].infinityfreeapp.com`
- **Type:** Temporary for development/testing
- **Future:** Can upgrade to custom domain

---

## 10. ARCHITECTURE OVERVIEW

### **Application Architecture:**

```
┌─────────────────────────────────────────────────────────────┐
│                     PRESENTATION LAYER                       │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  HTML5 + Tailwind CSS + JavaScript (Leaflet, Chart.js)│  │
│  │  Responsive UI | Interactive Maps | Real-time Charts  │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              ↓ HTTP/AJAX
┌─────────────────────────────────────────────────────────────┐
│                     BUSINESS LOGIC LAYER                     │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  PHP 8.0+ MVC Controllers                             │  │
│  │  • AuthController (Login, Registration)              │  │
│  │  • ReportController (Submit, Verify, Resolve)        │  │
│  │  • AdminController (User Management)                 │  │
│  │  • AnalyticsController (Statistics, Insights)        │  │
│  └───────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  Helper Classes                                       │  │
│  │  • SecurityHelper (CSRF, XSS, Input Sanitization)    │  │
│  │  • PermissionHelper (RBAC)                           │  │
│  │  • SettingsHelper (Configuration Management)         │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              ↓ PDO
┌─────────────────────────────────────────────────────────────┐
│                      DATA ACCESS LAYER                       │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  PHP PDO Models                                       │  │
│  │  • User Model (CRUD operations)                      │  │
│  │  • Report Model (CRUD + status management)           │  │
│  │  • Barangay Model (Geographic data)                  │  │
│  │  • ActivityLog Model (Audit trail)                   │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              ↓ SQL
┌─────────────────────────────────────────────────────────────┐
│                      DATABASE LAYER                          │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  MySQL 8.0+ Database (InnoDB)                        │  │
│  │  • 10 Tables (users, reports, barangays, etc.)      │  │
│  │  • Foreign Key Constraints                           │  │
│  │  • Indexes for Performance                           │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              ↓ HTTP APIs
┌─────────────────────────────────────────────────────────────┐
│                    EXTERNAL SERVICES                         │
│  ┌──────────────┬──────────────┬─────────────────────────┐  │
│  │  Brevo API   │  iProg SMS   │  OpenStreetMap          │  │
│  │  (Email)     │  (SMS)       │  (Maps)                 │  │
│  └──────────────┴──────────────┴─────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

### **Data Flow:**

1. **User Request** → Browser sends HTTP request
2. **Routing** → `index.php` routes to appropriate controller
3. **Authentication** → Security checks (CSRF, session validation)
4. **Business Logic** → Controller processes request
5. **Data Access** → Model queries database (PDO)
6. **External APIs** → Calls to Brevo, iProg, OSM (if needed)
7. **View Rendering** → PHP generates HTML with embedded data
8. **Response** → Browser receives and renders UI
9. **Client-side Interactions** → JavaScript handles dynamic features

### **Security Layers:**

1. **Input Validation** → All user input sanitized (SecurityHelper)
2. **Authentication** → Session-based with secure cookies
3. **Authorization** → Role-based access control (RBAC)
4. **CSRF Protection** → Token validation on all forms
5. **SQL Injection Prevention** → Prepared statements (PDO)
6. **XSS Prevention** → Output escaping (`htmlspecialchars()`)
7. **Rate Limiting** → Brute-force protection (login attempts)
8. **Password Security** → bcrypt hashing with salt

---

## 📊 SUMMARY TABLE

| Category | Technology | Version | Purpose |
|----------|-----------|---------|---------|
| **Programming** | PHP | 8.0+ | Server-side logic |
| | JavaScript | ES6+ | Client-side interactivity |
| | MySQL | 8.0+ | Database queries |
| | HTML | 5 | Markup |
| | CSS | 3 | Styling |
| **IDE** | Visual Studio Code | Latest | Code editor |
| | XAMPP | 8.0+ | Local server |
| | phpMyAdmin | Latest | Database admin |
| **Front-End** | Tailwind CSS | 3.x | CSS framework |
| | Font Awesome | 6.4.0 | Icons |
| | Google Fonts | - | Typography |
| **Back-End** | Custom MVC | - | Architecture pattern |
| | PDO | - | Database abstraction |
| **Database** | MySQL | 8.0+ | RDBMS |
| | InnoDB | - | Storage engine |
| **APIs** | Brevo | v3 | Email service |
| | iProg | v1 | SMS service |
| | OpenStreetMap | - | Map tiles |
| **JavaScript** | Leaflet.js | 1.9.4 | Interactive maps |
| | Chart.js | 4.x | Data visualization |
| | Select2 | 4.1.0 | Enhanced dropdowns |
| | Quill | 1.3.7 | Rich text editor |
| | Cropper.js | 1.6.2 | Image cropping |
| | GSAP | 3.12.5 | Animations |
| **Hosting** | InfinityFree | - | Web hosting |
| | Apache | - | Web server |

---

## 🎓 CAPSTONE DOCUMENTATION NOTES

### **For Technical Specifications Section:**

**System Requirements:**
- **Server:** Apache 2.4+, PHP 8.0+, MySQL 8.0+
- **Client:** Modern web browser (Chrome, Firefox, Edge, Safari)
- **Internet:** Required for CDN libraries and external APIs
- **Mobile:** Responsive design supports iOS and Android devices

**Development Tools:**
- **IDE:** Visual Studio Code with PHP, JavaScript, and MySQL extensions
- **Local Server:** XAMPP (Apache + MySQL + PHP)
- **Version Control:** Git for source code management
- **Testing:** Manual browser testing with Chrome DevTools

**Deployment:**
- **Platform:** InfinityFree shared hosting
- **URL:** `[project-name].infinityfreeapp.com`
- **SSL:** Free SSL certificate included
- **Database:** Remote MySQL via phpMyAdmin

### **For Software Architecture Section:**

- **Pattern:** Model-View-Controller (MVC)
- **Layers:** 4-tier (Presentation, Business Logic, Data Access, Database)
- **API Integration:** RESTful HTTP calls to external services
- **Security:** Multi-layered (input validation, CSRF, XSS, SQL injection prevention)

### **For Feature Descriptions:**

- **Interactive Maps:** Leaflet.js with OpenStreetMap tiles
- **Real-time Charts:** Chart.js for data visualization
- **Email Notifications:** Brevo API (300/day free)
- **SMS Notifications:** iProg gateway for Philippines
- **Role-based Access:** Custom RBAC system with 4 roles
- **Responsive Design:** Tailwind CSS utility-first framework

---

## 📝 CITATION FORMAT (IEEE Style)

For academic papers, cite technologies as:

**Web Framework:**
> Tailwind Labs. (2023). Tailwind CSS [Software]. Available: https://tailwindcss.com

**Mapping Library:**
> Leaflet. (2023). Leaflet: An Open-Source JavaScript Library for Mobile-Friendly Interactive Maps [Software]. Available: https://leafletjs.com

**Charting Library:**
> Chart.js. (2023). Chart.js: Open Source HTML5 Charts [Software]. Available: https://www.chartjs.org

**Email API:**
> Brevo. (2023). Brevo API Documentation [Online]. Available: https://developers.brevo.com

---

**Document Version:** 1.0  
**Last Updated:** December 2024  
**Prepared For:** Capstone Project Documentation
