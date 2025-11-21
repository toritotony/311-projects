import streamlit as st
import pandas as pd
import numpy as np
import folium
from streamlit_folium import st_folium
import math

st.set_page_config(
    page_title='Car Dashboard | Home',
    page_icon='🏎️',
    layout='wide',
    initial_sidebar_state='expanded'
)

st.header('Should you bring your car to campus?', divider=True)

CAMPUS_LAT = 40.8762
CAMPUS_LON = -124.0786

MODE_SPEED_M_PER_MIN = {
    "Walking": 5000 / 60,   # ~5 km/h
    "Biking": 15000 / 60,   # ~15 km/h
    "Driving": 40000 / 60,  # ~40 km/h
}

def haversine_m(lat1, lon1, lat2, lon2):
    R = 6371000
    phi1, phi2 = math.radians(lat1), math.radians(lat2)
    dphi = math.radians(lat2 - lat1)
    dlambda = math.radians(lon2 - lon1)
    a = math.sin(dphi/2)**2 + math.cos(phi1)*math.cos(phi2)*math.sin(dlambda/2)**2
    c = 2 * math.asin(math.sqrt(a))
    return R * c

# =========================
# SIDEBAR – MAP & TRAVEL SETTINGS
# =========================
st.sidebar.header("Map & Travel Settings")

map_tile = st.sidebar.selectbox(
    "Map style",
    ["OpenStreetMap", "CartoDB positron"]
)

travel_mode = st.sidebar.selectbox(
    "Travel mode",
    ["Walking", "Biking", "Driving"]
)

time_bands = st.sidebar.multiselect(
    "Show time rings (minutes)",
    [5, 10, 15, 20],
    default=[5, 10, 15]
)

show_clusters = st.sidebar.checkbox(
    "Show student living clusters",
    value=False
)

st.sidebar.caption("Time rings are rough, straight-line estimates from campus.\nReal travel will vary by route and traffic.")

# Speeds in meters per minute (rough averages)
MODE_SPEED_M_PER_MIN = {
    "Walking": 5000 / 60,   # ~5 km/h
    "Biking": 15000 / 60,   # ~15 km/h
    "Driving": 40000 / 60,  # ~40 km/h city-ish
}

# =========================
# LAYOUT
# =========================
col1, col2, col3 = st.columns([0.4, 0.3, 0.3], gap='small')

# =========================
# INTERACTIVE MAP (col1)
# =========================
with col1:
    @st.cache_data
    def load_data():
        """Load address data. Cached so it only runs once."""
        df = pd.read_csv('project2/data/clean_data/population_addresses_validated_test_100.csv')
        return df

    df = load_data()

    if 'data' not in st.session_state:
        st.session_state['data'] = df

    # Cal Poly Humboldt campus location (1 Harpst St, Arcata)
    CAMPUS_LAT = 40.8762
    CAMPUS_LON = -124.0786

    @st.cache_data
    def get_common_locations(address_df):
        """Extract common living locations from address data."""
        # Filter to only successfully geocoded addresses
        valid_addresses = address_df[
            (address_df['current_geocode_status'] == 'ok') &
            (address_df['current_lat'].notna()) &
            (address_df['current_lon'].notna())
        ].copy()

        # Round coordinates to group nearby addresses (0.01 degree ≈ ~1 km)
        valid_addresses['lat_rounded'] = valid_addresses['current_lat'].round(2)
        valid_addresses['lon_rounded'] = valid_addresses['current_lon'].round(2)

        # Group by rounded coordinates and count students
        location_groups = valid_addresses.groupby(['lat_rounded', 'lon_rounded']).agg({
            'current_lat': 'first',
            'current_lon': 'first',
            'current_city': 'first',
            'pop_id': 'count'
        }).reset_index()

        location_groups.columns = [
            'lat_rounded', 'lon_rounded', 'lat', 'lon', 'city', 'count'
        ]

        # Create location list
        locations = []
        for _, row in location_groups.iterrows():
            locations.append({
                'name': f"{row['city']}",
                'lat': row['lat'],
                'lon': row['lon'],
                'count': int(row['count'])
            })
        return locations

    locations = get_common_locations(df)

    # -------------------------
    # Create map centered on campus
    # -------------------------
    map_center = [CAMPUS_LAT, CAMPUS_LON]
    m = folium.Map(location=map_center, zoom_start=12, tiles=map_tile)

    # -------------------------
    # Campus marker
    # -------------------------
    folium.Marker(
        [CAMPUS_LAT, CAMPUS_LON],
        popup="Cal Poly Humboldt Campus",
        icon=folium.Icon(color='red', icon='university', prefix='fa'),
        tooltip="Campus"
    ).add_to(m)

    # -------------------------
    # Time rings around campus
    # -------------------------
    speed = MODE_SPEED_M_PER_MIN[travel_mode]

    # Give each band a color so they’re easier to tell apart
    band_colors = ["#1b9e77", "#d95f02", "#7570b3", "#e7298a"]  # green, orange, purple, pink
    band_colors = band_colors[:len(time_bands)]

    for t, color in zip(sorted(time_bands), band_colors):
        radius_m = speed * t  # meters = m/min * minutes

        folium.Circle(
            location=[CAMPUS_LAT, CAMPUS_LON],
            radius=radius_m,
            color=color,
            fill=False,
            weight=2,
            opacity=0.8,
            popup=f"{t}-minute {travel_mode.lower()} radius (~{radius_m/1000:.1f} km)",
            tooltip=f"{t} min {travel_mode.lower()} from campus (straight line)"
        ).add_to(m)

    # -------------------------
    # Student living clusters (optional)
    # -------------------------
    if show_clusters:
        for loc in locations:
            # Marker size based on count (you can tune this)
            radius = max(loc['count'] / 2, 3)  # ensure minimum size

            folium.CircleMarker(
                [loc['lat'], loc['lon']],
                radius=radius,
                popup=f"{loc['name']}<br>Students: {loc['count']}",
                color='blue',
                fill=True,
                fillColor='blue',
                fillOpacity=0.6,
                tooltip=f"{loc['name']} ({loc['count']} students)"
            ).add_to(m)

    st.write("**Blue circles** show where anonymized clusters of students live.")
    st.write(
        f"**Colored rings** show approximate {travel_mode.lower()} time from campus "
        f"for {', '.join(map(str, sorted(time_bands)))} minute(s)."
    )

    map_data = st_folium(m, width=700, height=500, returned_objects=["last_clicked"])

