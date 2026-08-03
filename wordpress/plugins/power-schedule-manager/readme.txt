=== Cúp Điện Lâm Đồng ===
Contributors: nguyenhoangthaibao
Tags: lam dong, local portal, market data, weather, lottery
Requires at least: 6.7
Requires PHP: 8.3
Stable tag: 0.38.16
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Vận hành cổng thông tin và tiện ích số dành cho Lâm Đồng.

== Description ==

Cúp Điện Lâm Đồng vận hành trang chủ Cúp Điện Lâm Đồng, tin địa phương, việc làm,
du lịch, lịch điện, thời tiết, giá thị trường và xổ số trong một hệ thống
shortcode thống nhất. Plugin có quy trình nhập dữ liệu, chống trùng, thư viện
bản đồ, API token, SEO, cache, sao lưu cloud, kiểm tra hệ thống và thông báo.

== Installation ==

1. Tải thư mục `power-schedule-manager` vào `wp-content/plugins`.
2. Kích hoạt plugin trong WordPress.
3. Mở Cúp Điện Lâm Đồng > Trạng thái hệ thống để xác nhận schema và cron.
4. Cấu hình bản đồ, retention và thông báo trước khi vận hành.

== Changelog ==

= 0.38.16 =
* Added encrypted administration fields for OneSignal, Telegram, webhook and Zalo credentials so deployment no longer depends on integration variables in Docker Compose.
* Added a 60-second Nginx FastCGI micro-cache hint for public plugin surfaces and the standard mobile web-app capability metadata.

= 0.38.15 =
* Minified the main frontend stylesheet again, eliminating the Lighthouse unminified-CSS finding and reducing its transfer size by about 45 KB.

= 0.38.14 =
* Restored a clean plugin boot path for all shortcode services, including [power_schedule_home].
* Reframed the public collaboration form as a voluntary community-contact form without payment, donation amount or advertising-position selection.

= 0.38.13 =
* Fixed a PHP startup error in the sponsorship service introduced during the retired donation-flow cleanup.

= 0.38.12 =
* Removed the retired donation shortcode, payment templates, review screen, handlers and menu ordering; only the sponsorship workflow remains.
* Rebuilt Synchronization History desktop rows as spacious two-by-two record cards instead of a compressed table.

= 0.38.11 =
* Redesigned Synchronization History into four spacious information groups: run/source, results, operation and timing.
* Retired the public donation/MoMo/bank settings and form. The sponsorship shortcode now captures only collaboration needs, placement, timing and contact details—never an amount.
* Refined advertising slots for responsive AdSense integrations: clear “Quảng cáo” labels, safe shortcode/HTML rendering and guidance for combining manual units with Auto ads.

= 0.38.10 =
* Balanced the Synchronization History column widths across the full available canvas, giving results and timing the room they need.

= 0.38.9 =
* Replaced wrapping map-library row actions with compact, labelled edit, map and delete icon controls; long unit codes remain on one line.
* Refined synchronization-history time details so immediate runs no longer repeat the same timestamp.

= 0.38.8 =
* Redesigned Synchronization History with current-filter update statistics, clearer result signals and more compact filtering.
* Reworked the Help Center into a task-first interface with quick actions and instant guide search.

= 0.38.7 =
* Bổ sung thông báo OneSignal theo sở thích xổ số: ba miền hoặc từng sản phẩm, chỉ khi dữ liệu kỳ quay mới/thay đổi thực sự được lưu.
* Mẫu Push lịch điện mới, tự rút gọn khoảng ngày thành “trong ngày dd/mm/YYYY” khi chỉ có một ngày.
* Rút gọn và làm rõ hướng dẫn OneSignal, Telegram, webhook và Zalo OA; các hằng cấu hình không còn bị tràn khung hiển thị.

= 0.38.6 =
* Hàng đợi OneSignal chỉ tạo từ revision có thay đổi nội dung lịch, gom một thông báo theo khu vực và đợt import, kèm hash chống gửi trùng.
* Bổ sung nhật ký lần thử, HTTP, OneSignal message ID, trạng thái không có thuê bao phù hợp và retry chỉ cho timeout, 429 hoặc 5xx.
* Chuẩn hóa tag khu vực thành mã ổn định viết thường `psm_area_<unit_code>` và tự dọn tag định dạng cũ khi người dùng lưu lại lựa chọn.

= 0.38.5 =
* OneSignal SDK chỉ tải khi người dùng chủ động mở tùy chọn “Nhận thông báo”; không còn được đưa vào head và không hiển thị Slidedown tự động.
* Người dùng có thể theo dõi nhiều khu vực điện lực trên thiết bị của mình; thông báo chỉ gửi khi lịch của khu vực đã chọn thực sự thay đổi.
* Bổ sung cấu hình gửi Push tự động, tiêu đề và nội dung mẫu trong quản trị; REST API key vẫn chỉ đọc từ cấu hình máy chủ.

= 0.38.4 =

* Chuẩn hóa toàn bộ phần nội dung dưới Hero theo đúng frame header Blocksy, không cộng lặp gutter khi shortcode nằm trong container theme.
* Loại bỏ kích thước nội tại 720px trên trục ngang của section trì hoãn render, ngăn card bị đẩy phải hoặc bị cắt trên mobile.
* Áp dụng cùng hợp đồng chiều rộng cho trang plugin tự sinh, trang chủ và toàn bộ shortcode độc lập/lồng nhau.

= 0.38.3 =

* Cho nền Hero phủ toàn bộ chiều rộng màn hình, giữ nội dung bên trong thẳng hàng với header Blocksy.
* Chuẩn hóa lưới nội dung/trạng thái và rút gọn summary thành hai cột trên mobile.
* Ngăn breadcrumb, H1, CTA và bảng realtime chạm mép hoặc vượt khung Hero.

= 0.38.2 =

* Đổi toàn bộ tên hiển thị plugin, quản trị, Hero và PWA thành Cúp Điện Lâm Đồng.
* Tự chuyển các giá trị nhận diện cũ đã lưu trong cấu hình khi plugin khởi động.
* Xác minh lại ZIP cài đặt và giữ nguyên cấu trúc một thư mục gốc chuẩn WordPress.
* Khai báo PHP tối thiểu 8.3 đúng với cú pháp typed class constants đang sử dụng.

= 0.38.1 =
* Chuẩn hóa mọi Utility Hero và Hero trang chủ theo một frame duy nhất trùng lề nội dung header Blocksy ở desktop và mobile.
* Xóa lưới 720px còn sót trên trang chủ, ngăn Hero trôi sang phải và không cho inner shell rộng hơn khối ngoài.
* Khôi phục đầy đủ giao diện CTA Hero sau khi loại bỏ mã Chia sẻ; breadcrumb, H1, mô tả và thẻ trạng thái đều tự co trong viewport.
* Đồng bộ chiều rộng bài lịch chi tiết và khối liên hệ EVNSPC; khối lồng nhau không còn cộng thêm gutter lần thứ hai.

