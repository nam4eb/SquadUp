# Rà soát chức năng và kế hoạch hiệu năng — 14/09/2026

## Phạm vi và mức độ xác nhận

Rà soát mã nguồn Flutter/Laravel, cấu hình Compose, test và báo cáo hiệu năng trong repository hiện tại. Đây là audit mã nguồn và kế hoạch triển khai, không phải xác nhận mọi chức năng đã chạy E2E.

Kiểm tra runtime hôm nay: Docker CLI không kết nối được `dockerDesktopLinuxEngine`; HTTP `/up` trên cả 8000 và 8001 bị connection refused. Không chạy lại test/load test, không đổi cấu hình hoặc dữ liệu ứng dụng trong lượt audit này. Các con số dưới đây là báo cáo lịch sử.

Không dùng phần Gap matrix cũ trong `squadup-product-completion-plan.md` như trạng thái hiện tại: mã nguồn đã có onboarding, sports, venues, Explore map, rating/reputation, story gắn activity, chat reconnect và outbox. Cần hoàn thiện/tích hợp/kiểm thử các phần này thay vì viết lại.

## Chức năng còn thiếu hoặc chưa khép kín

| Ưu tiên | Bằng chứng hiện tại | Tác động và việc cần làm | Tiêu chí nghiệm thu |
| --- | --- | --- | --- |
| P0 | `lib/presentation/home_feed_screen/home_feed_screen.dart`: tên Alex và thống kê cố định; View All có callback rỗng. `lib/features/activities/presentation/activity_category_strip.dart`: chip có callback rỗng | Home chưa phản ánh dữ liệu thật; nối profile/statistics, điều hướng Explore và category filter | Hai tài khoản thấy đúng dữ liệu riêng; View All và từng category mở danh sách được lọc |
| P0 | `lib/core/network/api_client.dart`: 401 chỉ xóa token; router không có redirect auth; `api_activity_feed.dart` che mọi lỗi bằng Reload activities | Phiên hết hạn, mất mạng và lỗi API đều khó phân biệt; xây auth state thống nhất, session restore, redirect và reset cache/provider khi đổi tài khoản | Test token hết hạn, logout/login tài khoản khác, offline, timeout, 500; không hiển thị dữ liệu người trước |
| P0 | `backend/routes/console.php` có lịch reminders và prune; các Compose/script được rà soát không khai báo tiến trình scheduler | Chưa chứng minh lịch thực sự được thực thi; thêm scheduler hoặc cấu hình cron được quản lý và heartbeat | Reminder chạy đúng lịch, không gửi lặp khi có nhiều instance; prune có log thành công |
| P0 | `docker-compose.fast.yml`: không có storage volume; media mặc định dùng public disk; APP_KEY fallback cố định | Upload nằm trong filesystem container; API nhanh và API thường cần dùng chung media và cấu hình khóa ứng dụng phù hợp | Upload qua API nhanh, đọc qua các service và sau recreate vẫn được; URL/chữ ký hoạt động đúng |
| P1 | `auth_form_widget.dart`: Forgot password callback rỗng | Backend có API nhưng đường thao tác trên màn đăng nhập này chưa nối | Gửi yêu cầu reset, mở link, đặt mật khẩu mới, xử lý token hết hạn; kiểm tra email thật ở staging |
| P1 | `StoryController::index()` kết thúc bằng `oldest()->get()` | Feed không phân trang; càng nhiều story càng tốn DB, RAM, payload và thời gian parse | API/client có cursor hoặc phân trang tương thích, giới hạn mỗi trang và không mất/trùng item |
| P1 | `test/app_smoke_test.dart` chỉ có một smoke test; chưa có `.github` trong checkout | Backend test chưa bảo đảm luồng app thực tế, đặc biệt auth/feed/chat/cache | Integration test login → Explore → tạo/join → chat → hoàn thành/rating; pipeline lặp lại được |
| P1 | Tài liệu hiện tại còn ghi thiếu kiểm tra HTTP đồng thời trên PostgreSQL | Test SQLite/stale client chưa đủ chứng minh tranh chấp slot ngoài thực tế | Nhiều người tranh slot cuối không vượt capacity; rating/message retry không nhân đôi |
| P1 | Có mã push/OAuth/deep-link nhưng audit này không xác minh môi trường dịch vụ ngoài | Chưa thể coi tích hợp đã sẵn sàng production chỉ từ mock test | Kiểm tra thiết bị thật foreground/background/terminated, refresh device token, OAuth callback và error path |
| P2 | Telemetry chỉ giữ 100 mẫu trong SharedPreferences; cache feed dùng hashCode của token và không có timestamp TTL/eviction xuyên phiên rõ ràng | Không có p95/p99 toàn hệ thống; cache cần ổn định và được quản lý theo tài khoản | Export telemetry có sampling, TTL/giới hạn tổng cache, xóa đúng khi logout và thay đổi quyền |

