# BaoCache — vận hành và cấu hình

BaoCache là engine hiệu năng WordPress độc lập cho stack này. Core không phụ
thuộc vào domain, `power-schedule-manager`, Blocksy, Astra, child theme hoặc
bất kỳ plugin riêng nào.

Mọi recommendation của core phải xuất phát từ evidence quan sát được: route,
asset handle/dependency, HTTP response, MIME type, DOM marker, cache header,
CSP report hoặc frontend probe. Cấu hình riêng của từng website thuộc **Site
Overrides**, không thuộc core và không được đóng gói làm mặc định.

## Kiến trúc cache

```text
Khách truy cập → Cloudflare (nếu dùng) → Nginx FastCGI cache → WordPress
                                                        └→ Redis Object Cache
```

- **Nginx** là HTML/page cache duy nhất. TTL mặc định do `nginx/default.conf`
  quyết định; BaoCache chỉ có thể gửi TTL `X-Accel-Expires` cho các trang công
  khai nếu bạn chủ động đặt giá trị khác 0.
- **Redis Object Cache** là object cache duy nhất. BaoCache chỉ kiểm tra trạng
  thái và có nút flush object cache theo namespace Redis hiện tại.
- BaoCache không tạo page cache thứ hai hay dịch vụ cloud. Khi deploy cùng image
  Nginx BaoCache, nó có purge API thật cho **một URL cùng website**. Mật khẩu
  endpoint được tự sinh trong Docker, không cần nhập token vào Coolify và không
  hiển thị trong wp-admin.

## Kích hoạt trên Coolify

`docker-compose.yml` đã có:

```yaml
AUTO_ACTIVATE_BUNDLED_PLUGINS: "redis-cache power-schedule-manager baocache"
```

Sau khi push Git và deploy, vào **WordPress Admin → BaoCache**. Trên site mới,
hoàn tất trình cài WordPress rồi redeploy hoặc khởi động lại service `wordpress`
để bước tự kích hoạt chạy.

## Dùng dashboard đúng cách

Sidebar được chia thành Performance (Tổng quan, Cache, Assets, Resource Hints),
WordPress, Operations (Warmup, Diagnostics, Logs) và Integrations (Cloudflare).
Các nhóm chỉ là lớp điều hướng; BaoCache vẫn lưu toàn bộ option trong một giao
dịch nguyên tử để không tạo trạng thái cấu hình dở dang giữa cache và hardening.

BaoCache không dùng một “điểm hiệu năng” chung. Dashboard tách trạng thái cấu
hình, runtime đã xác minh, field data và lab data. Nó không gọi các số liệu đó
là Core Web Vitals hay PageSpeed khi chưa có nguồn đo thực tế.

1. Xác nhận Redis và PhpRedis đều xanh.
2. Giữ TTL BaoCache bằng `0` trừ khi có lý do đo được để thay đổi.
3. Chỉ thêm preload khi đó là tài nguyên LCP thật sự; preload sai có thể làm
   trang chậm hơn.
4. Dùng defer và Script Manager trên staging trước. Nhập **handle WordPress**,
   không phải URL file. Plugin sẽ không dequeue handle đang là dependency của
   một asset được xếp hàng khác.
5. Mở “Báo cáo kỹ thuật” khi cần gửi thông tin cho người hỗ trợ.
6. Dùng “Purge một URL FastCGI” sau khi redeploy Nginx. Không có tùy chọn purge
   wildcard/toàn cache vì thao tác đó quá rộng trên production.

Header Inspector là một request không đăng nhập tới URL cùng domain. Nó hiển thị
PASS/WARN/FAIL cùng FastCGI, Cloudflare, Cache-Control, Age, ETag, compression
và các header có trên response; đây không phải hit ratio 24 giờ. BaoCache cũng
lưu một snapshot runtime mỗi giờ, tối đa 30 ngày. Biểu đồ 24h/7d/30d chỉ được
thêm sau khi đã có đủ snapshot thật.

## Critical Resource Diagnostics

