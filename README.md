# 🚗 GarageMaster: Multi-Tenant Automotive SaaS Platform

![Project Status](https://img.shields.io/badge/Status-Live-brightgreen)
![Architecture](https://img.shields.io/badge/Architecture-Multi--Tenant%20SaaS-blue)
![Methodology](https://img.shields.io/badge/Methodology-Agile%20Scrum-success)

## 🌐 Live Platform
**[Click here to view the live GarageMaster System](https://garagemaster.sericsoft.com)**

*(Note: To protect the integrity of the multi-tenant architecture and tenant isolation, public registration is strictly disabled. Please contact me for Super Admin or Tenant Admin demo credentials.)*

## 📌 Project Overview
**GarageMaster** is a commercial-grade, multi-tenant Enterprise Resource Planning (ERP) system designed specifically for automotive garages, car service centers, and fleet maintenance companies. Developed under **Sericsoft Innovations Ltd**, the platform operates on a SaaS (Software as a Service) model. It allows the software owner to manage multiple independent garages under one centralized infrastructure, ensuring complete data isolation between tenants.

## 🚀 Key Features & Business Logic
* **Multi-Tenant Architecture:** Strict database-level isolation using `garage_id`. Tenant Admins can only access their specific garage's data, while the Super Admin retains global oversight.
* **Granular Role-Based Access Control (RBAC):** Custom, non-deletable user hierarchies including Super Admin, Garage Admin, Employee, Accountant, Customer, and Support Staff, each with strictly enforced permissions.
* **Job Card & Service Management:** End-to-end tracking of vehicle repairs, service schedules, and mechanic assignments.
* **Financial & Inventory Operations:** Integrated quotation generation, invoicing, payment tracking, and real-time inventory management for parts and consumables.
* **Automated Logistics:** Built-in Gate Pass system for vehicle entry/exit and automated email service reminders.
* **Enterprise Security:** Session-based authentication, CSRF protection, SQL injection prevention via prepared statements, and active login attempt tracking/lockout protocols.

## 🛠️ Tech Stack & Deployment
* **Frontend:** HTML5, CSS3, Bootstrap 5 (Custom CSS variables mapped to brand guidelines: `--brand-primary`, `--brand-secondary`).
* **Backend:** PHP 8+ (Custom modular architecture, strictly strictly separated logic without heavy external frameworks).
* **Database:** MySQL 8+ (Relational mapping for cross-tenant indexing and foreign key enforcement).
* **Typography & UI:** Montserrat/Poppins font stack for clear, readable data tables and dashboards.

## 📈 Project Management & Lifecycle
* Acted as Lead Architect and IT Project Manager, mapping out the multi-tenant business logic before deployment.
* Enforced strict Agile development cycles to deploy functional modules (Authentication, Job Cards, Financials) iteratively.

---
*Architected and Developed by [Gideon Mwiti](https://github.com/GideonMwiti) - IT Project Manager & Software Engineer*
