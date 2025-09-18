# Data Collection & Storage System Recommendation for Waste Audits on Campus
## Project Plan

## Team Name & Members
### Assigned Roles
- **Tech Lead**: Anthony Wolfe  
- **Quality Assurance**: Emad Syed  
- **Domain Expert**: Aiden Thakur  
- **Project Manager**: Elio Piccagli  

---

### Purpose and Context
- **Project Purpose**: Recommend a practical end-to-end approach for _collecting_, _validating_, _storing_, and _analyzing_ campus waste-audit data so that results are accurate, comparable year-over-year, and easy for stakeholders to consume.  
- **Why Now**: Current data is fragmented across spreadsheets and ad-hoc files, which makes QA, reporting, and longitudinal analysis slow and error-prone. A consistent pipeline will reduce rework, improve trust in the data, and speed up decision-making.  
- **Scope**: Compare three storage approaches (spreadsheets, relational DB, NoSQL) and pair each with a simple data-entry front end or form. Evaluate setup effort, data-quality controls, scalability, reporting, integration with campus IT, cost, and maintainability.  
- **Outcomes**: A ranked recommendation with trade-offs, a working prototype (form + storage), an ERD (for applicable options), example reports/dashboards, and an operations checklist for ongoing use.  

---

### Systems to Compare
- **System A — Spreadsheet + Form Workflow**
  - **Front End**: Google Form or Excel/Sheets data-entry tab with validation (lists, required fields, ranges). Optional Apps Script/macros for checks and timestamping.  
  - **Storage**: Google Sheets or Excel file in shared drive with standardized headers and one record per row.  
  - **Typical Use**: Fast to stand up, low learning curve, good for small teams and early pilots.  
  - **Examples**: Google Forms ↔ Google Sheets; Excel template with data-validation rules.  

- **System B — Relational Database + Web App**
  - **Front End**: Simple web UI (PHP) for create/edit/search; CSV bulk upload for historical data; role-based access (auditor, reviewer, admin).  
  - **Storage**: Oracle SQL (hosted on campus via nrs-projects) with a normalized schema (audits, sites, materials, measurements, units, etc.) and primary/foreign keys for referential integrity.  
  - **Typical Use**: Strong data integrity, complex queries, multi-year analytics, and reliable reporting.  
  - **Examples**: PHP forms + Oracle SQL; scheduled SQL views for reporting.  

- **System C — NoSQL Database + Lightweight API**
  - **Front End**: Minimal HTML/JS form calling a small API (Flask or Node/Express) with server-side validation.  
  - **Storage**: Document store (e.g., MongoDB) capturing flexible audit payloads as JSON (useful when schema may evolve rapidly).  
  - **Typical Use**: Rapid iteration, evolving fields, and semi-structured data; add aggregation pipelines for reporting.  
  - **Examples**: Flask/Express API ↔ MongoDB Atlas; JSON export to analytics tools.  

---

### Evaluation Criteria and Methods
1. **Ease of Setup**  
   - **How**: Record steps/time to install/configure; import seed data; document blockers.  
   - **Who**: Project Manager & Tech Lead  

2. **Usability for Auditors**  
   - **How**: Task-based tests (enter audit, edit, export); measure completion time and error rate; gather feedback.  
   - **Who**: Domain Expert & Quality Assurance  

3. **Error Prevention & Data Quality**  
   - **How**: Check required fields, types/units, valid ranges, duplicates, referential integrity; attempt invalid inputs.  
   - **Who**: Quality Assurance & Tech Lead  

4. **Relationship Handling**  
   - **How**: Model audits ↔ materials ↔ sites (1-many, many-many); verify joins or embedded structures work for reporting.  
   - **Who**: Tech Lead & Domain Expert  

5. **Scalability & Performance**  
   - **How**: Grow dataset (×10/×100); measure form save time, query latency, and upload throughput.  
   - **Who**: Tech Lead & Quality Assurance  

6. **Reporting & Analysis**  
   - **How**: Produce year-over-year trends, site comparisons, and material breakdowns; exportability (CSV/PDF).  
   - **Who**: Domain Expert & Project Manager  

7. **Integration with Campus IT**  
   - **How**: Verify hosting constraints, single-sign-on options, backups, and data-sharing workflows.  
   - **Who**: Tech Lead & Project Manager  

8. **Security & Compliance**  
   - **How**: Review access controls, audit logs, least-privilege setup, and data-retention/export needs.  
   - **Who**: Tech Lead & Quality Assurance  

9. **Total Cost of Ownership**  
   - **How**: Estimate licensing/hosting, support time, training, and migration costs.  
   - **Who**: Project Manager & Quality Assurance  

10. **Maintenance & Extensibility**  
    - **How**: Evaluate backup/restore, upgrades, schema changes, and ease of adding new fields or reports.  
    - **Who**: Tech Lead & Quality Assurance  

11. **Accessibility**  
    - **How**: Check labels, keyboard navigation, color contrast, and error messaging in forms.  
    - **Who**: Quality Assurance & Domain Expert  

---

### Deliverables & Sample Implementations
- **1) Comparison Brief**  
  - 2–3 page summary of the three options with pros/cons, risks, and a ranked recommendation.  

- **2) Data Model & Definitions**  
  - Entity-Relationship Diagram (for relational option) and a data dictionary (fields, types, units, valid ranges).  

- **3) Working Prototype (Form + Storage)**  
  - **Spreadsheet Prototype**: Data-entry tabs with validations and basic summary charts.  
  - **Relational Prototype**: Oracle SQL schema + PHP web form for create/edit + CSV bulk upload; basic reporting views.  
  - **NoSQL Prototype**: Minimal HTML/JS form + Flask/Express API + MongoDB collection; sample aggregation for summaries.  