Trong tab **Resource Hints**, mục này chỉ kiểm tra cấu hình mà BaoCache có thể
phát ra HTML: URL preload có thuộc font/CSS/JavaScript/ảnh được hỗ trợ không,
origin nào đang vừa `preconnect` vừa `dns-prefetch`, và LCP assistance có được
cấu hình không. Nó không tự gọi ảnh là LCP, không kết luận render-blocking và
không thay thế PageSpeed, DevTools hoặc dữ liệu field.

## WordPress Hardening và ranh giới bảo mật

Tab **WordPress** có các toggle opt-in cho XML-RPC, self pingback, trackbacks, ẩn
chi tiết lỗi đăng nhập, RSD, WLW, shortlink/generator, X-Pingback, REST user
enumeration, RSS Feed, Application Passwords và file editor. RSS có ba chính
sách: giữ feed, redirect về trang chủ hoặc trả `410 Gone`. Redirect attachment
pages và author archives mặc định tắt vì có thể thay đổi SEO hoặc luồng nội
dung. BaoCache không phải WAF và không quét malware.

`Remove Feed Links` chỉ gỡ các `<link rel="alternate">` trong HTML; `Remove
REST API Discovery Link` chỉ gỡ `<link rel="https://api.w.org/">` và header
discovery. Cả hai không tắt endpoint REST.

**Hardening Verification** hiển thị từng policy bằng PASS/WARN/INFO. Đây là
kiểm tra cấu hình và hook runtime, không phải điểm bảo mật; muốn xác minh HTML
hoặc header public sau khi bật tùy chọn, dùng Header Inspector và purge URL
FastCGI tương ứng.

Nút **Probe public response** thực hiện một request không đăng nhập tới trang
chủ, feed và `/wp-json/wp/v2/users`. Probe chỉ cho phép cùng domain, không gửi
cookie, không theo redirect và chỉ trả về status/header cần thiết cùng thời
gian tổng hợp; response body không được lưu hoặc ghi log.

BaoCache giữ tối đa 10 snapshot probe đã rút gọn để hiển thị lần kiểm tra gần
nhất và ghi một dòng aggregate vào Logs. Snapshot chỉ gồm timestamp, số PASS,
nhãn/trạng thái kiểm tra và timing; không có URL, body, cookie hoặc token.

Probe Diff chỉ cảnh báo khi một check chuyển từ `PASS` sang `WARN/INFO`; thay
đổi timing không tự tạo regression. Các cải thiện được hiển thị riêng để phân
biệt hồi quy cấu hình với dao động mạng.

### Probe Schedule & Staging Baseline

Trong mục **WordPress → Hardening Verification**, probe có thể để `Manual only`
hoặc bật lịch mỗi giờ, mỗi 6 giờ hay mỗi ngày. Lịch chỉ được tạo khi cả toggle
và schedule được bật; BaoCache kiểm tra schedule trước khi đăng ký WP-Cron và
không chạy song song nếu một probe trước đó vẫn còn lock.

Hãy chạy một probe sau khi kiểm tra staging, xác nhận không có `WARN/FAIL`, rồi
bấm **Đặt baseline từ probe PASS**. BaoCache chỉ lưu các nhãn/trạng thái và timing
đã rút gọn; không lưu URL, response body, cookie hay token. Các probe sau sẽ so
sánh với baseline này thay vì lấy một lần probe lỗi làm chuẩn. Nếu chưa đặt
baseline, probe dùng snapshot gần nhất để phát hiện thay đổi. Baseline được gắn
với `wp_get_environment_type()`; khi database được chuyển giữa `staging` và
`production`, baseline khác môi trường sẽ không được dùng làm chuẩn.

### Probe History và xác nhận cảnh báo

BaoCache giữ tối đa 10 snapshot probe đã rút gọn. Bảng **Probe History** phân
biệt probe thủ công và probe định kỳ, hiển thị thời gian phản hồi, số PASS và số
regression/improvement. Nút **Xác nhận đã xem** chỉ ghi nhận rằng quản trị viên
đã xem regression; snapshot, chi tiết regression và Activity Log vẫn được giữ.

## Render Blocking Optimization

Trong **Assets → Analysis**, BaoCache có thể nhận JSON Lighthouse/PageSpeed do
quản trị viên xuất từ công cụ đo. Plugin đọc các audit render-blocking và các
metric FCP, LCP, CLS, TBT; nó không tự gọi PageSpeed. URL resource được map theo
path/host với Asset Inventory hiện tại để tìm WordPress handle. Resource chỉ có
URL mà không map được sẽ được giữ ở trạng thái “URL only” và không tự sửa.

