# Data Collection & Storage System Recommendation for Waste Audits on Campus
## Project Plan Template
## Team Name & Members            
### Assigned Roles
- Tech Lead: Anthony Wolfe
- Quality Assurance: Emad Syed
- Domain Expert: Aiden Thakur
- Project Manager: Elio Piccagli
---
### Purpose and Context
- **Project Purpose**: To create a streamlined data processing pipeline that enhances data quality and accessibility for stakeholders.
- **Context**: This project aims to address the challenges faced by organizations in collecting large volumes of data and storing it effectively. By implementing a robust data pipeline, we can ensure that data is clean, well-structured, and is readily available for consumption and analysis.
---
### Systems to be Compared
- **System A**: Sreadsheet programs - An electronic document in which data is arranged in the rows and columns of a grid and can be manipulated and used in calculations. (e.g., Microsoft Excel, Google Sheets)
- **System B**: Relational Databases - organizes data into structured tables composed of rows and columns, where each table represents a specific subject and its rows are individual records while columns hold specific data attributes (e.g., MySQL, PostgreSQL)
- **System C**: NoSQL Databases - a non-relational database that stores data in flexible formats (like documents, key-value pairs, graphs, or wide columns) instead of the rigid, table-based structure of traditional SQL databases (e.g., MongoDB, Cassandra)
---
<!-- This is subject to change -->
### Evaluation Criteria and Methods
1. Ease of Set Up
- **How**: Measure the time and resources required to set up each system, including installation, configuration, and initial data import.
- **Who**: Project Manager and Tech Lead
2. Ease of Use for Auditors
- **How**: Conduct user testing sessions with auditors to assess the intuitiveness of the interface, ease of data entry, and overall user experience.
- **Who**: Domain Expert and Quality Assurance
3. Error Prevention & Data Quality
- **How**: Evaluate the built-in validation features, error-checking mechanisms, and data integrity controls of each system.
- **Who**: Quality Assurance and Tech Lead
4. Ability to Handle Relationships
- **How**: Test the capability of each system to manage complex data relationships, such as one-to-many and many-to-many associations.
- **Who**: Tech Lead and Domain Expert
5. Scalability and Performance
- **How**: Simulate increasing data loads and measure system performance, including response times and resource utilization.
- **Who**: Tech Lead and Quality Assurance
6. Reporting and Analysis
- **How**: Assess the reporting tools and analytical capabilities of each system, including ease of generating reports and visualizations.
- **Who**: Domain Expert and Project Manager
7. Integratiion with Campus IT
- **How**: Evaluate how well each system integrates with existing campus IT infrastructure, including compatibility with other software and data sources.
- **Who**: Tech Lead and Project Manager
8. Cost (Total Cost of Ownership)
- **How**: Analyze the upfront costs, ongoing maintenance expenses, and any additional costs associated with each system.
- **Who**: Project Manager and Quality Assurance
9. Maintenance After Setup
- **How**: Evaluate the ease of performing routine maintenance tasks, such as updates, backups, and troubleshooting.
- **Who**: Tech Lead and Quality Assurance
---
### Sample Implementation to be Built
- **Spreadsheet Prototype**: Create a sample spreadsheet template that includes data entry forms, validation rules, and basic reporting features.
    - **Tools**: Microsoft Excel or Google Sheets
    - **Data Structure**: Tabular format with predefined columns for audit data.
- **Relational Database Prototype**: Set up a streamlined relational database schema to store audit data, along with a simple web interface for data entry and reporting.
    - **Tools**: Oracle SQL, leveraging on campus servers through nrs-projects and a PHP-driven web interface to host the web app. File upload functionality will be implemented to allow bulk data uploads via CSV files. This then can be queried and reported on through SQL queries for year over year reporting. Otherwise, the web interface will allow for single year data entry.
    - **Data Structure**: Normalized tables with relationships defined through foreign keys.
---
### Data to be Collected for the Trial
- **Sample Data**: Use historical waste audit data from previous campus audits to populate each system.
_ **Error Prevention & Data Quality**: Introduce intentional errors (e.g., duplicate entries, missing values) to test validation features.
- **Scalability Testing**: Gradually increase the volume of data to assess performance under
--- 
### Roles & Responsibilities
- **Tech Lead**: Anthony Wolfe
    - Leading technical implementation, system setup, and performance testing.
- **Quality Assurance**: Emad Syed
    - Overseeing testing protocols, data quality assessments, and error prevention evaluations.
- **Domain Expert**: Aiden Thakur
    - Providing insights on user needs, data relationships, and reporting requirements.
- **Project Manager**: Elio Piccagli
    - Coordinating project activities, managing timelines, and ensuring effective communication among team members.
---
### Timeline Table
| Date | Tech Lead | Project Manager | Domain Expert | Quality Assurance |
--- | --- | --- | --- | ---
| 9/8 | Attend meeting with Morgan King; take notes on system requirements | Attend meeting; record action items | Attend meeting; ask questions about audit practices | Attend meeting; note data quality concerns |
| 9/10 | Help design trial plan | Draft plan and documents and assign tasks for 9/11 | Review plan for domain accuracy | Review plan for clarity and completeness |
| 9/11 | Record Data | (blank) | Record Observations | Record Data (validate tech leads data too) |