= 0.38.0 =
* Thay toàn bộ cơ chế full-width dịch chuyển bằng container Blocksy ổn định cho trang chủ, utility, xổ số và lịch điện trên desktop/mobile.
* Xóa hoàn toàn nút Chia sẻ, cấu hình và mã frontend liên quan; CTA Hero trỏ đúng anchor của từng shortcode dữ liệu.
* Tự nhận diện Page xổ số con, tạo đúng một Utility Hero/H1, tắt Page Title Blocksy và cho phép quản trị viên chỉnh Hero cùng nội dung SEO tại Page.
* Chuẩn hóa giao diện Điện toán 123, 6x36 và Thần Tài; thu gọn trạng thái chờ và bố cục thẻ responsive.
* Đưa thông tin pháp lý và kênh liên hệ chính thức EVNSPC lên ngay sau Hero của trang lịch điện.
* Bổ sung hướng dẫn chi tiết cho từng shortcode con, tham số, anchor CTA và route plugin tự sinh trong trang quản trị.

= 0.37.1 =
* Khóa breakpoint Blocksy cuối stylesheet, sửa Hero trang chủ/thời tiết bị giữ lưới desktop và tràn ngang trên mobile.
* Đưa bảng xổ số miền Trung, miền Nam về thẻ hai cột responsive, bỏ cột giải ghim và yêu cầu cuộn ngang trên điện thoại.
* Đồng bộ chiều rộng Hero, bảng, form, nội dung SEO và khối liên hệ theo đúng gutter header Blocksy; giảm chiều cao Hero tiện ích.
* Bổ sung kênh xác minh EVNSPC trên archive lịch điện và tuyên bố rõ website tổng hợp độc lập.
* Hoàn thiện hướng dẫn mọi shortcode xổ số, công thức tạo Page, thuộc tính Hero, CTA anchor và nguyên tắc một H1 trong quản trị.

= 0.37.0 =
* Đồng bộ archive, taxonomy khu vực và trang chi tiết lịch theo ngày với một Utility Hero/H1; CTA trỏ đúng form hoặc bảng dữ liệu.
* Căn toàn bộ Hero, bảng, trang chủ, Đồng hành và Hợp tác theo gutter responsive của header Blocksy thay vì container hẹp riêng.
* Làm cho tùy chọn Plugin quản lý Hero hoạt động với Blocksy: tự tắt Page Title theo Page, hiển thị trạng thái và tự đồng bộ cấu hình cũ.
* Bổ sung preset Hero Đồng hành/Hợp tác và chuyển phần mở đầu trong shortcode thành nội dung H2, tránh Hero kép.
* Hoàn thiện danh mục shortcode xổ số, công cụ tra cứu cũ, lịch sử từng sản phẩm và quy trình tạo Page trong phần quản trị.
* Tách CSS production thành core và sáu component (lịch điện, xổ số, thị trường, thời tiết, trang chủ, cộng đồng); chỉ nạp component hiện diện, dùng JavaScript defer và không nạp asset plugin trên nội dung không liên quan.
* Minify riêng frontend, PWA và OneSignal controller; giữ PWA/OneSignal ở hai service-worker scope không xung đột, đồng thời sửa nhận diện cũ thành Cúp Điện Lâm Đồng.
* Ảnh trang chủ có kích thước, srcset và lazy loading để giảm CLS; Turnstile tiếp tục dùng async/defer.

= 0.36.1 =
* Xóa hoàn toàn masthead cũ khỏi shortcode giá vàng, cà phê, xổ số và thời tiết để mỗi page chỉ còn đúng một Hero/H1.
* Đồng bộ archive `/lich-cup-dien/` do plugin tạo với Utility Hero chung, breadcrumb và container Blocksy.
* Sửa bảng xổ số ba miền trên mobile: giữ độ rộng đọc được, cuộn ngang có hướng dẫn và cố định cột tên giải.
* Giữ các tiêu đề dữ liệu dạng H2 gọn ngay sau Hero; không còn nút Chia sẻ trong shortcode dữ liệu.
* Đóng gói đầy đủ tài nguyên frontend và thư viện Leaflet tại `public/assets/vendor`.

= 0.36.0 =
* Giảm Utility Hero về khoảng 260–320px trên desktop để dữ liệu xuất hiện sớm hơn; giữ mobile tự động theo nội dung.
* Loại nút Chia sẻ khỏi Hero và thay bằng tóm tắt dữ liệu thật đã lưu: giờ cập nhật, trạng thái, mức gần nhất, biến động, nhiệt độ hoặc kỳ quay.
* Chuyển toàn bộ masthead cũ của giá, xổ số, thời tiết và tra cứu thành section header nhỏ, không còn Hero kép.
* Nâng breadcrumb thành Trang chủ / Tiện ích / Tên trang và đồng bộ item Tiện ích với Rank Math Breadcrumb khi Page dùng Hero plugin.
* Thêm hộp Cúp Điện Lâm Đồng — Hero và nội dung SEO trên từng Page để chọn Plugin/Blocksy, sửa badge, H1, mô tả, CTA, tóm tắt, hướng dẫn, nguồn và FAQ hiển thị.
* Hero không gọi API ngoài khi render; chỉ đọc database hoặc cache hiện có và tự bỏ cột phải nếu chưa có dữ liệu thật.

= 0.35.9 =
* Refactor hero và vùng dữ liệu theo một container dùng biến layout của Blocksy, đồng nhất desktop/tablet/mobile và tránh tràn ngang.
* Chuẩn hóa CTA và tóm tắt dữ liệu theo từng loại Page tiện ích.
* Để Rank Math toàn quyền quản lý title, description, canonical và schema; plugin chỉ ép noindex cho trạng thái lọc mỏng khi thật sự cần.
* Chỉ tải CSS/JS công khai trên trang có shortcode, giữ SSR và bổ sung tối ưu bảng, focus, reduced motion và content visibility.
* Thêm Khối nội dung có revisions, chỉ quản trị viên được sửa, render HTML an toàn bằng [power_schedule_content slug="..."] và tự ngăn H1 trùng.

= 0.35.8 =
* Bổ sung hero SSR dùng chung cho lịch điện, giá vàng, cà phê, xổ số, tra cứu xổ số và thời tiết; có đúng một H1, breadcrumb, CTA, quick info và anchor navigation responsive.
* Sửa lỗi JSON Power 6/55 và Max 3D khi nhà cung cấp trả trường giải thưởng dạng lồng mảng.
* Loại bản ghi JSON hỏng trước khi render, ghi log bảo vệ và trả trạng thái lỗi thân thiện thay vì làm sập Page.

