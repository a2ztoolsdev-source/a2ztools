#nullable enable
using System;
using System.Collections.Generic;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace A2zTools.Api
{
    /// <summary>Settings for <see cref="A2zClient"/>.</summary>
    public sealed class A2zClientOptions
    {
        /// <summary>Free key from https://a2z.tools/developers. Optional: without one you get 10 calls a minute, 100 a day.</summary>
        public string? ApiKey { get; set; }

        /// <summary>Your website's host (example.com), sent as X-A2Z-Site. For plugins that call without a key.</summary>
        public string? SiteHost { get; set; }

        /// <summary>Defaults to https://a2z.tools/public-api/v1.</summary>
        public Uri BaseUrl { get; set; } = new Uri(A2zClient.DefaultBaseUrl);

        /// <summary>Per-request timeout. Default 30 seconds.</summary>
        public TimeSpan Timeout { get; set; } = TimeSpan.FromSeconds(30);
    }

    /// <summary>Limits reported by the most recent response.</summary>
    public sealed class A2zRateLimit
    {
        public int Limit { get; internal set; }
        public int? Remaining { get; internal set; }
        /// <summary>Next reset (00:00 UTC).</summary>
        public DateTimeOffset? Reset { get; internal set; }
        /// <summary>True when the answer came from the API's cache.</summary>
        public bool Cached { get; internal set; }
    }

    /// <summary>
    /// One answer. <see cref="Ok"/> is false when the check ran but found a problem (for example no record);
    /// errors such as a bad key or a rate limit throw <see cref="A2zApiException"/> instead.
    /// </summary>
    public sealed class A2zResponse
    {
        internal A2zResponse(JsonElement data, A2zRateLimit? rateLimit)
        {
            Data = data;
            RateLimit = rateLimit;
        }

        /// <summary>The whole JSON answer.</summary>
        public JsonElement Data { get; }

        public A2zRateLimit? RateLimit { get; }

        public bool Ok => Data.ValueKind == JsonValueKind.Object && Data.TryGetProperty("ok", out var ok) && ok.ValueKind == JsonValueKind.True;

        public string? Error => Data.ValueKind == JsonValueKind.Object && Data.TryGetProperty("error", out var e) && e.ValueKind == JsonValueKind.String ? e.GetString() : null;

        /// <summary>Deserialises the answer into your own type.</summary>
        public T? As<T>(JsonSerializerOptions? options = null) => JsonSerializer.Deserialize<T>(Data.GetRawText(), options ?? new JsonSerializerOptions { PropertyNameCaseInsensitive = true });

        public override string ToString() => Data.GetRawText();
    }

    /// <summary>An error answer from the API (4xx/5xx), or a request that never got one.</summary>
    public sealed class A2zApiException : Exception
    {
        public A2zApiException(string message, int status, string? code, int? retryAfterSeconds, Exception? inner = null) : base(message, inner)
        {
            Status = status;
            Code = code;
            RetryAfterSeconds = retryAfterSeconds;
        }

        /// <summary>HTTP status, or 0 when there was no answer.</summary>
        public int Status { get; }

        /// <summary>missing_parameter, invalid_key, key_suspended, key_revoked, key_required, rate_limited, daily_quota, target_rate_limited, api_disabled, network_error.</summary>
        public string? Code { get; }

        /// <summary>Seconds to wait before retrying (429 / 503).</summary>
        public int? RetryAfterSeconds { get; }
    }

    /// <summary>
    /// Client for the A2Z Tools public API (https://a2z.tools/developers): SSL, DNS, WHOIS, email
    /// authentication, HTTP headers, SEO and site-health checks. Thread-safe; reuse one instance.
    /// </summary>
    public partial class A2zClient : IDisposable
    {
        public const string DefaultBaseUrl = "https://a2z.tools/public-api/v1/";
        public const string Version = "1.0.0";

        private readonly HttpClient _http;
        private readonly bool _ownsHttp;
        private readonly A2zClientOptions _options;

        public A2zClient(A2zClientOptions? options = null) : this(new HttpClient(), options, true) { }

        /// <summary>Uses your HttpClient (for IHttpClientFactory). Its BaseAddress and Timeout are left alone.</summary>
        public A2zClient(HttpClient httpClient, A2zClientOptions? options = null) : this(httpClient, options, false) { }

        private A2zClient(HttpClient http, A2zClientOptions? options, bool owns)
        {
            _http = http ?? throw new ArgumentNullException(nameof(http));
            _ownsHttp = owns;
            _options = options ?? new A2zClientOptions();
        }

        /// <summary>Limits from the most recent response.</summary>
        public A2zRateLimit? LastRateLimit { get; private set; }

        /// <summary>Calls any endpoint by group and name, for example ("ssl", "certificate").</summary>
        public async Task<A2zResponse> CallAsync(string group, string name, IDictionary<string, string?> parameters, CancellationToken cancellationToken = default)
        {
            var query = string.Join("&", parameters
                .Where(p => !string.IsNullOrEmpty(p.Value))
                .Select(p => Uri.EscapeDataString(p.Key) + "=" + Uri.EscapeDataString(p.Value!)));
            var baseUrl = _options.BaseUrl.ToString();
            if (!baseUrl.EndsWith("/", StringComparison.Ordinal)) baseUrl += "/";
            var url = new Uri(baseUrl + group + "/" + name + (query.Length > 0 ? "?" + query : ""));

            using var request = new HttpRequestMessage(HttpMethod.Get, url);
            request.Headers.Accept.ParseAdd("application/json");
            request.Headers.UserAgent.ParseAdd("a2ztools-dotnet/" + Version);
            if (!string.IsNullOrEmpty(_options.ApiKey)) request.Headers.TryAddWithoutValidation("X-API-Key", _options.ApiKey);
            if (!string.IsNullOrEmpty(_options.SiteHost)) request.Headers.TryAddWithoutValidation("X-A2Z-Site", _options.SiteHost);

            using var timeout = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken);
            timeout.CancelAfter(_options.Timeout);

            HttpResponseMessage response;
            try
            {
                response = await _http.SendAsync(request, timeout.Token).ConfigureAwait(false);
            }
            catch (Exception ex) when (ex is HttpRequestException || (ex is OperationCanceledException && !cancellationToken.IsCancellationRequested))
            {
                throw new A2zApiException("Request to " + group + "/" + name + " failed: " + ex.Message, 0, "network_error", null, ex);
            }

            using (response)
            {
                var rate = ReadRateLimit(response);
                LastRateLimit = rate ?? LastRateLimit;
                var text = response.Content == null ? "" : await response.Content.ReadAsStringAsync().ConfigureAwait(false);
                JsonElement data = default;
                if (!string.IsNullOrWhiteSpace(text))
                {
                    try
                    {
                        using var doc = JsonDocument.Parse(text);
                        data = doc.RootElement.Clone();
                    }
                    catch (JsonException) { /* not JSON */ }
                }

                if (!response.IsSuccessStatusCode)
                {
                    string? code = null, message = null;
                    if (data.ValueKind == JsonValueKind.Object)
                    {
                        if (data.TryGetProperty("code", out var c) && c.ValueKind == JsonValueKind.String) code = c.GetString();
                        if (data.TryGetProperty("error", out var m) && m.ValueKind == JsonValueKind.String) message = m.GetString();
                    }
                    int? retry = null;
                    if (response.Headers.RetryAfter?.Delta is TimeSpan d) retry = (int)d.TotalSeconds;
                    throw new A2zApiException(message ?? "HTTP " + (int)response.StatusCode, (int)response.StatusCode, code, retry);
                }
                return new A2zResponse(data, rate);
            }
        }

        private static A2zRateLimit? ReadRateLimit(HttpResponseMessage r)
        {
            int? Int(string name) => r.Headers.TryGetValues(name, out var v) && int.TryParse(v.FirstOrDefault(), out var n) ? n : (int?)null;
            var limit = Int("X-RateLimit-Limit");
            if (limit == null) return null;
            long? reset = r.Headers.TryGetValues("X-RateLimit-Reset", out var rv) && long.TryParse(rv.FirstOrDefault(), out var rr) ? rr : (long?)null;
            return new A2zRateLimit
            {
                Limit = limit.Value,
                Remaining = Int("X-RateLimit-Remaining"),
                Reset = reset == null ? (DateTimeOffset?)null : DateTimeOffset.FromUnixTimeSeconds(reset.Value),
                Cached = r.Headers.TryGetValues("X-A2Z-Cache", out var cv) && cv.FirstOrDefault() == "HIT",
            };
        }

        public void Dispose()
        {
            if (_ownsHttp) _http.Dispose();
        }
    }
}
