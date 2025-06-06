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

    # filter breweries that are reachable at all
    candidates = [
        b
        for b in breweries
        if distance(lat, lon, b["latitude"], b["longitude"])
        + distance(b["latitude"], b["longitude"], lat, lon)
        <= max_distance
    ]

    best_route: List[Dict] = []
    best_types: Set[str] = set()
    best_distance: float = float("inf")

    def search(cur_lat: float, cur_lon: float, remaining: List[Dict], visited: List[Dict], types: Set[str], dist: float) -> None:
        nonlocal best_route, best_types, best_distance

        back = distance(cur_lat, cur_lon, home["lat"], home["lon"])
        if dist + back <= max_distance:
            if len(types) > len(best_types) or (
                len(types) == len(best_types) and dist + back < best_distance
            ):
                best_route = visited.copy()
                best_types = types.copy()
                best_distance = dist + back

        for i, brew in enumerate(remaining):
            d = distance(cur_lat, cur_lon, brew["latitude"], brew["longitude"])
            new_dist = dist + d
            if new_dist + distance(brew["latitude"], brew["longitude"], home["lat"], home["lon"]) > max_distance:
                continue
            search(
                brew["latitude"],
                brew["longitude"],
                remaining[:i] + remaining[i + 1 :],
                visited + [brew],
                types | beers.get(brew["brewery_id"], set()),
                new_dist,
            )

    search(lat, lon, candidates, [], set(), 0.0)

    print("Visited breweries:")
    for brew in best_route:
        print(f"  id {brew['brewery_id']} at {brew['latitude']},{brew['longitude']}")
    print(f"Total distance traveled: {best_distance:.2f} km")
    print(f"Different beer types gathered: {len(best_types)}")
    for name in sorted(best_types):
        print(name)

    if map_file:
        generate_map(lat, lon, best_route, map_file)

if __name__ == "__main__":
    import argparse

    p = argparse.ArgumentParser(description="Compute beer-diversity-focused brewery route")
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
