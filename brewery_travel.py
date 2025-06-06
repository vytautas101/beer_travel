import csv
import json
import math
from typing import Dict, List, Tuple, Set, Optional


def distance(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    """Compute distance between two lat/lon pairs in kilometers."""
    if lat1 == lat2 and lon1 == lon2:
        return 0.0
    theta = lon1 - lon2
    dist = math.sin(math.radians(lat1)) * math.sin(math.radians(lat2))
    dist += (
        math.cos(math.radians(lat1))
        * math.cos(math.radians(lat2))
        * math.cos(math.radians(theta))
    )
    dist = math.acos(dist)
    miles = math.degrees(dist) * 60 * 1.1515
    return miles * 1.609344


def load_data(base_path: str = "dumps") -> Tuple[List[Dict], Dict[str, Set[str]]]:
    """Load geocodes and beer names."""
    breweries = []
    with open(f"{base_path}/geocodes.csv", newline="") as f:
        reader = csv.DictReader(f)
        for row in reader:
            breweries.append(
                {
                    "id": row["id"],
                    "brewery_id": row["brewery_id"],
                    "latitude": float(row["latitude"]),
                    "longitude": float(row["longitude"]),
                }
            )

    beers: Dict[str, Set[str]] = {}
    with open(f"{base_path}/beers.csv", newline="") as f:
        reader = csv.DictReader(f)
        for row in reader:
            bid = row["brewery_id"]
            beers.setdefault(bid, set()).add(row["name"])
    return breweries, beers


def generate_map(
    start_lat: float, start_lon: float, visited: List[Dict], outfile: str
) -> None:
    """Write an HTML map displaying the visited breweries."""
    points = [[start_lat, start_lon]] + [
        [b["latitude"], b["longitude"]] for b in visited
    ]
    js_points = json.dumps(points)
    html = f"""<!DOCTYPE html>
<html>
<head>
 <meta charset='utf-8'/>
 <title>Brewery Route</title>
 <link rel='stylesheet' href='https://unpkg.com/leaflet@1.7.1/dist/leaflet.css'
  integrity='sha256-xodZBntMZQ1S1gLEV9jNO7DShzM1JkEYFDP3rJV41tk=' crossorigin=''/>
 <script src='https://unpkg.com/leaflet@1.7.1/dist/leaflet.js'
  integrity='sha256-dcY6QMPRyQzYKepKdeDu79uLhaLZIe4VZ2tRrg5PVcs=' crossorigin=''></script>
 <style>#map{{height:600px;}}</style>
</head>
<body>
<div id='map'></div>
<script>
 var map = L.map('map').setView([{start_lat}, {start_lon}], 6);
 L.tileLayer('https://{{s}}.tile.openstreetmap.org/{{z}}/{{x}}/{{y}}.png', {{
     attribution: '&copy; OpenStreetMap contributors'
 }}).addTo(map);
 var points = {js_points};
 L.polyline(points, {{color: 'blue'}}).addTo(map);
 for (var i = 0; i < points.length; i++) {{
     L.marker(points[i]).addTo(map);
 }}
</script>
</body>
</html>
"""
    with open(outfile, "w") as f:
        f.write(html)


def plan_route(
    lat: float, lon: float, max_distance: float = 2000.0, map_file: Optional[str] = None
) -> None:
    breweries, beers = load_data()
    home = {"lat": lat, "lon": lon}
    current_lat, current_lon = lat, lon
    total = 0.0
    visited: List[Dict] = []

    unvisited = breweries.copy()
    while True:
        best = None
        best_d = None
        for brew in unvisited:
            d = distance(current_lat, current_lon, brew["latitude"], brew["longitude"])
            d_back = distance(
                brew["latitude"], brew["longitude"], home["lat"], home["lon"]
            )
            if total + d + d_back > max_distance:
                continue
            if best_d is None or (d < best_d and d != 0.0):
                best = brew
                best_d = d
        if best is None:
            break
        visited.append(best)
        unvisited.remove(best)
        current_lat, current_lon = best["latitude"], best["longitude"]
        total += best_d

    # distance back home
    total += distance(current_lat, current_lon, home["lat"], home["lon"])

    beer_types: Set[str] = set()
    for brew in visited:
        beer_types.update(beers.get(brew["brewery_id"], set()))

    print("Visited breweries:")
    for brew in visited:
        print(f"  id {brew['brewery_id']} at {brew['latitude']},{brew['longitude']}")
    print(f"Total distance traveled: {total:.2f} km")
    print(f"Different beer types gathered: {len(beer_types)}")
    for name in sorted(beer_types):
        print(name)

    if map_file:
        generate_map(lat, lon, visited, map_file)


if __name__ == "__main__":
    import argparse

    p = argparse.ArgumentParser(description="Compute greedy brewery route")
    p.add_argument("lat", type=float, help="Starting latitude")
    p.add_argument("lon", type=float, help="Starting longitude")
    p.add_argument(
        "--max", type=float, default=2000.0, help="Maximum total travel distance in km"
    )
    p.add_argument(
        "--map-file", type=str, help="Write an HTML map of the route to this file"
    )
    args = p.parse_args()

    plan_route(args.lat, args.lon, args.max, args.map_file)
