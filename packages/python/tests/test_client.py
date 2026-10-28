import io
import json
import urllib.error
from email.message import Message

import pytest

from a2ztools import A2zApiError, A2zClient


class FakeResponse:
    def __init__(self, status, body, headers):
        self.status = status
        self.headers = headers
        self._body = body

    def read(self):
        return self._body


def make_headers(values):
    m = Message()
    for k, v in values.items():
        m[k] = v
    return m


class FakeOpener:
    def __init__(self, status=200, body=None, headers=None):
        self.status = status
        self.body = json.dumps(body if body is not None else {"ok": True}).encode()
        self.headers = make_headers(headers or {})
        self.requests = []

    def open(self, request, timeout=None):
        self.requests.append(request)
        if self.status >= 400:
            raise urllib.error.HTTPError(request.full_url, self.status, "error", self.headers, io.BytesIO(self.body))
        return FakeResponse(self.status, self.body, self.headers)


def test_every_group_is_attached():
    c = A2zClient(opener=FakeOpener())
    for group in ("ssl", "dns", "email", "domain", "http", "seo", "site"):
        assert hasattr(c, group)
    assert callable(c.site.health)
    assert callable(c.email.security)


def test_url_headers_and_rate_limit():
    o = FakeOpener(body={"ok": True, "domain": "example.com"}, headers={
        "X-RateLimit-Limit": "2000", "X-RateLimit-Remaining": "1999",
        "X-RateLimit-Reset": "1793232000", "X-A2Z-Cache": "HIT"})
    c = A2zClient("a2z_test", site="shop.example", opener=o)
    r = c.dns.records("example.com", type="MX")
    assert r["domain"] == "example.com"
    req = o.requests[0]
    assert req.full_url == "https://a2z.tools/public-api/v1/dns/records?domain=example.com&type=MX"
    assert req.get_header("X-api-key") == "a2z_test"
    assert req.get_header("X-a2z-site") == "shop.example"
    assert c.last_rate_limit.limit == 2000
    assert c.last_rate_limit.remaining == 1999
    assert c.last_rate_limit.cached is True
    assert c.last_rate_limit.reset.isoformat() == "2026-10-29T00:00:00+00:00"


def test_optional_parameters_are_left_out_and_snake_case_maps_back():
    o = FakeOpener()
    c = A2zClient(opener=o, base_url="http://localhost:1/public-api/v1/")
    c.ssl.certificate("a2z.tools")
    c.email.security("google.com", dkim_selector="google")
    assert o.requests[0].full_url == "http://localhost:1/public-api/v1/ssl/certificate?host=a2z.tools"
    assert o.requests[1].full_url.endswith("/email/security?domain=google.com&dkimSelector=google")


def test_errors_raise_with_code_and_retry_after():
    o = FakeOpener(status=429, body={"ok": False, "code": "rate_limited", "error": "Too many requests"}, headers={"Retry-After": "17"})
    c = A2zClient(opener=o)
    with pytest.raises(A2zApiError) as e:
        c.seo.llms_txt("x.com")
    assert e.value.status == 429
    assert e.value.code == "rate_limited"
    assert e.value.retry_after == 17
    assert str(e.value) == "Too many requests"


def test_a_check_that_found_nothing_is_not_an_error():
    c = A2zClient(opener=FakeOpener(body={"ok": False, "error": "Not found (HTTP 404)"}))
    assert c.seo.llms_txt("example.com")["ok"] is False


def test_network_errors():
    class Broken:
        def open(self, request, timeout=None):
            raise urllib.error.URLError("no route")

    with pytest.raises(A2zApiError) as e:
        A2zClient(opener=Broken()).http.status("https://x.com")
    assert e.value.code == "network_error"
    assert e.value.status == 0