## Đọc đúng các số hiệu năng hiện có

Nguồn: `docs/performance/phase-6-octane-roadrunner.md`, `phase-7-soak-and-database.md`, `local-fast-runtime.md`.

| Kịch bản lịch sử | Kết quả | Giới hạn diễn giải |
| --- | --- | --- |
| Ramp 10 → 50 → 100 VU: FPM so với Octane | Overall p95 529 → 321 ms; throughput 145 → 219 req/s | Overall toàn run, không phải p95 riêng plateau 100 VU; chưa đạt ngưỡng 200 ms |
| Octane soak khoảng 170 giây, plateau 30 VU | p95 105 ms; 295 req/s; không lỗi | Seed nhỏ, thời gian ngắn; không chứng minh tải production hoặc không có memory leak |
| Profile sau soak, request tuần tự | DB p95 activities 6,85 ms; conversations 5,28 ms | Không kết luận DB là nút thắt chính hoặc luôn nhanh khi dataset lớn |
| Local Windows bind mount | Health warm khoảng 5,5 giây; Octane warm 401 khoảng 4–18 ms | Hai loại endpoint khác nhau; 401 không đại diện danh sách có xác thực và truy vấn DB |

Ưu tiên loại bỏ overhead runtime local và đo lại đúng nghiệp vụ. Hiện chưa có bằng chứng cần chuyển ngôn ngữ hoặc chia microservices.

## Kế hoạch triển khai

Ước lượng ngày công kỹ thuật, không phải cam kết lịch; phụ thuộc thiết bị test, staging và quy mô mục tiêu.

### Đợt 1 — Luồng sử dụng và môi trường đúng (3–5 ngày công)

1. Khôi phục môi trường chạy; thống nhất script chọn dev/fast, API_BASE_URL, WebSocket và broadcast auth URL. Giữ bind-mount cho sửa PHP, dùng image Linux/Octane cho đo hiệu năng.
2. Thống nhất APP_KEY và media storage giữa các service; thêm scheduler có giám sát.
3. Sửa auth/session, lỗi feed, Home dữ liệu thật, category và View All. Không tăng timeout để che backend chậm.
4. Chạy regression trong service test riêng; không chạy test phá database trong container API đang dùng.

Nghiệm thu: golden flow bằng tài khoản thật trên web/mobile, media tồn tại sau recreate, reminder chạy đúng, không cần seed lại để chữa lỗi UI.

### Đợt 2 — Baseline có thể lặp lại (2–3 ngày công)

1. Tạo database benchmark riêng với mức nhỏ/vừa/lớn, ví dụ 1k/10k/100k activities; có phân bố user, friendship, participants, story và message thực tế. Không đổ load data vào database demo.
2. Đo endpoint có auth: Explore có/không radius, activity detail, conversations/messages, stories, join và presence. Tách warm/cold, từng plateau, lỗi 429/5xx/network.
3. Thu p50/p95/p99, throughput, payload, DB time/query count, lock wait, CPU/RAM, queue age và latency frontend. Gắn build SHA, cấu hình máy, số worker và kích thước dữ liệu vào kết quả.
4. Dùng nhiều tài khoản và tần suất phù hợp limiter production; đo capacity nới limiter phải là kịch bản riêng, có nhãn rõ.