= 0.35.7 =
* Chuyển domain chính thức, Plugin URI, Update URI và máy chủ mẫu OpenAPI sang lamdonghomnay.com.
* Loại tham chiếu domain cũ khỏi mã nguồn và giữ nguyên định danh plugin để cập nhật an toàn.
* Chuyển trang chủ thành cổng thông tin Lâm Đồng tổng hợp với tìm kiếm toàn website, bảy lối tắt tiện ích và các dòng Tin tức, Việc làm, Du lịch.
* Đưa lịch điện xuống thành tiện ích thiết yếu riêng, thay nội dung hướng dẫn cuối trang bằng giới thiệu, minh bạch nguồn và FAQ cấp cổng thông tin.
* Tự lấy bốn bài mới nhất từ các chuyên mục tin-tuc, viec-lam và du-lich; bổ sung hướng dẫn cấu hình ngay trong quản trị.
* Đồng bộ OpenAPI với Bearer token riêng theo client và bổ sung hướng dẫn Laravel pull lịch bằng cursor, ETag và rate limit.

= 0.35.6 =
* Đổi tên plugin và toàn bộ khu vực quản trị từ Power Schedule Manager/Trung tâm vận hành thành Cúp Điện Lâm Đồng.
* Đổi thương hiệu công khai, Hero, PWA, API title và nội dung Đồng hành/Hợp tác thành Cúp Điện Lâm Đồng.
* Chuẩn hóa cụm từ mô tả thành “lịch điện tại Lâm Đồng” và tự thay nội dung mặc định cũ khi hiển thị.
* Giữ nguyên shortcode, text domain, option, slug kỹ thuật và URL thật để bảo toàn khả năng tương thích.

= 0.35.5 =
* Chuyển shortcode ủng hộ cũ thành trang “Đồng hành” không có QR, số tài khoản hoặc biểu mẫu thanh toán.
* Vô hiệu hóa tiếp nhận khai báo chuyển khoản mới, đồng thời giữ dữ liệu cũ trong quản trị để đối soát.
* Bổ sung tuyên bố độc lập với EVN, CTA góp ý/hợp tác và nội dung xác minh rõ trên page hợp tác.
* Đổi nhãn quản trị sang Đồng hành và loại cấu hình thanh toán cá nhân khỏi giao diện cài đặt công khai.

= 0.35.4 =
* Chuyển shortcode overview xổ số sang chế độ Hub gọn, dẫn đến đúng page ba miền, Mega 6/45, Power 6/55, Max 3D, Keno và Điện toán.
* Bổ sung shortcode tra cứu kết quả cũ theo ngày và sản phẩm; vẫn giữ mode full để tương thích page cũ.
* Thêm hướng dẫn cấu trúc 8 page, slug gợi ý và shortcode sao chép nhanh trong quản trị xổ số.

= 0.35.3 =
* Sửa Hero Cà phê và Vàng bị co giữa, dóng nội dung theo cùng mép ngang của header website.
* Chuẩn hóa tiêu đề bảng, hàng tiêu đề, padding và căn số cho toàn bộ overview thị trường.
* Đưa dải USD/VND có ngày ghi nhận vào bảng Cà phê trong nước và Vàng trong nước.

= 0.35.2 =
* Đồng bộ mép ngang Hero với toàn bộ bảng Cà phê và Vàng trên desktop lẫn thiết bị nhỏ.
* Tinh gọn điều hướng trong Hero, bổ sung liên kết Tổng quan cho bảng Vàng và trạng thái trống gọn cho giá thế giới.
* Chuẩn hóa phân trang Giá thị trường, Hợp tác tài trợ và Lịch sử đồng bộ; hiển thị từ bản ghi thứ 16.

= 0.35.1 =
* Sắp xếp lại overview cà phê theo luồng trong nước, thế giới rồi mới đến chỉ số liên quan; tỷ giá không còn chiếm diện tích Hero.
* Bổ sung tóm tắt giá trung bình, khu vực cao nhất và khoảng giá trước bảng cà phê trong nước; thu gọn điều hướng và metadata.
* Chuẩn hóa trang quản trị giá bằng Hero, bộ lọc, bảng dữ liệu dễ đọc, trạng thái rõ, giá định dạng đúng và form responsive.
* Đồng bộ giao diện cho lịch sử đồng bộ, xem trước nhập liệu, chi tiết lần nhập và các trang quản trị phụ; bỏ navigation sticky gây che nội dung.

= 0.35.0 =
* Thay Application Password bằng Bearer token riêng cho từng website/app; token chỉ hiện một lần, lưu mã băm, có hạn mức, ngày hết hạn, lần dùng gần nhất và thu hồi độc lập.
* Hợp nhất tương thích dữ liệu cà phê nhập tay giữa các bảng cũ, tự phân loại cà phê và hồ tiêu theo nhãn, đồng thời đưa bảng trong nước lên đầu overview.
* Bổ sung điều hướng trang rõ ở đầu và cuối dữ liệu giá đã lưu, kèm trạng thái công khai/đang ẩn.
* Chuẩn hóa overview và shortcode riêng của cà phê, vàng và xổ số.

= 0.34.9 =
* Tối ưu riêng từng shortcode cà phê, vàng và xổ số, đồng thời giữ overview gọn, có phân cấp rõ trên desktop và mobile.
* Hoàn thiện backup cloud: tạo NDJSON có checksum, tải lên, ghi nhớ bản gần nhất và khôi phục an toàn từ Wasabi hoặc Google Drive.
* Bổ sung giá thị trường, xổ số và ủng hộ vào nội dung backup thay vì chỉ sao lưu lịch điện và bản đồ.
* Hỗ trợ cấu hình cloud bền vững từ biến môi trường Docker/Coolify, với cấu hình mã hóa trong WordPress được ưu tiên.

= 0.34.8 =
* Chuẩn hóa giá nhập thủ công, tính biến động từ bản ghi trước và hiển thị bản mới nhất của từng vùng cà phê hoặc hồ tiêu.
* Tách overview cà phê thành Lâm Đồng, trong nước, hồ tiêu và thế giới; đưa USD/VND thành thẻ đối chiếu gọn trong Hero.
* Giảm kích thước chữ, padding và độ dàn trải của overview cà phê, vàng và xổ số trên desktop lẫn mobile.
* Thêm kết nối mã hóa và kiểm tra quyền thực tế cho Wasabi và Google Drive tại trung tâm Sao lưu.

= 0.34.7 =
* Cho phép nội dung Hero đã lưu trong quản trị thay thế thuộc tính shortcode cũ, giúp H1 và phần giới thiệu áp dụng nhất quán.
* Hiển thị bản ghi mới nhất của từng khu vực cà phê trong nước và dùng bảng bốn cột responsive gọn hơn.
* Bổ sung lưu ý mặc định, hotline và liên kết CSKH EVNSPC tại trang chi tiết; sửa kiểm tra Leaflet tránh cảnh báo sai.
* Thêm công tắc ẩn/hiện toàn bộ banner, đổi menu quản trị thành “Ủng hộ” và hỗ trợ khôi phục schema 1.7.2.

