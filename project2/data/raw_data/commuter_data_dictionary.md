# 🗂️ Data Dictionary — Simulated Cal Poly Humboldt Commuter Survey (2019–2025)

This dataset simulates respondent-level data collected through the **Annual Cal Poly Humboldt Commuter Survey**, administered by the Office of Institutional Research, Analytics, and Reporting (IRAR).  
Each row represents one respondent’s reported commuting behavior for one academic year.

---

## Identification & Demographics

| Column | Description | Units / Type | Source (Survey Question or Derived) |
|--------|--------------|---------------|------------------------------------|
| **respondent_id** | Unique synthetic identifier for each respondent. Some may appear in multiple years (representing returning participants). | Text (ID) | *Generated for simulation; not from survey.* |
| **year** | Academic year of the survey response (e.g., `2024-25`). | Categorical | *Recorded in survey administration metadata.* |
| **role** | Employment or enrollment category of respondent: `"Student"`, `"Faculty"`, or `"Staff/Admin"`. | Categorical | Q1: Sampling frame based on email invitation group. |
| **city** | City or community where respondent’s primary residence is located. | Text | Q10: “Please enter the city… for the residence from which you typically commute to Cal Poly Humboldt this semester.” |
| **zip** | ZIP code of residence. | Text | Q10: “Please enter the ZIP code…” |
| **nearest_intersection** | Text description of nearest major street intersection near respondent’s residence. | Text | Q10: “Nearest major intersection to your residence (e.g., G Street and 18th Street).” |
| **distance_to_campus_miles** | Estimated one-way travel distance between home and campus (1 Harpst St, Arcata). | Miles (numeric) | *Computed by analyst using Google Maps based on reported address/intersection (per Methodology, p.3).* |

---

## Commute Frequency & Duration

| Column | Description | Units / Type | Source |
|--------|--------------|---------------|--------|
| **trips_per_week** | Average number of one-way trips to/from campus in a typical week. Each round-trip counts as two trips. | Count (integer) | Q4: “In general, how many days a week are you commuting to the university…?” Adjusted to one-way trips by analyst. |
| **weeks_per_year** | Approximate number of weeks per academic year the respondent commutes or telecommutes. | Weeks (integer) | Q5: Role-specific question about duration of attendance or employment (e.g., “Between the end of last May and the end of this May, I will have been coming to the university for about…”). Converted to weeks by analyst. |

---

## Commute Mode Shares (Trip-based)

Each `share_*` variable gives the **proportion of weekly one-way trips** using that mode.  
They always sum to 1 for each respondent and correspond to integer trip counts.

| Column | Description | Units / Type | Source |
|--------|--------------|---------------|--------|
| **share_drive** | Fraction of trips driving or motorcycling alone (single occupant vehicle). | Proportion of weekly trips | Q6: “What mix of transportation options do you typically use… Driving or motorcycling alone.” |
| **share_carpool** | Fraction of trips carpooling or vanpooling with others (as driver or passenger). | Proportion of weekly trips | Q6: “Carpool or vanpool with others (either as driver or passenger).” |
| **share_bus** | Fraction of trips taken by public bus. | Proportion of weekly trips | Q6: “Riding the bus.” |
| **share_walk** | Fraction of trips made by walking (on or off campus). | Proportion of weekly trips | Q6: “Walking (I live on-campus/off-campus).” |
| **share_bike** | Fraction of trips using a bicycle, scooter, or skateboard (non–gas powered). | Proportion of weekly trips | Q6: “Bicycling, scooter, skateboard or other non-gas powered mode…” |
| **share_tele** | Fraction of weekly commute activity replaced by telecommuting or virtual classes. | Proportion of weekly trips | Q6: “Attending virtual classes or telecommuting.” |

> 💡 *In this simulated dataset, the proportions correspond to whole-number trips (e.g., 0.2 = 2 of 10 weekly trips).*

---

## Commute Mode Summary & Derived Indicators

| Column | Description | Units / Type | Source |
|--------|--------------|---------------|--------|
| **mode_primary** | Mode with the highest `share_*` value (respondent’s main commute type). | Categorical | *Computed by analyst from mode shares.* |
| **is_ev_user** | Indicates whether respondent drives an electric vehicle when driving alone or carpooling. | Boolean | Q7: “If you answered ‘drive alone’ or ‘carpool’, is it in one of the following vehicles? – Plug-in electric vehicle.” |
| **is_ebike_user** | Indicates whether respondent uses an e-bike or e-scooter for bicycling trips. | Boolean | Q9: “If you answered ‘Bicycling…’, is it electric or standard?” |

---

## Emissions and Trip Totals

| Column | Description | Units / Type | Source |
|--------|--------------|---------------|--------|
| **total_trips_est** | Estimated total number of one-way trips per year (`trips_per_week × weeks_per_year`). | Trips (count) | *Computed by analyst from Q4–Q5 responses.* |
| **mtcde_est** | Estimated metric tons of CO₂-equivalent emissions from commuting per respondent per year. Based on per-mode emission factors and distance traveled. | Metric tons CO₂e | *Computed by analyst using Sustainability Indicator Management & Analysis Platform (SIMAP) methodology, per report p.9–11.* |

---

## Relationship to Report and Methodology

- The proportions and averages replicate the official results from the *2025 Commuter Report*:
  - Students: ~27.5 % drive alone, 41 % walk, etc.  
  - Faculty and staff: progressively higher single-occupancy vehicle (SOV) rates.  
- The distance and emissions calculations mirror the process described in the *Methodology* section (p. 3):  
  > “Utilizing Google Maps, the PI calculated the miles traveled from residence to campus per mode per respondent… The Climate Action Analyst utilizes SIMAP to calculate greenhouse gas emissions…”

---

## Units Summary

| Type | Units | Example |
|-------|--------|----------|
| Distance | miles | 6.2 |
| Trips per week | count (one-way) | 10 |
| Weeks per year | weeks | 32 |
| Mode shares | proportion (0–1) | 0.2 |
| Emissions | metric tons CO₂e | 0.1234 |
