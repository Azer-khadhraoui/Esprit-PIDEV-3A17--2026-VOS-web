# WebSymphony Project Resume

## Overview
**WebSymphony** is a Symfony-based employment management system featuring an admin dashboard (`gestion offre`) for managing job offers and criteria, and a client-facing opportunities portal for job seekers.

---

## Project Timeline & Accomplishments

### Phase 1: Project Initialization & Environment Setup
**Objective:** Set up the Symfony development environment and connect to the database.

**What we did:**
- Created a Symfony 7.4 skeleton project in `C:\Users\obelh\OneDrive\Desktop\websymphony`
- Installed PHP 8.2 via Windows Package Manager
- Configured PHP extensions (OpenSSL, cURL, mbstring, PDO MySQL, intl) in `php.ini`
- Installed Composer dependencies: Doctrine ORM, Twig, Maker Bundle, CSRF protection, and other core bundles
- Connected the application to local MariaDB database `vos` at `127.0.0.1:3306`
- Verified database connectivity by running test queries through Doctrine

**Key decisions:**
- Used Symfony's built-in server (`php bin/console server:run`) for local development
- Selected Doctrine ORM for database abstraction and entity mapping
- Configured `.env` for database URL connection string

---

### Phase 2: Data Modeling & Entity Design
**Objective:** Define the core data entities and their relationships.

**What we did:**
- **Created Entity: OffreEmploi** (`src/Entity/OffreEmploi.php`)
  - Fields: `idOffre`, `titre`, `description`, `lieu`, `typeContrat`, `workPreference`, `statutOffre`, `datePublication`, `salaire`
  - Mapped to database table `offre_emploi`
  - Relations: OneToMany with `CritereOffre`

- **Created Entity: CritereOffre** (`src/Entity/CritereOffre.php`)
  - Fields: `idCritere`, `niveauExperience`, `niveauEtude`, `competencesRequises`, `responsibilities`
  - Mapped to database table `critere_offre`
  - Relations: ManyToOne with `OffreEmploi`

- **Generated & Executed Migration** (`migrations/Version20260404133000.php`)
  - Manually created migration to ensure proper schema alignment
  - Created foreign key constraints linking offers and criteria

**Key decisions:**
- Used Doctrine ORM entity mapping for type safety and query builder support
- Established OneToMany/ManyToOne relationship for scalable criteria management per offer
- Used `DateTime` for date fields to handle date arithmetic

---

### Phase 3: Admin Dashboard Development (`gestion_offre`)
**Objective:** Build a comprehensive admin interface for managing job offers.

**What we did:**

#### Backend Implementation
- **Created Controller:** `src/Controller/GestionOffreController.php`
  - `dashboard()` – List all offers with filtering, search, and selection persistence
  - `newOffre()` – Create new offer with validation and form handling
  - `editOffre()` – Modify existing offer with pre-populated form data
  - `deleteOffre()` – Remove offers with CSRF protection
  - Helper methods: `validateOffreInput()`, `hydrationMapFromRequest()`

#### Frontend Implementation
- **Created Base Template:** `templates/base.html.twig`
  - Shared HTML structure with collapsible sidebar (compact by default, expand on hover)
  - Reusable CSS styling and layout foundation
  - Fixed Twig document structure issues (removed duplicate blocks)

- **Created Dashboard Template:** `templates/gestion_offre/dashboard.html.twig`
  - Scrollable offer table with columns: titre, lieu, typeContrat, datePublication, statutOffre
  - Dynamic search and filter controls (type, workPreference, location, status)
  - Row click selection to maintain state across navigation
  - CRUD action buttons: Ajouter, Modifier, Supprimer
  - Module tabs: Offre, CritereOffre, Statistique

- **Created Offer Form Template:** `templates/gestion_offre/offre_form.html.twig`
  - Reusable form for creating and editing offers
  - Dropdown fields with database-populated options (`typeContrat`, `workPreference`)
  - User selection dropdown (fetched from `utilisateur` table)
  - HTML5 validation constraints (required, minlength, maxlength)

