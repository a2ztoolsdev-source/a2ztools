using System;
using System.Collections.Generic;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Reflection;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using A2zTools.Api;
using Xunit;

public class ClientTests
{
    private sealed class FakeHandler : HttpMessageHandler
    {
        private readonly HttpStatusCode _status;
        private readonly string _body;
        private readonly Dictionary<string, string> _headers;
        public readonly List<HttpRequestMessage> Requests = new List<HttpRequestMessage>();

        public FakeHandler(HttpStatusCode status, string body, Dictionary<string, string>? headers = null)
        {
            _status = status;
            _body = body;
            _headers = headers ?? new Dictionary<string, string>();
        }

        protected override Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
        {
            Requests.Add(request);
            var r = new HttpResponseMessage(_status) { Content = new StringContent(_body, Encoding.UTF8, "application/json") };
            foreach (var h in _headers) r.Headers.TryAddWithoutValidation(h.Key, h.Value);
            return Task.FromResult(r);
        }
    }

    private static (A2zClient, FakeHandler) Client(HttpStatusCode status, string body, Dictionary<string, string>? headers = null, A2zClientOptions? options = null)
    {
        var h = new FakeHandler(status, body, headers);
        return (new A2zClient(new HttpClient(h), options), h);
    }

    [Fact]
    public async Task Builds_the_url_sends_the_key_and_reads_the_limits()
    {
        var (c, h) = Client(HttpStatusCode.OK, "{\"ok\":true,\"domain\":\"example.com\"}",
            new Dictionary<string, string> { ["X-RateLimit-Limit"] = "2000", ["X-RateLimit-Remaining"] = "1999", ["X-RateLimit-Reset"] = "1793232000", ["X-A2Z-Cache"] = "HIT" },
            new A2zClientOptions { ApiKey = "a2z_test", SiteHost = "shop.example" });

        var r = await c.Dns.RecordsAsync("example.com", "MX");

        Assert.True(r.Ok);
        Assert.Equal("example.com", r.Data.GetProperty("domain").GetString());
        var req = h.Requests.Single();
        Assert.Equal("https://a2z.tools/public-api/v1/dns/records?domain=example.com&type=MX", req.RequestUri!.ToString());
        Assert.Equal("a2z_test", req.Headers.GetValues("X-API-Key").Single());
        Assert.Equal("shop.example", req.Headers.GetValues("X-A2Z-Site").Single());
        Assert.Equal(2000, c.LastRateLimit!.Limit);
        Assert.Equal(1999, c.LastRateLimit.Remaining);
        Assert.True(c.LastRateLimit.Cached);
        Assert.Equal(new DateTimeOffset(2026, 10, 29, 0, 0, 0, TimeSpan.Zero), c.LastRateLimit.Reset);
    }

    [Fact]
    public async Task Optional_parameters_are_left_out()
    {
        var (c, h) = Client(HttpStatusCode.OK, "{\"ok\":true}", options: new A2zClientOptions { BaseUrl = new Uri("http://localhost:1/public-api/v1") });
        await c.Ssl.CertificateAsync("a2z.tools");
        await c.Ssl.CertificateAsync("a2z.tools", 8443);
        Assert.Equal("http://localhost:1/public-api/v1/ssl/certificate?host=a2z.tools", h.Requests[0].RequestUri!.ToString());
        Assert.Equal("http://localhost:1/public-api/v1/ssl/certificate?host=a2z.tools&port=8443", h.Requests[1].RequestUri!.ToString());
    }

    [Fact]
    public async Task Errors_throw_with_code_and_retry_after()
    {
        var (c, _) = Client((HttpStatusCode)429, "{\"ok\":false,\"code\":\"rate_limited\",\"error\":\"Too many requests\"}",
            new Dictionary<string, string> { ["Retry-After"] = "17" });
        var ex = await Assert.ThrowsAsync<A2zApiException>(() => c.Seo.LlmsTxtAsync("x.com"));
        Assert.Equal(429, ex.Status);
        Assert.Equal("rate_limited", ex.Code);
        Assert.Equal(17, ex.RetryAfterSeconds);
        Assert.Equal("Too many requests", ex.Message);
    }

    [Fact]
    public async Task A_check_that_found_nothing_is_not_an_exception()
    {
        var (c, _) = Client(HttpStatusCode.OK, "{\"ok\":false,\"error\":\"Not found (HTTP 404)\"}");
        var r = await c.Seo.LlmsTxtAsync("example.com");
        Assert.False(r.Ok);
        Assert.Equal("Not found (HTTP 404)", r.Error);
    }

    [Fact]
    public void Every_group_has_methods()
    {
        var groups = typeof(A2zClient).GetProperties().Where(p => p.PropertyType.Name.EndsWith("Group")).ToList();
        Assert.Equal(7, groups.Count);
        var methods = groups.SelectMany(g => g.PropertyType.GetMethods(BindingFlags.Public | BindingFlags.Instance | BindingFlags.DeclaredOnly)).Count();
        Assert.True(methods >= 60, methods.ToString());
    }
}