Nút **Preview** chỉ kiểm tra handle có inline/localized code, module/conditional
metadata và dependency hiện tại. Khi đã xác minh, defer dùng strategy API của
WordPress, không regex thẻ script. CSS async chỉ hoạt động với các style handle
được nhập thủ công, dùng runner JavaScript ngoài để không cần inline event handler
và luôn có `noscript` fallback. Exclusion theo handle, URL
prefix và context thắng mọi strategy; authenticated, admin, preview và checkout
được loại trừ mặc định.

Màn **Context QA** cho phép kiểm tra path, handle và các cờ preview trước khi
đưa strategy vào staging. Kết quả PASS/BYPASS ghi lý do rõ ràng vào Activity Log
nhưng không tự thay đổi cấu hình. **Strategy Ledger** giữ tối đa 100 entry đã
rút gọn, gồm strategy, context, lý do và trạng thái rollback; nếu handle trong
cấu hình không còn xuất hiện ở Asset Inventory, BaoCache chỉ cảnh báo để quản
trị viên rà soát, không tự xoá rule.

Trong **Diagnostics → Staging Compatibility QA**, quản trị viên có checklist
thủ công cho menu, form, map, analytics/consent, chat, checkout, login và
rollback. BaoCache chỉ lưu trạng thái PASS/FAIL/bỏ qua, môi trường và phiên bản
plugin; không giả lập trình duyệt và không lưu URL, cookie, token hay payload
bên thứ ba. Chỉ bật strategy production sau khi các context liên quan đã PASS
trên staging và đã thử đường rollback.

Với beta55, mỗi handle defer, async CSS hoặc delay có một **Per-rule
Compatibility Gate** riêng kèm evidence reference bất biến. Evidence chỉ là
hash của dependency và asset fingerprint; không lưu URL nguồn, nội dung trang
hay payload bên thứ ba. Nếu handle, dependency hoặc fingerprint asset thay đổi,
gate tự chuyển sang **Stale** và bị chặn ở production cho tới khi QA/rollback
được xác minh lại. Diff Drawer hiển thị lịch sử evidence trước/sau và các nhóm
thay đổi; staging/development vẫn cho phép chạy thử. Lịch sử chỉ giữ 90 ngày
và tối đa 200 bản ghi; JSON export chỉ chứa trường an toàn. Operator có thể
đánh dấu stale gate là đã xem, nhưng acknowledgement không mở khóa production.
Automated evidence review chạy theo giờ, đánh dấu evidence quá 90 ngày là hết
hạn và ghi audit chỉ gồm số lượng gate, handle và môi trường.

Với beta56, trang **Analytics** nhận đúng một ID Google: `G-…` là GA4 và
`GTM-…` là Google Tag Manager. BaoCache inject bootstrap từ file local thay vì
inline executable script, nên không phải sửa theme và không tạo ngoại lệ CSP
vô tội vạ. Microsoft Clarity là opt-in riêng. “Injected on public frontend”
chỉ xác nhận cấu hình BaoCache sẽ phát tag cho khách; nó không phải xác nhận
Google đã nhận hit hay Realtime đang có dữ liệu. Auto Events cũng là opt-in và
chỉ chạy sau khi Consent Mode được cấu hình; event chỉ vào `dataLayer`, không
được lưu tại WordPress.

Với beta57, Analytics đổi nhãn theo provider sau khi nhập ID, kiểm tra
`G-…`/`GTM-…` ngay trên form, liệt kê các event thực sự được hỗ trợ và có
preview/copy bootstrap. Nút **Test configuration** chỉ kiểm tra cấu hình local,
Consent Mode và chiến lược CSP-safe; không tuyên bố Google Realtime hay
“Page View Received” khi BaoCache chưa gọi API vendor.

Với beta58, **Test configuration** đọc đúng một HTML frontend cùng domain ở
chế độ không cookie, kiểm tra bootstrap/config BaoCache, các directive CSP cần
thiết và chỉ liệt kê tối đa tám Google ID công khai khác cấu hình hiện tại.
Response body, cookie, query string và CSP đầy đủ không được lưu vào activity
log hoặc export. Đây là evidence HTML/CSP, không phải bằng chứng browser đã
chạy `gtm.js`, GA Realtime hay xác nhận vendor-side.