= 0.34.6 =
* Đồng bộ hero Hợp tác tài trợ theo chiều rộng màn hình, sửa checkbox đồng ý bị kéo dọc và bổ sung nội dung/quy trình hợp tác rõ ràng.
* Đổi menu quản trị thành Ủng hộ và đối tác, bỏ biểu tượng thừa ở hai nguồn dữ liệu và chuyển hướng dẫn Cloudflare sang accordion một cột.
* Chỉ gắn nhãn Đang quay cho kỳ đúng ngày hiện tại, còn thiếu kết quả và có trạng thái live rõ ràng từ nhà cung cấp.

= 0.34.5 =
* Đăng ký template và nạp stylesheet cho [power_schedule_sponsor], sửa lỗi shortcode trả về rỗng.
* Cân chiều cao thẻ trạng thái với nội dung hero, dùng FAQ hai cột toàn chiều rộng và thu gọn ghi chú quản trị.
* Xổ số Điện toán chỉ hiển thị kỳ có kết quả hoàn chỉnh và chuyển từng bảng về một hàng rõ ràng.

= 0.34.4 =
* Kích hoạt trực tiếp shortcode [power_schedule_sponsor], chuẩn hóa tên Hợp tác tài trợ và giữ Turnstile ở giao diện nguyên bản.
* Thu gọn nhịp hero, gom thời điểm cập nhật thị trường về một vị trí và cân lại thẻ USD/VND.
* Bỏ bảng Max 3D rỗng khỏi trang tổng hợp, mở rộng ánh xạ Max 3D Pro và chuyển FAQ trang chủ về accordion một cột.

= 0.34.3 =
* Tách các luồng cộng đồng và hợp tác thành shortcode độc lập.
* Nâng cấp trang hợp tác tài trợ, giữ hướng dẫn Google AdSense trong khu vực quản trị và bảo vệ form bằng Turnstile/rate limit.
* Đưa các lợi ích tra cứu lên trước tiêu đề hero, làm rõ trạng thái đồng bộ gần thời gian thực và sửa số ba chữ số bị xuống dòng.

= 0.34.2 =
* Giữ donate và hợp tác tài trợ trên cùng trang nhưng tách thành hai biểu mẫu, mục tiêu và trạng thái xử lý riêng.
* Bổ sung form doanh nghiệp chọn nhiều vị trí quảng bá, ngân sách, thời gian; lưu vào hàng đợi Hợp tác tài trợ và gửi thông báo cho quản trị viên.
* Mở rộng nội dung inventory tài trợ, nguyên tắc AdSense ít gián đoạn, Turnstile, rate limit và quyền riêng tư cho liên hệ doanh nghiệp.

= 0.34.1 =
* Cân lại hero trang chủ theo luồng tiêu đề → tra cứu → CTA; rút gọn tiêu đề thành ba dòng và đổi microcopy thành badge hữu ích.
* Thu gọn thanh tìm kiếm còn 64px, giảm độ nổi của CTA và giảm 10–15% trọng lượng thị giác của thẻ trạng thái.
* Chỉ dùng pulse khi thực sự chưa có dữ liệu gần nhất; thay LIVE STATUS bằng ngôn ngữ thân thiện với người dùng.

= 0.34.0 =
* Tích hợp Cloudflare Turnstile cho biểu mẫu ủng hộ với xác minh bắt buộc ở máy chủ, hostname/action binding và khóa bí mật mã hóa hoặc lấy từ môi trường.
* Bổ sung giới hạn biểu mẫu theo IP và email, công thức Cloudflare Rate Limiting, checklist WAF WordPress và cấu hình cache production.
* Tách trải nghiệm ủng hộ thành đóng góp tự nguyện và hợp tác tài trợ minh bạch dành cho doanh nghiệp.

= 0.33.0 =
* Bỏ nhãn cập nhật khỏi thời tiết Windy và dùng cùng màu hero xổ số cho các trang tiện ích.
* Đưa ảnh chụp so sánh vàng Việt Nam/thế giới lên trước, bảng trong nước trước biểu đồ và tỷ giá USD/VND xuống dưới bảng.
* Không che dữ liệu cũ bằng trạng thái đang cập nhật; khi thiếu biến động thế giới, hiển thị dấu gạch và giải thích rõ.
* Bổ sung mô tả, tiện ích tra cứu gọn cho hero trang chủ và thiết kế hero ủng hộ theo tác động cộng đồng.

= 0.32.9 =
* Chuẩn hóa hero toàn chiều rộng cho giá vàng, cà phê, xổ số, thời tiết và ủng hộ; nội dung bám đúng trục logo.
* Bỏ hộp tỷ giá USD/VND lặp ở cuối trang vàng và giữ duy nhất tham chiếu gọn trong hero.
* Đưa các lớp Radar mưa, gió, nhiệt độ, mây, tuyết và dông sét vào hero thời tiết.
* Căn lại cột số LIVE STATUS, tách rõ lịch hôm nay, lịch gần nhất và lịch vừa cập nhật; bổ sung FAQ responsive cho trang chủ.

= 0.32.8 =
* Đồng bộ giá vàng trong nước từ Giavang.now và giá XAU từ Gold API; ẩn nguồn trên giao diện công khai nhưng giữ thời điểm cập nhật.
* Đưa tỷ giá USD/VND tham khảo vào header giá vàng và chuẩn hóa căn lề header cho các trang tiện ích.
* Sắp xếp lại trang chủ theo ngày, khu vực, lịch hôm nay và cập nhật gần nhất; tinh gọn hero, LIVE STATUS, dropdown và khoảng cách.
* Lấy dự báo mới khi cache thời tiết trống, giữ Windy ở trang chi tiết và mở rộng FAQ theo layout responsive.

= 0.32.0 =
* Tách kiểm tra giá vàng/tỷ giá và cà phê để lỗi một nguồn không làm sai trạng thái nguồn khác.
* Hiển thị chẩn đoán riêng từng nhà cung cấp, phân biệt lỗi khóa, hạn mức, HTTPS và phản hồi dữ liệu.
* Sửa tương phản tiêu đề trang giá vàng; tối ưu điều hướng và bố cục nguồn dữ liệu trong quản trị.
* Bỏ biểu tượng thừa trong phần cấu hình giá vàng và cà phê.

= 0.31.9 =
* Buộc mô hình Trùng khớp Mega/Power nằm ngang và sửa toàn bộ header xổ số bị co chữ theo chiều dọc trên mobile.
* Gộp Điện toán 123, 6x36 và Thần Tài 4 thành một bảng ba cột đồng nhất; trạng thái chờ dùng icon pulse không viền.
* Chuyển API ứng dụng vào Nguồn dữ liệu & API; thêm thống kê request ẩn danh, phát hiện dấu hiệu tự động, giới hạn IP và hướng dẫn Cloudflare.
* Sắp xếp bảng vàng theo SJC, nhẫn, DOJI, PNJ và thương hiệu; kết hợp Giavang.now trong nước với Gold API thế giới theo mặc định.