**Features implemented:**
- Real-time filtering (auto-submit on select fields)
- Selection persistence across pages (state stored in URL query params)
- CSRF token protection on all state-modifying operations
- Server-side validation with error feedback

**Issues resolved:**
- Fixed broken JavaScript auto-filter initialization
- Removed stray "Choisir" button and malformed table HTML
- Fixed Twig duplicate block error in base template

---

### Phase 4: Criteria Management (`critere_offre`)
**Objective:** Enable admins to define and manage job requirements for each offer.

**What we did:**

#### Backend Implementation
- Extended `GestionOffreController` with criteria methods:
  - `criteresByOffre()` – List criteria for selected offer with add form
  - `createCritere()` – Insert new criteria with validation
  - `editCritere()` – Update existing criteria (modal access)
  - `deleteCritere()` – Remove criteria with CSRF protection
  - `validateCritereInput()` – Server-side validation helper

#### Frontend Implementation
- **Created Criteria Template:** `templates/gestion_offre/criteres.html.twig`
  - Criteria add form with fields: `niveauExperience`, `niveauEtude`, `competencesRequises`, `responsibilities`
  - Full criteria data table displaying all fields
  - Live client-side validation (green/red input feedback)
  - Edit modal for inline modification (no page navigation)
  - Delete action with CSRF protection

**Features implemented:**
- Modal-based inline editing to reduce friction
- Server + client-side validation with real-time feedback
- Nullable fields (skills/responsibilities can be empty)
- Per-offer criteria isolation

**Issues resolved:**
- Switched date type from `DateTimeImmutable` to mutable `DateTime` for Doctrine compatibility
- Fixed multiline text parsing (competences, responsibilities split by newlines)

---

### Phase 5: Statistics & Analytics
**Objective:** Provide admins with visual insights into job listing performance.

**What we did:**

#### Backend Implementation
- Added `statistique()` method to `GestionOffreController`
- Calculated KPIs:
  - Total open offers
  - Total closed offers
  - Average applications per offer
  - Offers by contract type
  - Top opportunities by applications

#### Frontend Implementation
- **Created Statistics Template:** `templates/gestion_offre/statistique.html.twig`
  - KPI card display (colored summary boxes)
  - 3 SVG-rendered charts:
    - Pie chart: Distribution of open vs. closed offers
    - Bar chart: Offers by contract type
    - Doughnut chart: Work preference breakdown
  - Initially used external chart library, then **switched to inline SVG rendering** for reliability

**Features implemented:**
- Real-time KPI calculation from database queries
- Chart rendering without external dependencies (improved performance)
- Responsive chart layout

**Issues resolved:**
- External chart library scripts not loading properly
- Replaced with custom SVG generation for guaranteed display
- Removed bottom summary tables per user request

---

### Phase 6: Client-Facing Opportunities Portal
**Objective:** Build public job listing page for candidates.

**What we did:**

#### Backend Implementation
- **Created Controller:** `src/Controller/ClientOffreController.php`
  - `index()` – List opportunities with advanced filtering, pagination (6 per page)
  - `apply()` – Backend route for candidature submission (UI disabled by request)
  - Helper methods: `getDistinctOffreValues()`, `userExists()`, `getLatestCriteriaByOffer()`

#### Frontend Implementation
- **Created Opportunities Portal:** `templates/client/opportunites.html.twig`
  - Professional header with nav and sign-up button
  - Advanced filter section (search, type contrat, mode travail, location, status)
  - Filter buttons positioned under "Type contrat" column
  - 6-card grid display per page with pagination numbers below
  - Details modal accessed via "Voir details" button
  - Criteria display in modal (experience, education, skills, responsibilities)
  - Non-functional "Postuler" button in modal (visual-only by user request)