- **4) Test Suite & Data-Quality Checks**  
  - Validation rules, edge-case scenarios, and a defect/issue log; sample datasets with intentional errors.  

- **5) Reporting/Dashboard Samples**  
  - Year-over-year trends, site/material breakdowns, and export (CSV/PDF) examples.  

- **6) Operations Guide**  
  - Setup steps, backup/restore, user roles, change management, and accessibility checklist.  

---

### Data to be Collected for the Trial
- **Sample / Seed Data**: Use de-identified records from prior campus waste audits (sites, dates, materials, weights/volumes, units, team, notes). Each record should include a unique ID, timestamp, site/location, material category, quantity + unit, and collection method.  
- **Coverage & Variability**: Include multiple years, seasons, and sites (academic buildings, dining, housing) to test comparisons and year-over-year trends.  
- **Reference Lists**: Controlled vocabularies for sites and material categories (e.g., “Paper,” “Plastics #1–#7,” “Compostables,” “Landfill,” “Metal,” “Glass”) and unit standards (kg, lb, L, gal). Map any legacy labels to these lists.  
- **Privacy**: Remove personal identifiers and free-text that could reveal individuals; keep only operational notes relevant to quality or process.  

- **Quality Challenge Set** _(intentional issues to test validation & cleanup)_:  
  - Duplicates (same site/date/material/quantity)  
  - Missing fields (site, material, or unit)  
  - Type/unit issues (text in numeric fields, wrong units)  
  - Range errors (negative or implausible quantities)  
  - Relationship errors (unknown site/material values)  

- **Scalability Testing**:  
  - **Volume tiers**: ~1k (pilot), ~10k (program scale), ~100k (stress)  
  - **Workloads**: bulk CSV upload, form entry/save, multi-field search/filter, summary queries (site/material/date)  
  - **Metrics**: import throughput (rows/sec), form save latency (p50/p95), query latency (p50/p95), error rate  

- **Outputs for Verification**:  
  - Row-level validation report (counts by issue type)  
  - De-duplication summary (before/after)  
  - Basic rollups (totals by year/site/material; unit-normalized)  
  - Export samples (CSV/PDF) to confirm downstream compatibility  

---

### Roles & Responsibilities
- **Tech Lead**: Anthony Wolfe  
  - Owns architecture, prototypes (form/UI + storage), and integrations.  
  - Implements validation rules, schema/ERD (where applicable), and import/export flows.  
  - Defines performance tests; tracks metrics and remediates bottlenecks.  
  - _Deliverables_: ERD/schema, working prototype(s), import scripts, performance report.  

- **Quality Assurance**: Emad Syed  
  - Authors test plan (functional, data-quality, accessibility, regression).  
  - Creates the “quality challenge set,” runs tests, logs/triages defects.  
  - Verifies validation messages, error handling, and exports.  
  - _Deliverables_: Test cases, issue log, QA summary, accessibility checklist.  

- **Domain Expert**: Aiden Thakur  
  - Defines required fields, controlled vocabularies, and unit standards.  
  - Ensures data reflects real audit workflows and reporting needs.  
  - Validates KPIs and report definitions; reviews usability for auditors.  
  - _Deliverables_: Data dictionary, controlled lists, KPI definitions, usability notes.  

- **Project Manager**: Elio Piccagli  
  - Maintains timeline, risks, decisions, and stakeholder communications.  
  - Coordinates usability sessions and review checkpoints; captures actions.  
  - Compiles the recommendation brief and operations handoff notes.  
  - _Deliverables_: Project plan, decision log, comparison brief, operations guide.  

---

### Timeline Table
| Date  | Tech Lead | Project Manager | Domain Expert | Quality Assurance |
|---|---|---|---|---|
| 9/8  | Kickoff with Morgan King; capture system needs | Kickoff; record action items & owners | Clarify audit workflow & site context | Note data-quality risks & assumptions |
| 9/10 | Help design trial plan | Draft plan; assign 9/11 tasks | Review for domain accuracy | Review for clarity/completeness |
| 9/11 | Record data | Capture logistics (sites, units, access) | Record observations | Record data (validate TL entries) |
| 9/15 | Set up repo & tooling | Publish timeline/RACI | Define data needs | Outline test plan |
| 9/17 | Draft ERD & sheet skeleton | Coordinate milestones | Draft data dictionary | List validations |
| 9/18 | Load sample audits | Schedule review | Provide scenarios | Create “quality challenge set” |
| 9/19 | No class | No class | No class | No class |
| 9/22 | Tech review of ERD/sheet | Summarize decisions (decision log) | Confirm domain fit | Note quality issues |
| 9/24 | Build form scaffold | Define acceptance criteria | Draft labels/help text | Write test cases (incl. a11y) |
| 9/25 | Continue form | Track progress/blockers | Provide controlled vocabularies | Run basic tests |
| 9/26 | Finish form | Run readiness checklist | Quick-start notes | Regression check |
| 9/29 | Dashboard prototype | Plan demo | Identify KPIs | Accessibility check |
| 10/01 | Iterate dashboard | Collect feedback | Finalize KPI thresholds | Cross-browser check |
| 10/02 | Hook up data | Draft demo script | Prepare insights | Performance check (pilot tier) |
| 10/03 | Finalize dashboard | Sprint review | Polish copy | UAT checklist & sign-offs |
| 10/06 | Build report queries/views | Define reporting cadence | Report outline | Verify totals vs source |
| 10/08 | Add exports (CSV/PDF) | Stakeholder walkthroughs | Finish glossary | Edge-case tests |
| 10/09 | Automate reports | Draft operations guide | Document methods/assumptions | CI smoke tests |
| 10/10 | Release v1.0 | Final deck | User guide | Final sign-off |
