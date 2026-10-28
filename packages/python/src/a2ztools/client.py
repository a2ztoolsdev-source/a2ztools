"""Client for the A2Z Tools public API (https://a2z.tools/developers). Standard library only."""
from __future__ import annotations

import json
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from datetime import datetime, timezone
from typing import Any, Dict, Mapping, Optional

from . import _endpoints
from ._version import __version__

DEFAULT_BASE_URL = "https://a2z.tools/public-api/v1"


class A2zApiError(Exception):
    """An error answer from the API (4xx/5xx), or a request that never got one."""

    def __init__(self, message: str, status: int = 0, code: Optional[str] = None,
                 retry_after: Optional[int] = None, body: Any = None) -> None:
        super().__init__(message)
        #: HTTP status, or 0 when there was no answer.
        self.status = status
        #: missing_parameter, invalid_key, key_suspended, key_revoked, key_required,
        #: rate_limited, daily_quota, target_rate_limited, api_disabled, network_error
        self.code = code
        #: Seconds to wait before retrying (429 / 503).
        self.retry_after = retry_after
        self.body = body


@dataclass
class RateLimit:
    limit: int
    remaining: Optional[int]
    #: Next reset (00:00 UTC).
    reset: Optional[datetime]
    #: True when the answer came from the API's cache.
    cached: bool


def _int(value: Optional[str]) -> Optional[int]:
    try:
        return int(value) if value not in (None, "") else None
    except ValueError:
        return None


class A2zClient:
    """Free website, DNS, SSL, email-authentication and SEO checks.

    >>> client = A2zClient(api_key="a2z_...")      # the key is optional
    >>> client.ssl.certificate(host="example.com")["result"]["daysRemaining"]

    Each check returns the decoded JSON answer. ``ok`` is False when the check ran but
    found a problem (for example no record); API errors raise :class:`A2zApiError`.
    """

    def __init__(self, api_key: Optional[str] = None, *, site: Optional[str] = None,
                 base_url: str = DEFAULT_BASE_URL, timeout: float = 30.0, opener: Any = None) -> None:
        self._api_key = api_key
        self._site_host = site
        self._base_url = base_url.rstrip("/")
        self._timeout = timeout
        self._opener = opener or urllib.request.build_opener()
        #: Limits from the most recent response.
        self.last_rate_limit: Optional[RateLimit] = None
        for name, cls in _endpoints.GROUPS.items():
            setattr(self, name, cls(self))

    def call(self, group: str, name: str, params: Optional[Mapping[str, Any]] = None) -> Dict[str, Any]:
        """Calls any endpoint by group and name, e.g. ``call("ssl", "certificate", {"host": "example.com"})``."""
        query = {k: str(v) for k, v in (params or {}).items() if v is not None and v != ""}
        url = f"{self._base_url}/{group}/{name}"
        if query:
            url += "?" + urllib.parse.urlencode(query)

        headers = {"Accept": "application/json", "User-Agent": f"a2ztools-python/{__version__}"}
        if self._api_key:
            headers["X-API-Key"] = self._api_key
        if self._site_host:
            headers["X-A2Z-Site"] = self._site_host

        request = urllib.request.Request(url, headers=headers, method="GET")
        try:
            response = self._opener.open(request, timeout=self._timeout)
            status, resp_headers, raw = response.status, response.headers, response.read()
        except urllib.error.HTTPError as err:
            status, resp_headers, raw = err.code, err.headers, err.read()
        except (urllib.error.URLError, OSError) as err:
            raise A2zApiError(f"Request to {group}/{name} failed: {err}", 0, "network_error") from err

        self.last_rate_limit = self._rate_limit(resp_headers) or self.last_rate_limit
        try:
            body = json.loads(raw.decode("utf-8")) if raw else None
        except ValueError:
            body = None

        if status >= 400:
            message = body.get("error") if isinstance(body, dict) else None
            code = body.get("code") if isinstance(body, dict) else None
            raise A2zApiError(message or f"HTTP {status}", status, code, _int(resp_headers.get("Retry-After")), body)
        return body

    @staticmethod
    def _rate_limit(headers: Any) -> Optional[RateLimit]:
        limit = _int(headers.get("X-RateLimit-Limit"))
        if limit is None:
            return None
        reset = _int(headers.get("X-RateLimit-Reset"))
        return RateLimit(
            limit=limit,
            remaining=_int(headers.get("X-RateLimit-Remaining")),
            reset=datetime.fromtimestamp(reset, tz=timezone.utc) if reset is not None else None,
            cached=headers.get("X-A2Z-Cache") == "HIT",
        )