= 0.31.8 =
* Chuyển bảng xổ số miền Trung và miền Nam trên điện thoại sang bố cục theo giải/đài, không còn cuộn ngang và thu gọn tiêu đề.
* Sửa mô hình Trùng khớp Mega/Power theo đúng từng hạng giải; làm mới trạng thái chờ dữ liệu của Điện toán 123, 6x36 và Thần Tài 4.
* Gom cấu hình API xổ số, giá vàng, tỷ giá và cà phê về một trang Nguồn dữ liệu; loại VNAppMob khỏi luồng vàng mới nhưng giữ dữ liệu cũ.
* Bổ sung nội dung tìm kiếm dài cho xổ số, giá vàng và cà phê; tinh chỉnh H1, hero, ô tìm kiếm và bảng giá trên mobile.

= 0.31.7 =
* Chuẩn hóa tiêu đề và trạng thái kỳ quay cho toàn bộ Vietlott, Keno và xổ số Điện toán.
* Khôi phục bốn chỉ số Chẵn/Lẻ/Lớn/Nhỏ của Keno và hoàn thiện bảng giải Max 3D, Max 3D+ và Max 3D Pro.
* Đọc thêm các tên trường số lượng giải từ API, tối ưu ô trùng khớp Mega/Power và hình tròn kết quả.
* Tách tra cứu theo ngày khỏi header, đồng thời tinh gọn hero và ô tìm kiếm trang chủ trên điện thoại.

= 0.31.6 =
* Nhận đúng Max 3D+ khi API dùng dấu cộng; tách dữ liệu Điện toán 123, 6x36 và Thần Tài 4 khi nhà cung cấp trả về chung một khối.
* Giữ nguyên ngày kỳ Keno từ API, sửa lại metadata cũ và tăng phạm vi đồng bộ để không bỏ sót sản phẩm có lịch quay thưa.
* Tối ưu bộ chọn ngày, khoảng cách header, thẻ Điện toán, thống kê Keno và bảng ba miền trên máy tính, máy tính bảng, điện thoại.
* Bổ sung nội dung tra cứu xổ số có ngữ nghĩa rõ ràng cho SEO và khả năng tiếp cận.

= 0.31.5 =
* Sửa lỗi nghiêm trọng khi một sản phẩm xổ số chưa có dữ liệu khiến toàn bộ phần kết quả ngừng hiển thị.
* Cô lập lỗi theo từng sản phẩm và từng bản ghi để dữ liệu bất thường không làm gián đoạn toàn bộ trang xổ số.

= 0.31.4 =
* Sửa dãy số Mega/Power dạng danh sách bị mất khi ghép metadata Jackpot, giữ đầy đủ cơ cấu giải và hiệu chỉnh Keno lệch một ngày UTC.
* Đưa Điện toán thành lưới ba cột, chuẩn hóa vòng số, bốn màu Chẵn/Lẻ/Lớn/Nhỏ và lịch sử dạng từng kỳ.
* Thêm bộ chọn ngày giới hạn theo khoảng dữ liệu cục bộ; giữ tối thiểu 10 kỳ trước với truy vấn có giới hạn.
* Tách Bảng Đặc Biệt Tuần khỏi overview; thu gọn header/padding và bổ sung nội dung phụ trợ cho xổ số, thời tiết, vàng, cà phê.

= 0.31.3 =
* Mở rộng bộ đọc Vietlott cho drawDate, draw_number, productName, winningNumbers và chuỗi kết quả phân cách.
* Làm mới giao diện responsive cho bảng miền Bắc, thẻ Vietlott, Keno, cơ cấu giải và bảng các kỳ trước.
* Hiển thị mặc định tối thiểu 10 kỳ trước; thêm shortcode Bảng Đặc Biệt Tuần tính từ dữ liệu cục bộ.
* Lọc lịch sử ngay trong SQL và bổ sung chỉ mục theo miền/ngày để giảm số bản ghi phải hydrate trong PHP.

= 0.31.2 =
* Nhận đúng kết quả xổ số truyền thống XoSoAPI dạng `prizeCode` + `values`; cập nhật trạng thái, giải đặc biệt và bảng kết quả ba miền.

= 0.31.1 =
* Sửa lỗi ghi MySQL làm API đọc thành công nhưng shortcode vẫn giữ dữ liệu “Đang cập nhật”.
* Đọc đúng dãy số Vietlott ở winningNumbers tách biệt với bảng cơ cấu giải và ưu tiên kỳ đã có kết quả.
* Đưa tiêu đề sản phẩm vào header của chính bảng/thẻ, bỏ tiêu đề lớn lặp lại và gom các kỳ trước thành đúng một bảng tổng hợp.
* Chuyển điều hướng quản trị thành ba trang thực, làm mới phân trang có phạm vi bản ghi và nút Trước/Sau rõ ràng.
* Loại shortcode xổ số riêng bị lặp khi trang đã dùng shortcode tổng; tiếp tục giữ shortcode riêng cho trang tùy biến độc lập.

= 0.31.0 =
* Không cho phản hồi xổ số rỗng từ endpoint phụ ghi đè kết quả đầy đủ; gộp bản ghi theo mức độ hoàn chỉnh trước khi lưu.
* Chuyển bảng xổ số ba miền trên mobile sang từng khối giải và đài, không ép co cột và không cần cuộn ngang.
* Sửa mã Arabica của Commodities-API thành COFFEE và tách khóa VNAppMob tỷ giá khỏi khóa giá vàng theo đúng scope.
* Hiển thị trạng thái đồng bộ riêng cho từng nguồn giá và phân trang dữ liệu trong quản trị.
* Bỏ FastCGI cache cho các trang dữ liệu động, sửa đường dẫn build Docker Compose và bổ sung Docker Secret tỷ giá.

= 0.30.0 =
* Thêm shortcode tổng có điều hướng cho giá cà phê, giá vàng và xổ số; toàn bộ shortcode riêng vẫn được giữ để quản trị viên tự bố trí.
* Gộp SJC, DOJI và PNJ thành một bảng vàng trong nước theo nguồn VNAppMob, tách rõ vàng thế giới và loại bỏ các khối giá lặp.
* Gộp ba miền, Vietlott, Điện toán và các kỳ quay trước vào một bố cục xổ số có cấu trúc; lịch sử dùng một bảng tổng hợp thay vì nhiều bảng rời.
* Tự khôi phục stylesheet frontend nếu theme hoặc plugin tối ưu gỡ đăng ký tài nguyên sau khi shortcode đã được nhận diện.
* Bỏ chiều rộng tối thiểu gây cuộn ngang trên mobile; bảng xổ số, bảng giải, bảng kỳ hạn và lịch sử chuyển sang bố cục co giãn hoặc dạng thẻ.
* Giảm kích thước số nổi bật và làm biểu đồ SVG, TradingView responsive theo chiều rộng màn hình.