**Features implemented:**
- Multi-field search (titre, description, location)
- Real-time filter options populated from database
- Server-side pagination (6 offers per page)
- Page numbers persist active filters in URLs
- Details modal with offer full information + criteria
- Status badges (ACTIVE/OUVERTE = green, FERMEE/INACTIVE = red)

**Database queries:**
- Joined offer data with latest criteria per offer
- Filtered by offer status (ACTIVE/OUVERTE by default, overridable)
- Distinct filter option extraction for dynamic dropdowns

**Issues resolved:**
- Fixed apply modal markup and event handling
- Removed apply action JS behavior while keeping Postuler button visual-only

---

## Technology Stack

| Layer | Technology | Purpose |
|-------|-----------|---------|
| **Runtime** | PHP 8.2 | Backend execution |
| **Framework** | Symfony 7.4 | Web framework & routing |
| **ORM** | Doctrine ORM/DBAL | Database abstraction & querying |
| **Database** | MariaDB/MySQL `vos` | Data persistence |
| **Templating** | Twig | View rendering & frontend templating |
| **Security** | Symfony CSRF Component | Token-based form protection |
| **Styling** | CSS3 (inline) | Custom responsive design |
| **Charting** | Inline SVG | Data visualization (no external deps) |

---

## Architecture Overview

```
websymphony/
├── src/
│   ├── Controller/
│   │   ├── GestionOffreController.php      (Admin: offers, criteria, stats)
│   │   └── ClientOffreController.php       (Public: opportunities, apply)
│   ├── Entity/
│   │   ├── OffreEmploi.php                (Job offer entity)
│   │   └── CritereOffre.php               (Job criteria entity)
│   └── ...
├── templates/
│   ├── base.html.twig                     (Shared layout)
│   ├── gestion_offre/
│   │   ├── dashboard.html.twig            (Offer list & module tabs)
│   │   ├── offre_form.html.twig           (Add/edit offer form)
│   │   ├── criteres.html.twig             (Criteria management)
│   │   └── statistique.html.twig          (Stats & charts)
│   ├── client/
│   │   └── opportunites.html.twig         (Public job listings)
│   └── ...
├── migrations/
│   └── Version20260404133000.php          (Schema creation)
├── .env                                   (Database connection)
└── composer.json                          (Dependencies)
```

---

## Key Routes

### Admin Panel (`/gestion-offre`)
- `GET /gestion-offre` – Dashboard with offers list & filters
- `GET /gestion-offre/new` – Create new offer form
- `POST /gestion-offre/new` – Store new offer
- `GET /gestion-offre/{idOffre}/edit` – Edit offer form
- `POST /gestion-offre/{idOffre}/edit` – Update offer
- `POST /gestion-offre/{idOffre}/delete` – Delete offer
- `GET /gestion-offre/{idOffre}/criteres` – Criteria list for offer
- `POST /gestion-offre/{idOffre}/critere/new` – Add criteria
- `POST /gestion-offre/{idOffre}/critere/{idCritere}/edit` – Update criteria
- `POST /gestion-offre/{idOffre}/critere/{idCritere}/delete` – Delete criteria
- `GET /gestion-offre/statistique` – Statistics & charts

### Client Portal (`/opportunites`)
- `GET /opportunites` – Opportunities list with filters & pagination
- `POST /opportunites/{idOffre}/postuler` – Apply for offer (backend route, UI disabled)

---

## Validation Strategy

### Offer Validation
- **Server-side:** Required fields enforced, min/max length checks, enum validation for enums
- **Client-side:** HTML5 `required`, `minlength`, `maxlength` attributes
- **CSRF:** Token required on create/edit/delete operations

### Criteria Validation
- **Server-side:** Field presence, malformed newline parsing protection
- **Client-side:** Live feedback (green border = valid, red border = invalid)
- **Multiline parsing:** Split by `\n` with trimming and empty line removal

