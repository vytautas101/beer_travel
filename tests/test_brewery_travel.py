import sys, os
sys.path.insert(0, os.path.dirname(os.path.dirname(__file__)))
import io
import contextlib

import brewery_travel as bt


def test_distance_zero():
    assert bt.distance(10, 20, 10, 20) == 0.0


def test_distance_symmetric():
    d1 = bt.distance(10, 20, 30, 40)
    d2 = bt.distance(30, 40, 10, 20)
    assert abs(d1 - d2) < 1e-9


def test_load_data():
    breweries, beers = bt.load_data()
    assert len(breweries) > 0
    assert len(beers) > 0


def test_plan_route_runs():
    buf = io.StringIO()
    with contextlib.redirect_stdout(buf):
        bt.plan_route(0, 0, max_distance=0.1)
    out = buf.getvalue()
    assert "Visited breweries:" in out
    assert "Total distance traveled:" in out