= 0.29.1 =
* Ưu tiên kết quả xổ số đã đủ giải và đủ dãy số, không giữ trạng thái đang cập nhật chỉ vì trạng thái kỳ quay từ nhà cung cấp đổi chậm.
* Shortcode Vietlott hiện tại chỉ chọn kỳ mới nhất; shortcode lịch sử tiếp tục hiển thị số kỳ theo cấu hình riêng.
* Giá vàng, cà phê và tỷ giá đã lưu gần nhất vẫn hiển thị khi dữ liệu hôm nay đang được cập nhật.
* Chuyển trang quản trị giá sang một cột toàn chiều rộng; bảng dữ liệu ở giữa và cấu hình, shortcode, hướng dẫn nằm cuối trang trong mục thu gọn.

= 0.29.0 =
* Thêm dự báo thời tiết hiện tại và bốn ngày tiếp theo được làm mới bằng tiến trình nền, có cache và dữ liệu dự phòng khi nguồn tạm lỗi.
* Bổ sung shortcode dự báo gọn không tải bản đồ; làm mới responsive cho thời tiết, giá cà phê, giá vàng và tỷ giá.
* Rút gọn thuật ngữ nhà cung cấp trên frontend, đồng thời giữ chi tiết nguồn và cấu hình trong trang quản trị.
* Giảm cột kỹ thuật của bảng cà phê thế giới, dùng tiêu đề riêng cho từng shortcode và mã HTML định danh duy nhất khi ghép nhiều bảng.
* Bổ sung bộ prebuild Coolify gồm WordPress PHP 8.5 FPM, Nginx, Redis, Docker Secrets, cron riêng và giới hạn truy cập REST/webhook.

= 0.28.0 =
* Thêm shortcode lịch sử riêng cho Mega, Power, Max 3D/3D+/3D Pro, Keno và ba sản phẩm xổ số điện toán.
* Bổ sung shortcode lịch sử tổng quát theo sản phẩm với limit từ 1 đến 30 kỳ.
* Thu gọn tiêu đề Vietlott/Điện toán, giữ đúng tên sản phẩm và tăng diện tích cho kết quả.
* Cho phép khóa API ứng dụng bằng WordPress Application Password và ngăn công cụ tìm kiếm lập chỉ mục phản hồi API.
* Bổ sung hướng dẫn bảo mật Coolify/Nginx, Docker Secrets, webhook, REST, RSS, XML-RPC và cron.

= 0.27.0 =
* Đồng bộ bốn endpoint xổ số mỗi 15 phút, tối đa khoảng 384 request/ngày cho gói Cơ Bản 2.000 request/ngày.
* Hoàn thiện bảng Mega/Power với dãy trùng khớp, Jackpot riêng và cơ cấu giải đầy đủ.
* Thêm giao diện chuyên biệt cho Điện toán 123, 6x36, Thần Tài và lịch sử Keno.
* Giữ bảng ba miền ở kích thước đọc được trên mobile, cuộn ngang mượt thay vì ép co cột.
* Giới hạn kích thước webhook, kiểm tra định dạng chữ ký HMAC-SHA256 và giữ bí mật hoàn toàn phía máy chủ.
* Hiển thị gói API, hạn dùng và ngân sách request dự kiến trong quản trị.

= 0.26.1 =
* Gộp đúng một cột miền Bắc khi endpoint tổng hợp và endpoint chi tiết trả cùng kỳ quay.
* Chỉ lấy dãy số trúng làm bóng Vietlott, không đọc nhầm mã giải, tên giải hoặc giá trị jackpot.
* Nhận diện sản phẩm Vietlott từ cả payload kết quả cũ và tăng phạm vi lấy lịch sử Vietlott.
* Giữ đúng số đài mới nhất của miền Trung, miền Nam và sắp xếp cột ổn định.
* Thu gọn masthead, bóng số và khoảng cách shortcode trên desktop lẫn điện thoại.

= 0.26.0 =
* Lưu đầy đủ kết quả, bảng giải, jackpot và mã kỳ quay từ XoSoAPI cho Vietlott/Điện toán.
* Bổ sung nguồn tổng hợp mới nhất để Keno không làm thiếu Mega, Power và Max 3D.
* Cân bằng danh sách kết quả quản trị theo từng sản phẩm, kèm trạng thái và kết quả chính.
* Làm mới màn hình quản trị với KPI, công tắc trực quan và ghi chú dạng dropdown cuối trang.
* Hiển thị bảng giải Vietlott hiện đại, responsive và phục hồi dữ liệu đầy đủ từ payload cũ.

= 0.25.1 =
* Đọc đúng cấu trúc prizeCode/value của XoSoAPI v2 cho xổ số ba miền.
* Giữ bản ghi miền Bắc chưa có giải để hiển thị bảng “Đang cập nhật”.
* Hiển thị ba shortcode miền Bắc, Trung, Nam theo chiều dọc với tiêu đề bảng gọn và responsive.
* Bỏ dòng cảnh báo tham khảo dưới toàn bộ giao diện xổ số.

= 0.25.0 =
* Làm mới giao diện xổ số ba miền và Vietlott theo dạng bảng chuyên nghiệp, responsive.
* Hiển thị vòng loading đúng số giải khi kỳ quay đang diễn ra và “Đang cập nhật” khi chưa có dữ liệu.
* Thêm nút sao chép cho từng shortcode xổ số trong quản trị.
* Bảo đảm mỗi shortcode cà phê, vàng và tỷ giá hiển thị độc lập; chặn giá cà phê bất thường do sai dấu phân cách.
* Làm mới đồng thời Gold API và các bảng VNAppMob đã cấu hình để shortcode SJC, DOJI, PNJ có dữ liệu riêng.

= 0.24.0 =
* Tách shortcode cà phê nội địa, Robusta, Arabica và giá vàng theo từng nguồn để không gộp chung bảng.
* Tích hợp VNAppMob cho SJC, DOJI, PNJ và tỷ giá theo ngân hàng; bổ sung lựa chọn nguồn trong admin.
* Bổ sung shortcode Gold API, tỷ giá và iframe responsive.
* Cho phép xóa địa điểm cùng bí danh và liên kết lịch bằng giao dịch database có xác nhận.

= 0.23.1 =
* Hiển thị “Đang cập nhật” khi dữ liệu giá bị thiếu hoặc chưa có bản ghi của ngày hiện tại.
* Bảo đảm CSS shortcode được nạp cho block động, đồng thời cải thiện responsive và bàn phím cho xổ số, thời tiết.
* Loại bỏ mô tả kỹ thuật về cách lưu dữ liệu khỏi phần đầu kết quả xổ số.

