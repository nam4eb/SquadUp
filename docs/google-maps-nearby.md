# Tìm kèo quanh vị trí với Google Maps

## Phân tích và luồng đã tích hợp

Explore trước đây dùng OpenStreetMap. API `/api/v1/explore` đã có bộ lọc bán kính, khoảng cách, môn thể thao, trình độ, hình thức chơi và chỗ trống. Tích hợp sử dụng lại API này, không tìm kèo qua Google Places.

- Chọn Explore → Nearby radius → Use current location, cấp quyền vị trí khi được hỏi.
- Chọn bán kính rồi chuyển Map. Marker hiển thị các kèo đã tải có tọa độ; chạm marker rồi thẻ phía dưới để mở chi tiết và tham gia bằng luồng hiện có.
- Kéo bản đồ rồi bấm Search this area để tìm tại tâm mới. Chấm xanh vẫn là vị trí GPS đã lấy, tâm tìm kiếm có thể khác.
- Không cấp quyền: chọn vùng trên bản đồ hoặc nhập cặp tọa độ hợp lệ. Xóa cả hai tọa độ và tìm lại để bỏ giới hạn bán kính.
- Vùng trống vẫn giữ bản đồ để chọn vùng khác. Load more tải thêm trang, tránh hiểu marker trang đầu là toàn bộ dữ liệu.
- Khi chưa có khóa hoặc kết nối tải Maps thất bại, dùng List. Vị trí không lưu vào SharedPreferences; chỉ gửi đến API khi tìm kiếm. Google nhận kết nối bản đồ từ thiết bị.

## Cấu hình

Copy `config/maps.example.json` thành `config/maps.web.local.json`, điền GOOGLE_MAPS_API_KEY. File `maps.*.local.json` đã được gitignore. Dùng file riêng cho Android/iOS, cập nhật API_BASE_URL phù hợp thiết bị (127.0.0.1 trên điện thoại trỏ chính điện thoại).

Google Cloud cần billing và bật API tương ứng: Maps JavaScript API (web), Maps SDK for Android, Maps SDK for iOS. Tạo khóa riêng mỗi nền tảng và giới hạn khóa theo website/referrer, Android package + SHA-1 hoặc iOS bundle ID. Khóa client có thể đọc được trong bản build; bảo vệ bằng hạn chế ứng dụng/API và quota, không dựa vào việc giấu khóa.

```powershell
flutter build web --no-web-resources-cdn --no-wasm-dry-run --dart-define-from-file=config/maps.web.local.json
flutter run --dart-define-from-file=config/maps.android.local.json
# Trên macOS:
flutter run --dart-define-from-file=config/maps.ios.local.json
```

Android đọc cùng dart-define vào manifest; minSdk 24. iOS đọc DART_DEFINES qua Info.plist và cấu hình GMSServices; deployment target 14, dùng CocoaPods của plugin mặc định. Web tải Maps JS khi mở bản đồ, không chặn lúc mở danh sách. GPS trên web cần HTTPS hoặc localhost.

Tài liệu chính thức: [Google Maps Flutter setup](https://developers.google.com/maps/flutter-package/config), [plugin Flutter](https://pub.dev/packages/google_maps_flutter), [iOS implementation](https://pub.dev/packages/google_maps_flutter_ios).

## Hiệu năng và phần cần đo tiếp

- Không gọi API theo từng chuyển động camera; chỉ truy vấn khi xác nhận tìm vùng.
- API nearby phân trang 20 kèo, PostgreSQL lọc bounding box trước khi tính khoảng cách. UI bỏ response lỗi thời khi đổi bộ lọc nhanh.
- Chưa thêm cache dùng chung cho kết quả cá nhân hóa để tránh lẫn quyền xem giữa người dùng.
- Bước tiếp theo khi dữ liệu lớn: đo p95 và EXPLAIN ANALYZE trên PostgreSQL với dữ liệu thực; cân nhắc PostGIS/GiST nếu truy vấn khoảng cách là nút thắt; thêm clustering khi nhiều marker. Marker trùng tọa độ hiện có thể chồng nhau, dùng List để chọn từng kèo.
- Tìm kiếm chưa có geocoding tên địa chỉ hay autocomplete Google Places. Backend hiện dùng bounding box thông thường, cần mở rộng xử lý kinh tuyến 180°/vùng cực trước khi triển khai toàn cầu.

## Kiểm chứng

Flutter analyze và build web thành công. ExploreTest kiểm tra bán kính, bộ lọc, quyền xem, tọa độ venue; bổ sung kiểm tra phân trang theo khoảng cách, tọa độ thiếu/sai, dữ liệu thiếu tọa độ và chặn người dùng trong nearby.

Chưa kiểm chứng tile/marker Google thực tế do chưa có API key. Chưa build Android/iOS trong lượt này. Test API chạy trên SQLite cô lập, không thay thế đo hiệu năng và kiểm thử nhánh SQL PostgreSQL. Bản web local build không khóa hiển thị fallback List.
