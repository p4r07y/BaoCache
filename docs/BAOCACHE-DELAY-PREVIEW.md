# BaoCache Delay JavaScript preview

Delay JavaScript remains opt-in and only applies to selected independent script
handles. BaoCache always excludes handles with inline/localized data,
conditional/module attributes, or another queued script depending on them.

## Administrator preview

From **BaoCache → Assets → Rules → Delay JavaScript**, choose **Mở preview 30
phút**. It opens the frontend for the current logged-in administrator and sets
a signed, HttpOnly cookie scoped to that account. During that preview only:

- the selected delay handles are delayed for that administrator;
- normal logged-in cache bypass makes the test independent from FastCGI HTML;
- a small local panel reports delayed handle load successes/failures and generic
  JavaScript errors or unhandled Promise rejections; and
- no preview result, browser error message, URL, query, cookie or visitor data
  is sent to WordPress or persisted.

The cookie expires after 30 minutes. Use **Kết thúc preview** to remove it
immediately. Guests and every other administrator continue to receive normal
behaviour throughout the preview.

## Release flow

1. Add only a known independent handle and save the configuration.
2. Run the administrator preview on representative pages.
3. Test the relevant feature: consent flow, menu, form, map, chat or analytics.
4. End preview; then test anonymously on staging before considering production.
5. Restore a BaoCache revision if the rule causes a regression.

The preview helps surface runtime issues; it cannot prove that a delayed script
is safe on every page, device or third-party integration.