= 0.23.0 =
* Chuẩn hóa các đầu vào quản trị cho PHP 8.5, tránh lỗi kiểu dữ liệu từ request không hợp lệ.
* Xác minh đường kích hoạt, migration và truy vấn cleanup tương thích MySQL 8.4.
* Rà soát toàn bộ cú pháp PHP, JavaScript, tài nguyên cục bộ và cấu hình bí mật trước khi đóng gói.
* Giữ schema 1.7.1 và cơ chế nâng cấp idempotent, không tạo lại hoặc làm mất dữ liệu hiện có.

= 0.22.0 =
* Khôi phục REST route xổ số và thêm endpoint GET kiểm tra trạng thái an toàn.
* Tự tạo, mã hóa và cho phép xoay vòng webhook secret.
* Chuẩn hóa metadata xổ số cũ để shortcode nhận đúng từng loại vé.
* Hỗ trợ Docker Secrets runtime cho XoSoAPI, Commodities-API, bản đồ và Cloudflare.
* Sửa giao diện tra cứu Hero, màn hình webhook và thứ tự Cúp Điện Lâm Đồng.

= 0.21.0 =
* Tách shortcode giao diện riêng cho xổ số miền Bắc, miền Trung và miền Nam.
* Bổ sung shortcode riêng cho Mega 6/45, Power 6/55, Max 3D, Max 3D+, Max 3D Pro và Keno.
* Bổ sung shortcode Điện toán 123, Điện toán 6x36 và Thần Tài 4.
* Chuẩn hóa giao diện bảng, thẻ kết quả, bóng số và lịch sử Keno trên máy tính, máy tính bảng và điện thoại.
* Frontend chỉ đọc dữ liệu đã lưu; tiến trình nền đồng bộ mỗi 15 phút để ưu tiên kết quả trong ngày.
* Chuẩn hóa bí mật tích hợp qua biến môi trường Coolify, không đóng gói khóa vào mã nguồn hoặc image Docker.

= 0.20.0 =
* Bổ sung kết quả xổ số từ XoSoAPI với API key mã hóa và đồng bộ phía máy chủ.
* Lưu kết quả idempotent, có index theo ngày/miền/tỉnh và retention 24 tháng.
* Thêm webhook HMAC-SHA256, trang quản trị và shortcode kết quả responsive.
* Frontend không gọi API trực tiếp và không cung cấp nội dung dự đoán hoặc soi cầu.
* Giữ Commodities-API cho ROBUSTA/ARABICA quốc tế; giá cà phê trong nước do quản trị viên cập nhật.

= 0.19.0 =
* Tích hợp Giavang.now cho XAU/USD và nhiều thương hiệu vàng trong nước.
* Thêm WiFeed có API key mã hóa cho dữ liệu vàng và tỷ giá USD/VND.
* Thêm Commodities-API cho ROBUSTA và ARABICA; giá cà phê trong nước vẫn do quản trị viên biên tập.
* Chuẩn hóa fallback nhà cung cấp, giới hạn phản hồi, khóa đồng bộ và đơn vị giá.

= 0.18.5 =
* Bắt buộc và xác thực email, số điện thoại Việt Nam trong quy trình ủng hộ.
* Sửa cách đọc dấu phân cách hàng nghìn của giá VND và tự phục hồi bản ghi vàng cũ bị lệch 1.000 lần.
* Hợp nhất phần giải thích và nguồn đối chiếu của bộ shortcode giá, tránh lặp nội dung dưới từng bảng.
* Bổ sung biểu đồ XAU/USD TradingView tải lười cho trang so sánh giá vàng.
* Làm lại thanh tìm khu vực trong Hero với trạng thái focus và responsive rõ ràng.

= 0.18.4 =
* Loại bỏ nội dung bảng giá vàng bị lặp khi nhiều shortcode cùng xuất hiện trên một trang.
* Bổ sung biểu đồ lịch sử SJC theo nhãn sản phẩm và biểu đồ giá giao ngay XAU/USD.
* Trình bày XAU/USD đúng bản chất giá tham chiếu, không tạo dữ liệu mua/bán không có từ nguồn.
* Đưa tỷ giá USD/VND thành thông tin đối chiếu gọn trong bảng hàng hóa.
* Cân bằng chiều cao, màu sắc Hero và bảng trạng thái cập nhật.
* Bổ sung biểu tượng PWA dự phòng.

= 0.18.3 =
* Sửa URL tile raster MapTiler và cho phép kiểm tra API key mới trước khi lưu.
* Bổ sung thao tác sửa đầy đủ cho dữ liệu giá cà phê và giá vàng.
* Đổi Hero thành một thanh tìm khu vực có autocomplete, giảm viền và làm gọn thông tin tin cậy.
* Cải thiện biểu đồ giá vàng và quy trình ủng hộ hai bước.
* Giữ migration MySQL 8.4 idempotent và an toàn sau lần nâng cấp dở dang.

= 0.18.1 =
* Cho phép kích hoạt trong môi trường production khóa sửa file từ WordPress.
* Cải thiện log activation để ghi rõ class lỗi, file và dòng gây lỗi.
* Rà soát migration: mỗi bảng chỉ được tạo một lần và đủ toàn bộ bảng bắt buộc.

= 0.18.0 =
* Sửa endpoint MapTiler raster cho Leaflet, loại bỏ Mapbox và bổ sung Stadia Maps.
* Thêm tìm nhanh đơn vị điện lực trong hero, thu gọn thống kê và cân bằng các lịch gần nhất/vừa cập nhật.
* Bổ sung kiểm tra SEO trang chủ trong quản trị và nội dung hướng dẫn hữu ích cuối trang.

= 0.17.0 =
* Tách PWA worker và OneSignal worker để tránh xung đột scope và lỗi 404 do máy chủ tĩnh.
* Bổ sung dữ liệu cà phê theo tỉnh, hồ tiêu, USD/VND và đầy đủ trường hợp đồng cà phê kỳ hạn.
* Tích hợp cập nhật XAU/USD qua Gold API, lịch sử và biểu đồ mua/bán vàng trong nước.
* Thiết kế lại quy trình ủng hộ hai bước với nội dung chuyển khoản ngẫu nhiên, không yêu cầu mã giao dịch.
* Đổi menu quản trị thành Cúp Điện Lâm Đồng và nâng schema lên 1.6.0.

= 0.16.0 =

* Bổ sung lịch sử giá cà phê Lâm Đồng và tự tính thay đổi so với lần nhập trước.
* Bổ sung bảng giá cà phê trực tuyến, vàng trong nước và vàng thế giới.
* Hợp nhất PWA và OneSignal trên một service worker phạm vi gốc.

