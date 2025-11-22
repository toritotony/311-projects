# Thursday Thoroughbreds
**Project 2 - Transportation Dashboard**

This project required us to make an interactive dashboard to help students make decisions about their mode of transport to Humboldt's campus.

---

## Raw Data
The raw data consists of two datasets which are Population Addresses and a Simulated Commuter Survey.

### Dataset 1: Population Addresses 2024-25
The Population addresses is a dataset that contains:

* **pop_id**: Contains a number/code that represents the person in the dataset.
* **role**: Contains if the person is a student of some faculty member.
* **current_street**: The current street address of the person.
* **Current_city**: The current city where the person is residing.
* **current_state**: The current state where the person is residing.
* **current_zip**: The current zip code where the person is residing.
* **permanent_street**: The permanent street address of the person.
* **permanent_city**: The permanent city of the person.
* **permanent_zip**: The permanent zip code of the person.
* **permanent_region**: The region where the person's permanent residence is (Nor Cal, Bay Area, LA, San Diego or OOS).

### Dataset 2: Simulated_Commuter_Survey_5yrs_Messy_100
The features of this dataset are:

* **respondent_id**: ID of the person responding to the survey.
* **Year**: School year that response was collected (for example 2024-25, 2023-25, etc).
* **Role**: Whether the respondent is student, staff or faculty.
* **City**: City where respondent resides.
* **Zip**: Zipcode of the respondent.
* **Nearest Intersection**: Nearest intersection to where the respondent lives.
* **Distance to campus miles**: Distance from campus respondent lives in miles.
* **Trips per week**: How many trips per week to campus does the respondent makes.
* **Weeks per year**: How many weeks in a year is the respondent making trips to campus.
* **Share_Drive**: Percent amount of the time respondent drives.
* **Share Carpool**: Percent amount of the time respondent carpools to campus.
* **Share Bus**: Percent amount of the time respondent takes the bus to campus.
* **Share Walk**: Percent amount of the time respondent walks to campus.
* **Share Bike**: Percent amount of the time respondent bikes walks to campus.
* **Share Tele**: Percent amount of the time respondent works remote.
* **Mode Primary**: Primary mode of transportation respondent uses.
* **Is ev user**: Does the respondent drive an ev.
* **Is ebike user**: Does the respondent use an e-bike.
* **Mctde_est**: Estimated frequency to campus by mode of transport(?).
* **Total trips est**: Total estimated trips respondent makes.

---

## Data Pipeline
The Data pipeline contains the process of which APIs were used for the dashboard as well as the cleaning process for the raw data. The workflow is divided into three primary stages:

### 1. Survey & Population Data Cleaning (`data_pipeline.ipynb`)
The initial cleaning phase ensures data consistency across both datasets:

* **Commuter Survey Cleaning**:
    * Standardizes numeric fields (e.g., converting years to integers, rounding emission estimates).
    * Normalizes mode share columns to ensure they sum to 1.0 (100%).
    * Imputes missing numerical data (e.g., using column means for `trips_per_week`).
    * Standardizes categorical labels (e.g., correcting "teleporting" to "Telecommute").
* **Population Address Cleaning**:
    * Standardizes formatting for City, State, and ZIP codes (e.g., correcting "Arcatra" to "Arcata" and mapping "California" to "CA").
    * Handles missing address fields by assigning placeholder values ("00000" for ZIPs, "No Answer" for cities) to prepare for geocoding.

### 2. Geocoding & Address Enrichment
To enable spatial analysis, we utilized the Google Maps Geocoding API to enrich the cleaned datasets:

* **Survey Data**: Coordinates (Latitude/Longitude) were derived based on the `nearest_intersection` field combined with City/Zip data.
* **Population Data (Fallback Logic)**: A smart fallback strategy was implemented:
    1.  The pipeline first attempts to geocode the **Current Address**.
    2.  If the current address is missing or yields an invalid ZIP, the pipeline automatically falls back to the **Permanent Address**.
    3.  A `geocode_source` flag is added to track which address was used.

### 3. External Data & Network Analysis (`external_data_pipeline.ipynb`)
To generate the interactive map layers and travel time metrics, an external data pipeline was created:

* **Travel Time Grid**: A grid of coordinates covering the Humboldt County area was generated. Each point was reverse-geocoded to an address and processed through the Google Maps Directions API to calculate travel times to campus via Drive, Walk, Bike, and Transit.
* **Travel Time Clustering**: K-Means clustering was applied to the travel time data to group residential areas into distinct clusters based on commute duration.
* **Public Transit Infrastructure**: Bus stop locations were fetched using the Overpass API (OpenStreetMap data) to visualize transit accessibility.

**Outputs**: The pipeline produces clean, geocoded CSV files (`Geocoded_Survey_Data.csv`, `Geocoded_Population_Addresses.csv`, and `Humboldt_Travel_Times_Grid.csv`) ready for dashboard integration.

---

## Interactive Dashboard
The project features a user-facing dashboard built with Streamlit designed to help students answer the question: *"Should you bring your car to campus?"*.

**Link to the dashboard**: [Should-You-Bring-Car](https://should-you-bring-your-car.streamlit.app/)

### Key Features:
* **Interactive Map Interface**: Built with Streamlit, the map centers on the Cal Poly Humboldt campus. Users can switch between map tiles (OpenStreetMap, CartoDB) and toggle layers.
* **Travel Time Rings**: Visualizes commute proximity by drawing colored rings around the campus representing 5, 10, 15, or 20-minute travel radiuses for Walking, Biking, or Driving.
* **Student Population Clusters**: Anonymized data visualization showing common student living locations based on rounded coordinate grouping.
* **Commute Calculator**: Users can click any location on the map to calculate the straight-line (Haversine) distance to campus and receive estimated travel times for all three modes.
* **Recommendation Engine**: A system analyzes the travel times to suggest the most practical mode of transport (e.g., recommending "Biking" if walking takes >15 minutes but biking is <15 minutes).
* **Statistical Distribution**: Displays a histogram using Seaborn to show the distribution of travel times to campus for the selected mode, utilizing the pre-calculated K-Means grid data.