clicked_lat = clicked_lon = None
distance_m = distance_km = None
times = None  # will be a dict if we have a click

if (
    map_data is not None
    and isinstance(map_data, dict)
    and map_data.get("last_clicked") is not None
):
    click = map_data["last_clicked"]
    clicked_lat = click["lat"]
    clicked_lon = click["lng"]

    distance_m = haversine_m(CAMPUS_LAT, CAMPUS_LON, clicked_lat, clicked_lon)
    distance_km = distance_m / 1000

    times = {}
    for mode, speed in MODE_SPEED_M_PER_MIN.items():
        times[mode] = distance_m / speed  # minutes
        
with col2:
    st.subheader("Estimated commute time")

    if times is not None:
        st.markdown(
            f"From the selected area to campus is approximately **{distance_km:.2f} km** "
            f"in a straight line."
        )
        st.caption("These are rough estimates, assuming direct travel and average speeds.")

        times_df = pd.DataFrame(
            {
                "Mode": list(times.keys()),
                "Estimated minutes": [round(v, 1) for v in times.values()],
            }
        )
        st.table(times_df)
    else:
        st.info("Click anywhere on the map to see estimated commute times.")

with col3:
    st.subheader("Our recommendation")

    if times is not None:
        w = times["Walking"]
        b = times["Biking"]
        d = times["Driving"]

        if w > 20 and b > 15:
            rec = "Driving"
            summary = (
                "You're relatively far from campus. Both walking and biking times are fairly high, "
                "so driving will likely be the most practical option."
            )
        elif w <= 10:
            rec = "Walking"
            summary = (
                "You're within about a 10-minute walk from campus. Walking is likely convenient, "
                "cheap, and healthy."
            )
        elif 10 < w <= 15:
            rec = "Walking or biking"
            summary = (
                "You're in a middle zone—walking is still reasonable, and biking will be even faster. "
                "Either could work depending on your preferences and weather."
            )
        elif 15 < w <= 20 and b <= 15:
            rec = "Biking"
            summary = (
                "Walking starts to get a bit long from here, but biking is still in a comfortable range. "
                "Biking is probably your best non-car option."
            )
        else:
            rec = "It depends"
            summary = (
                "Your commute sits in a gray area. Walking, biking, or driving could each make sense "
                "depending on schedule, weather, and your comfort."
            )

        st.markdown(f"### Recommended primary mode: **{rec}**")
        st.write(summary)

        st.markdown("#### How we decided")
        st.write(
            f"- Walking time: ~**{w:.1f} minutes**\n"
            f"- Biking time: ~**{b:.1f} minutes**\n"
            f"- Driving time: ~**{d:.1f} minutes**"
        )

        st.caption(
            "This is a simple rule-based suggestion based only on distance and average speeds. "
            "You should also consider cost, parking availability, weather, schedule flexibility, "
            "and personal comfort."
        )
    else:
        st.info("Click a location on the map to see a recommendation based on its distance.")