Với beta59, **Adapter integrations** là lớp opt-in nằm trên Auto Events. Khi
plugin tương ứng đang active, adapter chỉ có thể bổ sung metadata, known
constraints và event chuẩn hoá vào
`dataLayer`: WooCommerce (`view_item`, `add_to_cart`, `begin_checkout`,
`purchase`), form completion (`form_submit`), OneSignal (`subscribe_push`,
`unsubscribe_push`) và Power Schedule Manager (`search_schedule`,
`select_area`, `view_schedule`, push subscription). Adapter không gửi form
field, email, nội dung biểu mẫu, URL, mã khu vực hay dữ liệu khách về
WordPress/BaoCache. Core vẫn hoạt động khi không có adapter và không dùng slug
plugin làm điều kiện bật tối ưu. Tất cả vẫn cần Auto Events và Consent Mode hợp lệ. Một
OneSignal bên thứ ba chỉ nên gọi bridge sau khi SDK của chính nó đã xác minh
subscription thay đổi; BaoCache không đoán conversion từ click.

Với beta60, **Test configuration** tách riêng `GTM-…` ngoài cấu hình và
`G-…` ngoài cấu hình. Đây là dấu hiệu cần kiểm tra chủ sở hữu để tránh hai
container hoặc hai nguồn phát cùng một `page_view`; BaoCache không tự xoá tag
ngoài cấu hình. Test cũng chỉ xác minh file Auto Events/adapter đã có trong
HTML public khi chúng được bật hợp lệ. Điều đó không chứng minh người dùng đã
thao tác hay Google/OneSignal đã nhận conversion — bước đó cần GTM Preview,
GA4 DebugView hoặc công cụ vendor tương ứng.

Với beta61, BaoCache có **CSP manager** tùy chọn trong khu vực Cache/Security.
Policy mặc định tắt; khi bật, **Report-Only** là mode mặc định để quan sát trước
khi Enforce. Policy static và không dùng nonce vì HTML có thể được Nginx
FastCGI cache. BaoCache tự thêm origin tối thiểu theo Analytics, Clarity và
adapter đang bật; YouTube, Vimeo, Cloudflare Insights hoặc dịch vụ khác phải
được thêm rõ ràng vào đúng directive khi site thực sự sử dụng chúng, tránh
whitelist rộng không có bằng chứng. Chỉ nên có một nơi phát CSP: nếu chọn
BaoCache thì tắt policy tương ứng ở Cloudflare/Nginx (và ngược lại); hai header
CSP sẽ giao nhau trong trình duyệt. Khi response đã có CSP ở origin, BaoCache
không phát thêm header thứ hai.

beta61 cũng thêm **Analytics migration checklist** cho trường hợp public
evidence phát hiện GTM container hoặc GA4 Measurement ID ngoài cấu hình. Người
vận hành chọn một nguồn canonical, chuyển tag/page_view, tắt injector cũ rồi
chạy lại public diagnostics/GTM Preview. Nút acknowledgement chỉ ghi dấu audit
và không tự xóa hoặc vô hiệu hóa tag bên ngoài.

Với beta62, CSP Report-Only có thể thu thập **violation evidence tổng hợp**
theo opt-in. Endpoint same-origin chỉ giữ directive, blocked origin đã chuẩn
hóa, disposition, count và thời điểm; retention tối đa 30 ngày, không lưu
document URL/path, query, referrer hay visitor data. BaoCache lưu thêm policy
fingerprint và diff theo directive giữa snapshot hiện tại và snapshot trước.
Nút xóa evidence chỉ dành cho quản trị viên. Với beta63, BaoCache chỉ đề xuất
một HTTPS origin khi Report-Only có evidence lặp lại (ít nhất hai lần cách nhau
một phút, hoặc ba lần). Quản trị viên phải bấm **Thêm source**; thao tác này chỉ
thêm vào đúng directive, lưu fingerprint mới và vẫn giữ Report-Only. `inline`,
`eval`, `data`, `blob`, scheme-only và origin cùng website không bao giờ được đề
xuất. BaoCache không tự chuyển Report-Only sang Enforce.