---

## UI/UX Decisions

1. **Sidebar Navigation:** Compact by default, expands on hover (minimizes visual clutter)
2. **Selection Persistence:** URL query params maintain selected offer across navigation
3. **Modal Editing:** Inline modal for criteria edit to avoid page reloads
4. **Pagination:** 6 cards per page with clickable page numbers (previous/next arrows)
5. **Filter Positioning:** Search at top, dropdowns in grid, action buttons under first column
6. **Status Coloring:** Green = open, red = closed (semantic color coding)
7. **Details Modal:** Large modal for full offer info + criteria display

---

## Development Workflow & Problem Resolution

### Issues Encountered & Solutions

| Issue | Root Cause | Solution |
|-------|-----------|----------|
| PHP not in PATH | Windows installation | Added PHP 8.2 to environment variables |
| OpenSSL extension missing | PHP config | Uncommented `extension=openssl` in php.ini |
| DB driver mismatch | Doctrine config mismatch | Switched `.env` DB URL to MySQL driver |
| Twig duplicate blocks | Copy-paste error | Removed duplicate `{% block %}` definitions |
| Broken filter JS | Script initialization timing | Fixed jQuery selector scoping |
| Stray "Choisir" button | Template editing artifact | Removed leftover HTML fragments |
| Charts not rendering | External library not loading | Replaced with inline SVG rendering |
| Date type error | DateTimeImmutable immutability | Changed to mutable `DateTime` |
| Apply modal lingering | Event handler not cleared | Removed apply modal markup & JS hooks |

---

## Current Capabilities

### Admin Features
✓ Full CRUD for job offers (create, read, update, delete)  
✓ Advanced filtering on dashboard (search, type, mode, location, status)  
✓ Criteria management per offer (add, edit, delete)  
✓ Live validation feedback for criteria form  
✓ Statistics page with KPIs and charts  
✓ Selection persistence across navigation  
✓ CSRF protection on all state changes  
✓ Module tabs (Offre, Critere Offre, Statistique)  

### Client Features
✓ Job listings with 6-card pagination  
✓ Advanced filtering (search, type, mode, location, status)  
✓ Details modal with full offer info + criteria  
✓ Filter state persistence across page navigation  
✓ Responsive design (desktop, tablet, mobile)  
✓ Professional styling with semantic colors  

---

## Deployment Ready?

**Current Status:** Fully functional for local development.

**Before production:**
- [ ] Set up proper environment variables (`.env.prod`)
- [ ] Enable debug mode toggle in `config/packages/framework.yaml`
- [ ] Configure CORS if separate frontend domain
- [ ] Set up database backups
- [ ] Test with larger datasets
- [ ] Performance optimize (add database indexes, query caching)
- [ ] Set up email notifications for applications
- [ ] Implement user authentication & authorization
- [ ] Add SSL/HTTPS support

---

## Future Enhancements

- **User Authentication:** Login for admins and candidates
- **Application Tracking:** View/manage applications in admin panel
- **File Uploads:** CV and motivation letter support
- **Email Notifications:** Alerts for new applications and status changes
- **Advanced Reporting:** Export stats to CSV/PDF
- **Multi-language Support:** French/English localization
- **Candidate Dashboard:** View applied offers and status
- **Admin Audit Log:** Track all changes to offers and criteria

---

## Project Summary

We successfully built a **full-stack employment management system** using Symfony, Doctrine, and MariaDB. The project demonstrates:
- Solid backend architecture with proper validation and security
- Professional frontend UI with responsive design
- Database-driven filtering and dynamic content rendering
- Clean separation of admin (protected) and client (public) workflows
- Iterative development with continuous refinement based on user feedback

The system is ready for continued development and future feature additions.

---

**Total Development Time:** Single intensive session with iterative refinements  
**Final Commit Date:** April 4, 2026  
**Status:** ✅ Fully Functional for Development & Testing
