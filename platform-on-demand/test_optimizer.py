from unittest import TestCase

from optimizer import *


class Test(TestCase):
    def test_create_points(self):
        data = [
            {
                "value": 10
            },
            {
                "value": 5
            },
            {
                "value": 50
            }
        ]
        pts = create_points(data)
        self.assertEqual(len(pts), 4, "We should have four points")

    def test_have_common_edge(self):
        v1 = Vertex([1, 10, 20])
        v2 = Vertex([1, 10, 20])
        self.assertFalse(have_common_edge(v1, v2), "These two points represent the same, so no common edge")

        v3 = Vertex([1, 10, 1])
        self.assertTrue(have_common_edge(v1, v3), "These two points should have a common edge")

        v4 = Vertex({1, 20, 30})
        self.assertFalse(have_common_edge(v1, v2), "These two points should not been seen with one common edge")


    def test_build_simplex(self):
        data = [
            Vertex([0, 0, 0]),
            Vertex([1, 0, 0]),
            Vertex([0, 1, 0]),
            Vertex([1, 1, 0]),
            Vertex([0, 0, 1]),
            Vertex([1, 0, 1]),
            Vertex([0, 1, 1]),
            Vertex([1, 1, 1]),
        ]
        s1 = build_simplex(data[0], data)
        self.assertEqual(len(s1), 4)

        v1 = get_from_minus(data, [s1])
        s2 = build_simplex(v1, data)

        v2 = get_from_minus(data, [s1, s2])
        s3 = build_simplex(v2, data)

        v3 = get_from_minus(data, [s1, s2, s3])
        s4 = build_simplex(v3, data)

        self.assertTrue(get_from_minus(data, [s1, s2, s3, s4]) is None)

    def test_explode(self):
        data = [
            Vertex([0, 0, 0]),
            Vertex([10, 0, 0]),
            Vertex([0, 10, 0]),
            Vertex([10, 10, 0]),
            Vertex([0, 0, 10]),
            Vertex([10, 0, 10]),
            Vertex([0, 10, 10]),
            Vertex([10, 10, 10]),
        ]
        s1 = build_simplex(data[0], data)
        lst = [s1]
        res = s1.explode(data)
        self.assertEqual(len(res), 4)
        replace_simplex(lst, s1, res)
        self.assertEqual(len(lst), 4)