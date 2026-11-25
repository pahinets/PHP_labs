"""HTTP integration tests. Run against a local, disposable practicum4 database.

Only records created by this run are deleted (by id AND unique author).
Usage: python tests/test_api.py [http://127.0.0.1:8006]
"""
import json
from pathlib import Path
import subprocess
import sys
import unittest
import urllib.error
import urllib.parse
import urllib.request
import uuid

BASE = (sys.argv.pop(1) if len(sys.argv) > 1 else "http://127.0.0.1:8006").rstrip("/")
ROOT = Path(__file__).resolve().parents[1]


class ApiTest(unittest.TestCase):
    def call(self, params="", method="GET", data=None, content_type=None):
        headers = {}
        if isinstance(data, dict):
            data = json.dumps(data, ensure_ascii=False).encode()
            content_type = content_type or "application/json"
        elif isinstance(data, str):
            data = data.encode()
        if content_type:
            headers["Content-Type"] = content_type
        req = urllib.request.Request(BASE + "/api.php?" + params, data=data,
                                     headers=headers, method=method)
        try:
            response = urllib.request.urlopen(req)
        except urllib.error.HTTPError as exc:
            response = exc
        with response:
            raw = response.read().decode("utf-8")
            return response.status, response.headers, raw

    def check(self, status, params="", method="GET", data=None, content_type=None):
        actual, headers, raw = self.call(params, method, data, content_type)
        self.assertEqual(actual, status, raw[:500])
        self.assertIn("application/json", headers.get("Content-Type", ""))
        body = json.loads(raw)
        self.assertIs(body["success"], status < 400)
        self.assertIn("data" if status < 400 else "error", body)
        if status >= 400:
            self.assertIsInstance(body["error"], str)
            self.assertNotIn("SQLSTATE", raw)
        return body, headers

    def test_list_returns_numeric_fields(self):
        body, _ = self.check(200, "resource=topics")
        self.assertIsInstance(body["data"], list)
        for topic in body["data"]:
            self.assertIs(type(topic["id"]), int)
            self.assertIs(type(topic["is_pinned"]), int)
        ids = [t["id"] for t in body["data"]]
        self.assertEqual(ids, sorted(ids, reverse=True))

    def test_route_validation(self):
        cases = [
            (404, "", "GET"), (404, "resource=users", "GET"),
            (400, "resource[]=topics", "GET"),
            (400, "resource=topics&action[]=pin", "POST"),
            (404, "resource=topics&action=delete&id=1", "POST"),
            (400, "resource=topics&id=0", "GET"),
            (400, "resource=topics&id=-1", "GET"),
            (400, "resource=topics&id=abc", "GET"),
            (400, "resource=topics&id=1.5", "GET"),
            (400, "resource=topics&id=", "GET"),
            (400, "resource=topics&id[]=1", "GET"),
            (400, "resource=topics&id=9999999999999999999999", "GET"),
            (400, "resource=topics&q[]=a", "GET"),
            (400, "resource=topics&action=pin", "POST"),
            (404, "resource=topics&id=2147483647", "GET"),
            (404, "resource=topics&action=pin&id=2147483647", "POST"),
        ]
        for status, params, method in cases:
            with self.subTest(params=params, method=method):
                self.check(status, params, method)

    def test_methods_and_allow(self):
        for params, method, allow in [
            ("resource=topics", "PUT", "GET, POST"),
            ("resource=topics&id=1", "POST", "GET"),
            ("resource=topics&id=1", "DELETE", "GET"),
            ("resource=topics&id=1&action=pin", "GET", "POST"),
            ("resource=topics&id=1&action=pin", "PATCH", "POST"),
        ]:
            with self.subTest(params=params, method=method):
                _, headers = self.check(405, params, method)
                self.assertEqual(headers["Allow"], allow)

    def test_invalid_body_and_fields(self):
        for raw in ["{", "[]", "null", '""', "", "42"]:
            with self.subTest(raw=raw):
                self.check(400, "resource=topics", "POST", raw, "application/json")
        self.check(400, "resource=topics", "POST", "hello", "text/plain")
        invalid = [
            {}, {"title": "abcd", "author": "a"}, {"title": "a" * 101, "author": "a"},
            {"title": "Valid title", "author": ""}, {"title": "Valid title", "author": "a" * 51},
            {"title": [], "author": "a"}, {"title": "Valid title", "author": {}},
            {"title": 12345, "author": "a"}, {"title": "     ", "author": "a"},
            {"title": "Valid title", "author": "a", "is_pinned": 2},
            {"title": "Valid title", "author": "a", "is_pinned": []},
        ]
        for data in invalid:
            with self.subTest(data=data):
                body, _ = self.check(400, "resource=topics", "POST", data)
                self.assertIsInstance(body["errors"], dict)
        self.check(400, "resource=topics", "POST", "title[]=abcde&author=test",
                   "application/x-www-form-urlencoded")

    def test_explicit_null_pinned_is_rejected(self):
        author = "lab6_null_" + uuid.uuid4().hex[:12]
        status, _, raw = self.call("resource=topics", "POST",
                                  {"title": "Valid title", "author": author, "is_pinned": None})
        body = json.loads(raw)
        try:
            self.assertEqual(status, 400, raw)
            self.assertIs(body["success"], False)
            self.assertIn("is_pinned", body["errors"])
            rows, _ = self.check(200, "resource=topics&q=" + urllib.parse.quote(author))
            self.assertEqual(rows["data"], [])
        finally:
            # Also clean the unexpected creation when reproducing the regression.
            if status == 201:
                topic_id = int(body["data"]["id"])
                code = (
                    "$pdo=require 'db.php';"
                    "$s=$pdo->prepare('DELETE FROM topics WHERE id=? AND author=?');"
                    f"$s->execute([{topic_id},'{author}']);"
                )
                subprocess.run(["php", "-r", code], cwd=ROOT, check=True)

    def test_create_get_filter_pin_and_form(self):
        author = "lab6_test_" + uuid.uuid4().hex[:12]
        ids = []
        try:
            # Detects wrong JSON encoding, omitted INSERT/defaults and missing trimming.
            body, headers = self.check(201, "resource=topics", "POST",
                                       {"title": "  П'ять  ", "author": author})
            topic = body["data"]
            ids.append(topic["id"])
            self.assertEqual(topic["title"], "П'ять")
            self.assertEqual(topic["author"], author)
            self.assertEqual(topic["is_pinned"], 0)
            self.assertRegex(topic["created_at"], r"^\d{4}-\d{2}-\d{2}$")
            self.assertIn("id=" + str(topic["id"]), headers["Location"])
            one, _ = self.check(200, f"resource=topics&id={ids[0]}")
            self.assertEqual(one["data"], topic)
            # Repeated pin must not use affected-row count as an existence check.
            for _ in range(2):
                pinned, _ = self.check(200, f"resource=topics&id={ids[0]}&action=pin", "POST")
                self.assertEqual(pinned["data"]["is_pinned"], 1)
            one, _ = self.check(200, f"resource=topics&id={ids[0]}")
            self.assertEqual(one["data"]["is_pinned"], 1)
            # Boundary length 100 Unicode characters, and a traditional form.
            form = urllib.parse.urlencode({"title": "Ї" * 100, "author": author, "is_pinned": "1"})
            body, _ = self.check(201, "resource=topics", "POST", form,
                                 "application/x-www-form-urlencoded")
            ids.append(body["data"]["id"])
            self.assertEqual(body["data"]["title"], "Ї" * 100)
            self.assertEqual(body["data"]["is_pinned"], 1)
            rows, _ = self.check(200, "resource=topics&q=" + urllib.parse.quote(author))
            self.assertEqual([r["id"] for r in rows["data"]], list(reversed(ids)))
            # Apostrophe/SQL-like input remains data, not a WHERE-clause bypass.
            rows, _ = self.check(200, "resource=topics&q=" + urllib.parse.quote("' OR 1=1 --"))
            self.assertEqual(rows["data"], [])
            # Literal underscore is not SQL LIKE's single-character wildcard.
            rows, _ = self.check(200, "resource=topics&q=" + urllib.parse.quote(author.replace("_", "%")))
            self.assertEqual(rows["data"], [])
        finally:
            if ids:
                # Cleanup is test-only, never exposed through an API endpoint.
                code = (
                    "$pdo=require 'db.php';"
                    "$s=$pdo->prepare('DELETE FROM topics WHERE id=? AND author=?');"
                    f"foreach(json_decode('{json.dumps(ids)}') as $id)"
                    "{$s->execute([$id,'" + author + "']);}"
                )
                subprocess.run(["php", "-r", code], cwd=ROOT, check=True)


if __name__ == "__main__":
    unittest.main(verbosity=2)
