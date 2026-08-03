# Trang chờ deploy trên Coolify

Có hai lớp bảo vệ, phục vụ hai lỗi khác nhau.

| Lớp | Bao phủ | Trạng thái |
| --- | --- | --- |
| Nginx trong stack WordPress | PHP-FPM/WordPress đang khởi động, lỗi 502/503/504 | Đã nằm trong `docker-compose.yml` chính. |
| Maintenance service độc lập + Traefik | Traefik không còn backend healthy và trả `No available server` | Cần deploy một lần như Coolify resource riêng. |

Trang chờ trả HTTP `503`, `Retry-After: 120`, `Cache-Control: no-store` và
`X-Robots-Tag: noindex, nofollow`. Vì vậy không làm cache nhầm hoặc để máy tìm
kiếm lập chỉ mục nội dung bảo trì.

## 1. Deploy maintenance service độc lập

Tạo một **Docker Compose resource mới** trong cùng server Coolify, dùng repository
này và Compose file:

```text
maintenance/docker-compose.yml
```

Đặt tên resource là `cupdien-maintenance`. Gán cho service một domain riêng, ví
dụ `https://maintenance.cupdienlamdong.com`, để Coolify gắn Traefik vào network
của resource và theo dõi health check `/healthz`. Domain này chỉ là endpoint kỹ
thuật; trang đã có `noindex` và không liên kết từ website.

Deploy resource này và xác nhận nó **healthy** trước khi làm bước 2. Service có
tên Traefik cố định `cupdien-maintenance`, không dùng token hay biến môi trường.

## 2. Khai báo error middleware ở proxy server

Trong Coolify vào **Server → Proxy → Dynamic Configurations**, tạo file
`cupdienlamdong-error-pages.yml` và dán đúng nội dung từ:

```text
coolify/traefik/cupdienlamdong-error-pages.yml
```

Middleware này chuyển response `500–599` sang maintenance service độc lập. Nó
chỉ chạy khi upstream lỗi, không thay nội dung bình thường của website.

## 3. Gắn middleware vào service `nginx` của website

Sau khi hai bước trên đang healthy, thêm label sau trong service `nginx` của
`docker-compose.yml` chính rồi redeploy website:

```yaml
labels:
  - "coolify.traefik.middlewares=cupdienlamdong-errors@file"
```

Label chưa được bật sẵn trong repository để một deploy trước khi maintenance
service hoặc dynamic configuration tồn tại không thể làm router chính lỗi. Đây
là cơ chế shorthand chính thức của Coolify cho Docker Compose.

## Kiểm tra an toàn

1. Vào maintenance domain và xác nhận giao diện chờ cùng status `503`.
2. Mở website bình thường: phải trả nội dung thật, không phải maintenance page.
3. Thực hiện redeploy. Nếu container Nginx mới đã chạy trước PHP, trang chờ
   Nginx xuất hiện; nếu Traefik tạm không có backend, maintenance service riêng
   xuất hiện.
4. Sau deploy, kiểm tra `https://cupdienlamdong.com/` trả `200` và
   `X-FastCGI-Cache` bình thường.

Không thêm label ở bước 3 cho đến khi bước 1 và 2 hoàn tất. Docker Compose
deployments không có Rolling Update của Coolify; maintenance service độc lập là
lớp giữ trải nghiệm người dùng trong khoảng chuyển container đó.
