# BaoCache Browser Resource Timing

This optional module adds a tiny frontend script after page load. It is disabled
by default and is meant to guide asset investigation, not to measure Core Web
Vitals or replace RUM/Lighthouse.

## What is collected

One accepted sample per site every 15 minutes contains only grouped values:

- `same-site` or an external asset hostname;
- resource type and broad extension class;
- request count;
- summed browser resource duration; and
- summed transfer bytes where the browser exposes them.

The browser groups entries before sending them. BaoCache does **not** send or
store a resource URL or path, query string, cookie, visitor IP, user ID,
referrer, page URL, browser user agent or Core Web Vitals value. At most 20
groups are accepted per sample and 96 recent samples are retained.

The collector is an external same-site script. Its public endpoint and nonce are
passed through script data attributes, not an inline JavaScript configuration
object; this keeps the module compatible with a stricter CSP.

## How to enable

In BaoCache → Assets → Analysis, enable **Thu thập Resource Timing tổng hợp từ
frontend công khai**, then save the normal BaoCache configuration. Wait for an
anonymous public frontend visit, then return to the same panel to view the
latest accepted sample.

Because public HTML may already be in Nginx FastCGI cache, the collector appears
on a page only after its cached response expires or after that exact test URL is
purged. Do not purge the whole cache merely to collect a sample.

Disabling the setting prevents the script from loading and makes the receiving
endpoint return `404`. Use **Xóa dữ liệu mẫu** in the same panel to immediately
remove all retained summaries and reset the collection rate limit.

## Interpretation limits

Resource durations can overlap, include network/cache effects, and may be
partially hidden for third-party resources without Timing-Allow-Origin. A large
summed duration is a reason to investigate a source or create a safe asset-rule
draft; it is not proof that the resource blocks rendering or is unused.

The module never changes defer, delay, preload, resource hints or unload rules.
All changes still require administrator review, preview and Save.