Mục tiêu ban đầu để thống nhất: tại 30 VU, authenticated read API p95 ≤200 ms, p99 ≤500 ms và lỗi không mong đợi <0,1%. Đây là mục tiêu đề xuất, không phải kết quả đã đạt trên phiên bản hiện tại.

### Đợt 3 — Tối ưu theo trace (4–6 ngày công)

1. Stories: phân trang, lazy load trên app, thumbnail ảnh/video và payload tối thiểu. Media nặng xử lý bằng job riêng; chỉ triển khai object storage/CDN sau khi đo lưu lượng và yêu cầu phân quyền.
2. Explore: EXPLAIN ANALYZE trên dataset đại diện trước khi thêm index. Kiểm tra count pagination, sort và công thức khoảng cách; chỉ dùng PostGIS/spatial index khi phép đo cho thấy cần.
3. Reputation: `ReputationService::refresh()` dùng nhiều count/avg riêng; gộp aggregate query, đo thời gian transaction và tranh chấp. Chuyển queue chỉ khi sản phẩm chấp nhận cập nhật trễ và có bảo đảm thứ tự/idempotency.
4. Flutter: profile trên thiết bị thật; giảm rebuild/request lặp, hủy search cũ, debounce, ảnh đúng kích thước; cache có TTL và invalidation theo session/mutation. Giữ dữ liệu đang hiển thị khi refresh, báo trạng thái stale khi cần.
5. Queue: tách job media với notification nếu queue age chứng minh có chặn nhau; giám sát retries/failed jobs, kiểm thử reconnect và replay không trùng.

Nghiệm thu: cùng workload và dataset cho so sánh trước/sau; không tăng memory/payload hoặc làm sai quyền để đổi lấy latency. Mục tiêu UI đề xuất: cache render ≤300 ms, tải feed mạng Wi-Fi chuẩn p95 ≤1 giây, không jank kéo dài; đo mobile bằng profile/release phù hợp.

### Đợt 4 — Chứng minh khả năng vận hành (3–5 ngày công, cộng thời gian soak)

1. Benchmark worker 2/4/8 theo CPU, RAM và giới hạn connection PostgreSQL; không tăng worker một cách mặc định.
2. Soak 2–4 giờ và canary staging/production theo quyền triển khai riêng; theo dõi memory slope sau warm-up, restart, auth isolation, p95/p99 và queue backlog.
3. Tạo CI lint/analyze/test/build, integration PostgreSQL concurrency, restore backup và smoke API. Test database phải được tách biệt và kiểm tra cấu hình trước khi migrate.
4. Rollback theo image tag và migration tương thích; giữ đường rollback FPM. Gate rollback đề xuất: lỗi hoặc p95 xấu hơn baseline >20% trong cửa sổ 10 phút, hoặc bất kỳ lỗi lẫn dữ liệu người dùng nào.

## Thứ tự khuyến nghị

Auth/feed/Home → storage/scheduler → baseline có xác thực → Stories/media và query theo profile → mobile/cache → soak/CI. Ước lượng tổng 12–19 ngày công, chưa gồm thời gian chờ credentials, kiểm thử store/thiết bị và hạ tầng ngoài.

## Tài liệu kỹ thuật đối chiếu

- Laravel scheduler cần tiến trình gọi lịch thực sự, không chỉ khai báo trong code: https://laravel.com/framework/docs/12.x/scheduling
- Flutter hướng dẫn profile và phân tích frame: https://docs.flutter.dev/perf/ui-performance
- Flutter performance best practices: https://docs.flutter.dev/perf/best-practices

Các liên kết trên hỗ trợ phương pháp triển khai/đo; kết luận cụ thể về SquadUp dựa vào mã nguồn và báo cáo local đã nêu.