Critical CSS không được sinh tự động trong PHP. Một worker/CI có thể đưa CSS đã
được tạo vào màn **Critical CSS staging**; BaoCache kiểm tra giới hạn kích thước,
thẻ nguy hiểm, `@import` và cân bằng block trước khi stage. CSS chỉ được inline
khi fingerprint WordPress/theme/plugin/Customizer còn khớp. Khi theme, plugin,
Customizer hoặc phiên bản WordPress thay đổi, fingerprint lệch và CSS tự động
ngừng inline cho tới khi validate lại. Nút **Rollback** tắt inline ngay lập tức
nhưng giữ bản CSS staged để có thể kiểm tra lại sau.

`Generator Tag` là thẻ HTML `<meta name="generator">`; BaoCache có thể gỡ thẻ
này cùng nhóm shortlink. WordPress vẫn có thể thêm `?ver=` vào URL CSS/JS để
cache-busting; đó là metadata khác và được giữ nguyên.

Nếu phát hiện Wordfence đang hoạt động, dashboard chỉ hiển thị ghi chú rằng
firewall, malware scan, login security, 2FA, CAPTCHA và brute-force protection
do Wordfence quản lý; BaoCache không tạo lớp bảo mật trùng lặp.

Các header `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`,
`Permissions-Policy` và HSTS được phát từ Nginx bundled image. Header Inspector
mới là nguồn xác minh response thực tế; BaoCache không ghi đè header từ PHP để
tránh header trùng trên response FastCGI.

Nếu Cloudflare đang cache HTML ở edge, purge FastCGI chỉ làm mới lớp Nginx. Vì
BaoCache không dùng Cloudflare token, hãy đặt Edge Cache TTL phù hợp hoặc purge
URL đó từ Cloudflare khi cần xuất bản ngay lập tức.

## Sitemap preload và warm queue

Warm queue là tùy chọn, mặc định tắt. BaoCache ưu tiên URL đã cấu hình và
`/sitemap_index.xml`, sau đó tự xác minh `/wp-sitemap.xml`, `/sitemap.xml` và
`/sitemap-index.xml` trên cùng domain. Vì vậy nó tương thích với Yoast, Rank
Math, WordPress core và các plugin dùng đường dẫn sitemap phổ biến. BaoCache
chỉ tải sitemap cùng domain qua Nginx nội bộ (`http://nginx`), không gọi crawler bên ngoài. Mỗi phút
nó xử lý tối đa 1, 2 hoặc 5 URL; URL lỗi được thử lại tối đa hai lần. Điều này
phù hợp để làm ấm các trang vừa thay đổi hoặc một sitemap nhỏ, không phải cơ chế
để crawl toàn bộ một website lớn liên tục.

## Bảo vệ WordPress All Settings

Nginx chặn truy cập `GET /wp-admin/options.php`. Đây là màn hình WordPress có
thể liệt kê toàn bộ options cho administrator; nó không phù hợp để mở trực tiếp
trên production. Các form Settings hợp lệ vẫn gửi `POST` tới endpoint này và
tiếp tục lưu bình thường.

## Khả năng tương thích

BaoCache chỉ dùng WordPress public hooks/API, vì vậy tương thích với Blocksy,
Astra và phần lớn theme chuẩn. Các adapter cho Blocksy, Elementor, Bricks,
WooCommerce hoặc plugin riêng chỉ thêm metadata/known constraints; chúng không
thay thế engine generic. Mọi rule asset đặc thù phải được lưu vào Site Overrides
và luôn cần kiểm thử trên staging.

## Điều BaoCache chưa làm

- Không tự động “Remove Unused CSS”: tính năng đúng nghĩa cần phân tích render
  theo từng URL và thường dựa vào dịch vụ browser/cloud.
- Không “Delay all JavaScript”: có nguy cơ làm vỡ menu, biểu mẫu, captcha và
  tracking. BaoCache dùng defer strategy chuẩn của WordPress theo handle.
- Không đo cache HIT/TTFB/LCP giả. Muốn có dashboard số liệu thật cần bổ sung
  observability ở Nginx (access log/metrics endpoint được bảo vệ) hoặc RUM.