= 0.15.2 =
* Sửa Web Push OneSignal v16, thêm App ID triển khai và chuông thông báo nổi không chiếm diện tích Hero.
* Chuông hiển thị đúng trạng thái bật, tắt, bị chặn và nằm trên nút cuộn lên đầu trang của theme.
* Tiếp tục dùng worker OneSignal riêng để không xung đột worker PWA phạm vi toàn website.

= 0.15.1 =

* Tách hoàn toàn màn hình quản trị Giá cà phê và Giá vàng.
* Hiển thị shortcode tương ứng ngay trong từng màn hình để gắn vào Page.

= 0.15.0 =

* Thêm quản lý giá thị trường có nguồn và ngày cập nhật.
* Thêm shortcode giá cà phê Lâm Đồng, trong nước, quốc tế và giá vàng.
* Thêm nút xử lý thủ công một lô URL Cloudflare khi cron bị chậm.
* Tối ưu giao diện shortcode ủng hộ và tài liệu kiến trúc dài hạn.

= 0.14.0 =
* Thêm REST API công khai phiên bản v1, tắt mặc định và chỉ trả lịch đã xuất bản của đơn vị công khai.
* Dùng cursor có chữ ký thay cho OFFSET; giới hạn tối đa 100 bản ghi và 31 ngày cho mỗi yêu cầu.
* Thêm ETag, cache HTTP, giới hạn truy cập theo IP và cơ chế tương thích Redis/transient.
* Không công khai dữ liệu nguồn, hash chống trùng, lịch sử nhập hoặc chi tiết quản trị.
* Bổ sung index truy vấn API, hợp đồng OpenAPI, kiểm tra trạng thái hệ thống và checklist vận hành sau reverse proxy.

= 0.13.0 =
* Thêm manifest PWA và service worker phạm vi toàn website, không cache HTML lịch điện để tránh dữ liệu cũ.
* Gợi ý thêm website vào màn hình chính sau số phiên truy cập có thể cấu hình; không làm phiền người dùng ngay lần đầu.
* Hỗ trợ hộp cài native trên Chromium và hướng dẫn Add to Home Screen riêng cho iPhone/iPad.
* Thêm tab quản trị PWA với tên ứng dụng, biểu tượng, màu sắc, độ trễ, ngưỡng truy cập và thời gian nhắc lại.
* Không ghi đè service worker toàn website của hệ thống khác và bổ sung kiểm tra PWA trong Trạng thái hệ thống.

= 0.12.0 =
* Hero trả lời trực tiếp nhu cầu kiểm tra cúp điện, tích hợp biểu mẫu chọn ngày và đơn vị điện lực ngay tại CTA chính.
* Card Hero hiển thị động số khu vực hôm nay, ngày mai, số khung giờ bảy ngày tới và thời điểm cập nhật gần nhất bằng truy vấn có cache.
* Thêm Web Push tự nguyện qua OneSignal, cấu hình bật/tắt trong quản trị, service worker riêng và không xin quyền trước khi người dùng bấm.
* Chuẩn hóa giao diện Hero trên desktop, tablet và mobile; dọn CSS CTA cũ không còn sử dụng.
* Làm rõ các nhãn bản đồ và empty state bằng cụm từ “lịch cúp điện”.

= 0.11.0 =
* Sửa nút xem bản đồ để luôn mở hộp thoại và hiển thị lỗi cấu hình rõ ràng thay vì không phản hồi.
* Chỉ Hero trang chủ dùng chiều rộng toàn màn hình; các phần còn lại bám theo container của giao diện.
* Hero cung cấp H1 có thể cấu hình và bổ sung nội dung SEO hữu ích cuối trang.
* Bổ sung luồng xác nhận cộng đồng và danh sách đối chiếu thủ công.
* Thêm bảng donation có index chống trùng, backup/restore, công cụ xuất/xóa dữ liệu cá nhân và giới hạn chống gửi liên tục.

= 0.10.0 =
* Cho phép nhập Mapbox, MapTiler và Cloudflare API token trong quản trị dưới dạng bí mật mã hóa; cấu hình máy chủ vẫn được ưu tiên.
* Không đưa bí mật mã hóa vào bản sao lưu và không hiển thị lại khóa trong biểu mẫu.
* Thu gọn Hero, nâng cấp CTA kiểu tra cứu và giữ nội dung theo layout của theme.
* Thêm hiệu ứng nhẹ cho trạng thái đang cúp, tự tắt khi người dùng chọn giảm chuyển động.
* Bổ sung kiến trúc Freemium/Premium không khóa chức năng tra cứu cơ bản.

= 0.9.0 =
* Thiết kế Hero trang chủ full-width trong khi các phần nội dung còn lại vẫn bám layout của theme.
* Bổ sung lựa chọn Mapbox và MapTiler cho Leaflet, kiểm tra tile trực tiếp từ WordPress và giữ tùy chọn OpenStreetMap/tile riêng.
* Thêm hàng đợi xóa cache Cloudflare theo đúng URL bị ảnh hưởng, giới hạn theo lô và tự thử lại.
* Bổ sung hướng dẫn vận hành Telegram, generic webhook, Zalo OA, Cloudflare và định hướng tích hợp SMS an toàn.

= 0.8.0 =
* Nâng cấp shortcode trang chủ với Hero hiện đại, hai vị trí quảng cáo an toàn và bố cục full width.
* Ưu tiên hiển thị các khu vực cần chú ý trong hôm nay và thêm hiệu ứng trạng thái có hỗ trợ reduced motion.
* Quét tối đa 31 ngày nhưng chỉ hiển thị tám ngày có lịch đầu tiên theo lưới hai hàng trên desktop.
* Làm nổi bật lịch gần nhất và mở rộng danh sách khu vực bằng nút xem thêm có số lượng còn lại.
* Bổ sung nội dung hướng dẫn, mục lục và bảng thông tin SEO tự nhiên, tránh tuyên bố nguồn chính thức hoặc độ chính xác tuyệt đối.

= 0.7.1 =
* Sửa cleanup orphan tương thích MySQL bằng quy trình chọn khóa rồi xóa theo lô.
* Giới hạn cập nhật trạng thái và retention để tránh khóa bảng kéo dài.
* Cho phép bắt kịp backlog bằng tối đa năm lượt nhỏ, có ngân sách thời gian.
* Thêm index dành riêng cho cleanup và integration test chống tái phát.

= 0.7.0 =
* Thêm hàng đợi thông báo bền vững, chống trùng và tự thử lại.
* Thêm Telegram, generic webhook có HMAC và adapter Zalo OA.
* Thêm cảnh báo sức khỏe hàng đợi và địa điểm chưa liên kết.
* Thêm benchmark database chỉ đọc.
* Thêm PHPUnit, PHPCS, PHPCompatibility và integration tests.
* Thêm cấu hình trang chủ tổng hợp, danh sách khu vực mở rộng và nội dung hướng dẫn hữu ích.
* Kiểm tra checksum thư viện Leaflet chính thức trong Trạng thái hệ thống.
