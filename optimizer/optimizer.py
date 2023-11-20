#!/usr/bin/python3
import copy
import sqlite3
import subprocess
import json
import datetime
import time

import numpy as np
from yaml import load, Loader
import matplotlib.pyplot as plt

# filter = ["host", "metaservice", "ba", "kpi", "administrators", "users", "editors", "acl_group"]
filter = ["host", "users"]

max_length = 10

# output can be sqlite or stdout
output = "sqlite"
INJECTOR_PATH = ".."
# Timeout in seconds
TIMEOUT = 500
HIST = [{}, {}, {}]


class Vertex:
    def __init__(self, v: [int]):
        self.v = np.array(v)
        self.services_count = 0

    def dimension(self):
        return len(self.v)

    def __setitem__(self, key, value):
        self.v[key] = value

    def __getitem__(self, key):
        return self.v[key]

    def __add__(self, other):
        return Vertex(self.v + other.v)

    def __floordiv__(self, number: int):
        return Vertex(self.v // number)

    def __iadd__(self, other):
        self.v += other.v
        return self

    def __gt__(self, other):
        for i in range(self.v):
            if self.v[i] != other.v[i]:
                if self.v[i] > other.v[i]:
                    return True
                else:
                    return False
        return False

    def __lt__(self, other):
        for i in range(len(self.v)):
            if self.v[i] != other.v[i]:
                if self.v[i] < other.v[i]:
                    return True
                else:
                    return False
        return False

    def __isub__(self, other):
        self.v -= other.v
        return self

    def __ifloordiv__(self, other):
        self.v //= other
        return self

    def __sub__(self, other):
        return Vertex(self.v - other.v)

    def __iter__(self):
        self.n = 0
        return self

    def __next__(self):
        if self.n < len(self.v):
            result = self.v[self.n]
            self.n += 1
            return result
        else:
            raise StopIteration

    def __repr__(self):
        return f"{{ {str(self.v)} {self.services_count} }}"

    def set_services_count(self, v):
        self.services_count = v


class Simplex:
    def __init__(self, dim: int):
        self.dimension = dim
        self.data = []
        self.smart = False

    def add(self, v: Vertex):
        assert (v.dimension() == self.dimension)
        self.data.append(v)

    def __repr__(self):
        return str(self.data)

    def __contains__(self, item):
        return any(np.array_equal(item, i) for i in self.data)

    def dim(self):
        return self.dimension

    def __len__(self):
        return len(self.data)

    def __copy__(self):
        retval = Simplex(self.dimension)
        retval.data = []
        retval.data += self.data
        retval.smart = self.smart
        return retval

    def __deepcopy__(self, memo):
        retval = Simplex(self.dimension)
        for v in self.data:
            retval.add(copy.deepcopy(v))
        return retval

    def __setitem__(self, key, value):
        self.data[key] = value

    def __eq__(self, other):
        """
        Return True if simplex are equals. This works because a simplex cannot contain twice a vertex.
        :param other: a simplex
        :return: True if they are geometrically equal.
        """
        if self.dimension == other.dimension and len(self.data) == len(other.data):
            return all(v in other for v in self.data)
        else:
            return False

    def get_mid_vertices(self):
        """
        Computes the average vertex of each face of the vertex.
        We found at the index n, the vertex computed without the nth vertex of the simplex.
        :return: A list of vertices.
        """
        retval = []
        for idx in range(len(self.data)):
            m = Vertex([0, ] * self.dimension)
            for i in range(len(self.data)):
                if i != idx:
                    m += self.data[i]
            m //= len(self.data) - 1
            retval.append(m)

    def sort_edges(self):
        """
        Compute the length of each edge of this simplex and return a list of them
        ordered from the longest to the shorted. Each element of the list is a tuple
        with the length, the index of the first vertex, the index of the second one.
        :return: a list of tuple (length, idx1, idx2)
        """
        retval = []
        for i in range(len(self.data)):
            for j in range(i + 1, len(self.data)):
                length = np.linalg.norm(self.data[j].v - self.data[i].v)
                retval.append((length, i, j))
        retval.sort(reverse=True)
        return retval

    def new_explode(self, points, min_value: int, max_value: int):
        """
        Explode the simplex, For that, it computes the average points of its vertices and then builds new simplexes
        from the original one and by replacing one of its vertices by the average one. A list of all these vertices
        is returned. The number of new simplex is its dimension + 1, that is also the count of vertices.
        In case the average point is one of the existing vertices, the function returns None
        :return: A list of simplexes or None if it is impossible to get a new average point.
        """
        # We compute the average point of the simplex
        if self.smart:
            return None
        lengths = self.sort_edges()
        for t in lengths:
            if t[0] < max_length:
                self.smart = True
                return None
            m = (self.data[t[1]] + self.data[t[2]]) // 2
            avg = (self.data[t[1]].services_count +
                   self.data[t[2]].services_count) // 2
            compute_best_services_count(m, min_value, max_value)
            if abs(avg - m.services_count) > 0:
                t = lengths[0]
                m = (self.data[t[1]] + self.data[t[2]]) // 2
                m = add_point_if_needed(points, m)
                avg = (self.data[t[1]].services_count +
                       self.data[t[2]].services_count) // 2
                compute_best_services_count(m, min_value, max_value)
                new_s = copy.copy(self)
                new_s[t[1]] = m
                self[t[2]] = m
                points.append(m)
                save_row(m, data, reduced_data)
                return new_s
        self.smart = True
        return None

    def __iter__(self):
        self.n = 0
        return self

    def __next__(self):
        if self.n < len(self.data):
            result = self.data[self.n]
            self.n += 1
            return result
        else:
            raise StopIteration


data = []
main_idx = 0

points = []


def add_point_if_needed(points, v):
    for p in points:
        if v == p:
            return p
    points.append(v)
    return v


def extract_services(data):
    """
    In data, one item has 'services' for name. This element is removed from data and its value is
    returned by the function.
    :param data: Wanted values for benchmarks in a list, each one with its name. data is modified as
    the 'services' item is pop from it.
    :return: The wanted value for services.
    """
    retval = data
    for i in range(len(retval)):
        d = retval[i]
        if d['name'] == 'service':
            svc = retval.pop(i)
            return svc['max']
    # In case of no services in data.
    return 1


def origin(data):
    dim = len(data)
    v = Vertex([1, ] * dim)
    for d in range(dim):
        v[d] = data[d]["min"]
    return v


def create_points(data):
    """
    Create the bounding points defined by the given values.
    :param data: The list of bounds used to create the list of points.
    :return: The list of bounding points.
    """
    dim = len(data)
    max = 1 << dim
    retval = []
    for i in range(max):
        v = origin(data)
        for d in range(dim):
            if i & (1 << d):
                v[d] = data[d]["max"]
        retval.append(v)
    return retval


def have_common_edge(v1: Vertex, v2: Vertex):
    """
    Check if vertices v1 and v2 have a common trivial edge in common
    :param v1: A vertex
    :param v2: A second vertex
    :return: A boolean
    """
    v = v1 - v2
    match = 0
    for i in v:
        if i != 0:
            match += 1
    return match == 1


def build_simplex(v, data):
    """
    Build a simplex from the list of points with v as vertex. The new vertices must be built with points having
    a common edge with the vertex v.
    :param v: The point to use as first vertex
    :param data: The list of points we can use to build the simplex.
    :return: A simplex
    """
    retval = Simplex(v.dimension())
    retval.add(v)
    for vv in data:
        if have_common_edge(vv, v):
            retval.add(vv)
    return retval


def get_from_minus(data, spl):
    """
    Pick a point from data that does not appear in the simplexes list. The point is not chosen randomly, we just get the
    first one that verifies the condition.
    :param data: The set of points in the universe
    :param spl: The set of simplexes.
    :return: A point
    """
    for v in data:
        found = False
        for s in spl:
            if v in s:
                found = True
                break
        if not found:
            return v
    return None


def init_simplex(data: Vertex):
    retval = []
    origin = None
    for v in data:
        if origin is None:
            origin = v
        elif v < origin:
            origin = v
    v = origin
    while v is not None:
        s = build_simplex(v, data)
        retval.append(s)
        v = get_from_minus(data, retval)
    return retval


def replace_simplex(initial_lst, s, splx):
    """
    We have a list of simplexes containing the simplex s. This function replaces s by the simplexes contained in splx.
    :param initial_lst: A list of simplexes
    :param s: A simple simplex
    :param splx: A new list of simplexes
    """
    initial_lst.remove(s)
    initial_lst += splx


def filter_data(data, filter):
    # retval_idx = []
    retval_pts = []
    for d in range(len(data)):
        if data[d]["name"] in filter:
            # retval_idx.append(d)
            retval_pts.append(data[d])

    return retval_pts
    # return retval_idx, retval_pts


def generate_conf(v: Vertex, data, reduced_data):
    dico = {}
    for idx in range(v.dimension()):
        dico[reduced_data[idx]["name"]] = v[idx]
    for d in data:
        if d["name"] not in dico:
            dico[d["name"]] = d["max"]
    dico["service"] = v.services_count

    if dico["poller"] == 1:
        poller = "true"
    else:
        poller = "false"

    output = f"""poller:
  hostsOnCentral: {poller} # dispatch hosts on central server

timeperiod:
  count: {dico['timeperiod']}

contact:
  count: {dico['contact']}

command:
  count: {dico['command']}
  metrics:
    min: 1
    max: 30

host:
  count: {dico['host']}

service:
  count: {dico['service']}

metaservice:
  count: {dico['metaservice']}

hostgroup:
  count: {dico['hostgroup']}
  hosts:
    min: 0
    max: 200

servicegroup:
  count: {dico['servicegroup']}
  services:
    min: 0
    max: 300

host_category:
  count: {dico['host_category']}
  hosts:
    min: 0
    max: 10

service_category:
  count: {dico['service_category']}
  hosts:
    min: 0
    max: 30

ba:
  count: {dico['ba']}

kpi:
  count: {dico['kpi']}

host_disco_job:
  count: 0

acl_resource:
  count: {dico['acl_resource']}
  hosts: 100
  servicegroups: 1000

acl_group:
  count: {dico['acl_group']}
  resources: 3

user:
  administrators: {dico['administrators']}
  editors: {dico['editors']}
  users: {dico['users']}
"""
    with open("new_config.yaml", "w") as f:
        f.write(output)


def call_injector():
    subprocess.run([f"{INJECTOR_PATH}/bin/console",
                   "centreon:inject-data", "-p"])
    subprocess.run([f"{INJECTOR_PATH}/bin/console",
                   "centreon:inject-data", "-c", "new_config.yaml"])


def start_centreon():
    subprocess.run(["systemctl", "start", "cbd", "centengine"])


def stop_centreon():
    subprocess.run(["systemctl", "stop", "cbd", "centengine"])


def _check_centreon():
    with open("engine-stats.json") as f:
        # with open("/var/lib/centreon-engine/central-module-master-stats.json", "r") as f:
        content = json.load(f)
        # Has Engine some queue files?
        for key in content:
            if key.starts_with("endpoint "):
                if "queue_file_enabled" in content[key] and content[key]["queue_file_enabled"]:
                    print(f"Central Engine has queue files on {key}")
                    qf = content[key]["queue_file"]
                    if key not in HIST[0]:
                        HIST[0][key] = []
                    HIST[0][key].append(
                        int(qf["file_expected_terminated_at"]) - int(time.time()))
                    HIST[0][key] = HIST[0][key][-20:]
                    if HIST[0][key][-1] < HIST[0][key][0]:
                        retval &= True
                    retval &= False

    with open("broker-stats.json") as f:
        # with open("/var/lib/centreon-broker/central-broker-master-stats.json", "r") as f:
        content = json.load(f)
        # Has Broker Central some queue files?
        for key in content:
            if key.starts_with("endpoint "):
                if "queue_file_enabled" in content[key] and content[key]["queue_file_enabled"]:
                    print(f"Central Broker has queue files on {key}")
                    qf = content[key]["queue_file"]
                    if key not in HIST[1]:
                        HIST[1][key] = []
                    HIST[1][key].append(
                        int(qf["file_expected_terminated_at"]) - int(time.time()))
                    HIST[1][key] = HIST[1][key][-20:]
                    if HIST[1][key][-1] < HIST[1][key][0]:
                        retval &= True
                    retval &= False

    with open("rrd-stats.json") as f:
        retval = True
        # with open("/var/lib/centreon-broker/central-rrd-master-stats.json", "r") as f:
        content = json.load(f)
        # Has Broker Central some queue files?
        for key in content:
            if key.starts_with("endpoint "):
                if "queue_file_enabled" in content[key] and content[key]["queue_file_enabled"]:
                    print(f"RRD Broker has queue files on {key}")
                    qf = content[key]["queue_file"]
                    if key not in HIST[2]:
                        HIST[2][key] = []
                    HIST[2][key].append(
                        int(qf["file_expected_terminated_at"]) - int(time.time()))
                    HIST[2][key] = HIST[2][key][-20:]
                    if HIST[2][key][-1] < HIST[2][key][0]:
                        retval &= True
                    retval &= False
    return retval


def check_centreon():
    now = time.time()
    limit = now + TIMEOUT
    ok = _check_centreon()
    old_ok = ok
    while ok and time.time() < limit:
        time.sleep(10)
        ok = _check_centreon()

    if not ok:
        limit = now + TIMEOUT
        while not ok and time.time() < limit:
            time.sleep(10)
            ok = _check_centreon()

    if ok:
        while ok and (HIST[0][-1] > 10 or HIST[1][-1] > 10 or HIST[2][-1] > 10):
            ok = _check_centreon()
            time.sleep(10)
    return ok


# def check_centreon(v: Vertex):
#    sum = np.sum(v.v) ** 2 + v.services_count ** 2
#    return sum < 50000


def save_row(v: Vertex, data, reduced_data):
    row = {}
    for idx in range(v.dimension()):
        row[reduced_data[idx]["name"]] = v[idx]
    for d in data:
        if d["name"] not in row:
            row[d["name"]] = d["max"]
    row['service'] = v.services_count
    if output == "stdout":
        print(row)
    elif output == "sqlite":
        with sqlite3.connect("bench.db") as con:
            cur = con.cursor()
            keys = row.keys()
            values = map(str, row.values())
            cols = ",".join(keys)
            vals = ",".join(values)
            cur.execute(f"INSERT INTO bench ({cols}) VALUES ({vals})")


def compute_best_services_count(v, min_services_count, max_services_count):
    ok = True
    while max_services_count - min_services_count > 1:
        current_services_count = (min_services_count + max_services_count) // 2
        v.set_services_count(current_services_count)
        generate_conf(v, data, reduced_data)
        call_injector()
        start_centreon()
        ok = check_centreon()
        # ok = check_centreon(v)
        if ok:
            min_services_count = current_services_count
        else:
            max_services_count = current_services_count

    if not ok:
        v.set_services_count(v.services_count - 1)
    elif v.services_count == 0:  # In case we didn't enter into the loop
        v.set_services_count(min_services_count)


# Main program
if __name__ == "__main__":

    # We start to read the configuration to store it into data that is a list
    # of object of the form { "name": str, "value": int }
    with open(f"{INJECTOR_PATH}/data.yaml", "r") as f:
        config = load(f, Loader=Loader)
        idx = 0
        for k, v in config.items():
            if k == 'poller':
                data.append({
                    "name": k,
                    "min": 0,
                    "max": 1,
                    "type": "bool"
                })
            elif k == 'user':
                for u in ["administrators", "editors", "users"]:
                    data.append({
                        "name": u,
                        "min": 1,
                        "max": v[u],
                        "type": "int"
                    })
            else:
                data.append({
                    "name": k,
                    "type": "int",
                    "min": 1,
                    "max": int(v["count"])
                })
            idx += 1

    # The item whose name is 'services' is removed from data and its value is returned in services_count.
    services_count = extract_services(data)

    if output == "sqlite":
        with sqlite3.connect("bench.db") as con:
            cur = con.cursor()
            columns = []
            for t in data:
                name = t["name"]
                columns.append(f"{name} INT")
            cols = ",".join(columns)
            cols += ",service INT"
            cur.execute(f"CREATE TABLE IF NOT EXISTS bench ({cols})")

    # The data set is filtered to keep interesting variables. The resulting data set is just a part of the initial data, we still have names, min, max, etc...
    reduced_data = filter_data(data, filter)

    # We create the set of points from these data. With each value in data, we
    # consider a pair of values: 1 and the given value. For example, if we have
    # 20 users, we consider the pair {1, 20}. These values are our bounds for
    # users. We don't start from 0, this would have no interest.

    # As a concrete example, imagine a configuration with 10 hosts, 15 users
    # and 20 services. We then:
    # * extract the services from data.
    # * create the following points set from hosts and users: { (1, 1), (10, 1), (1, 15), (10, 15) }.
    points = create_points(reduced_data)

    # From the given points, we create simplexes (multidimensional triangles).
    # In our example, we get two triangles:
    # * ((1, 1), (10, 1), (1, 15))
    # * ((10, 1), (1, 15), (10, 15))
    simplexes = init_simplex(points)

    # The idea here is like in fractals. The map is triangulated and then for each vertex we give it an
    # elevation. If the mesh is not fine enough, each simplex is subdivided, and we give to the new vertices
    # a new elevation.
    # In our case, the elevation is not random, it is the greatest number of services for which the platform
    # continues to operate. In our example, if for each pair of (hosts,users), 20 services are good, we'll
    # know that for every number of hosts in range [1,10], users in range [1,15] and services in range [1,20],
    # the platform will operate.
    # On the other hand, if for 15 users the maximum number of services is 2, we can assume that for 7 users
    # we'll have a number of services between 2 and 20.
    # To determine the maximum number of services, we make a dichotomy.

    # All the vertices are stored in the points array. So we just have to compute the maximum elevation for
    # each point in data and this only if no elevation is already there.

    for v in points:
        # we set the max elevation
        if v.services_count != 0:
            continue
        compute_best_services_count(v, 1, services_count)
        save_row(v, data, reduced_data)

    smarter = True
    while smarter:
        smarter = False
        new_simplexes = []
        for s in simplexes:
            ret = s.new_explode(points, 1, services_count)
            if ret is not None:
                smarter = True
                new_simplexes.append(ret)
        simplexes += new_simplexes

#        plt.figure()
#        plt.scatter(reduced_data[0]['max'], reduced_data[1]['max'])
#        for s in simplexes:
#            poly = [
#                [s.data[0][0], s.data[0][1]],
#                [s.data[1][0], s.data[1][1]],
#                [s.data[2][0], s.data[2][1]],
#            ]
#            t = plt.Polygon(poly, color='blue', fill=None)
#            plt.gca().add_patch(t)
#
#        # plt.scatter(X[:, 0], X[:, 1], s=10, color=Y[:])
#
#        # t1 = plt.Polygon(X[:3, :], color=Y[0], fill=None)
#        # plt.gca().add_patch(t1)
#
#        # t2 = plt.Polygon(X[3:6, :], color=Y[3], fill=None)
#        # plt.gca().add_patch(t2)
#
#        plt.show()

x = []
y = []
z = []
for p in points:
    x += [p[0]]
    y += [p[1]]
    z += [p.services_count]

x = np.array(x)
y = np.array(y)
z = np.array(z)

ax = plt.figure().add_subplot(projection='3d')

ax.plot_trisurf(x, y, z, linewidth=0.2, antialiased=True)
ax.set_xlabel(filter[0])
ax.set_ylabel(filter[1])
ax.set_zlabel("service")

plt.show()

print("################################################################")
for p in points:
    lst = []
    for c in p.v:
        lst.append(str(c))
    lst.append(str(p.services_count))
    print(";".join(lst))
