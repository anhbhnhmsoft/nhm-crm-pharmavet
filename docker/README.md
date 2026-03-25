# Hướng dẫn triển khai (Deployment Guide) - Pharmavet CRM

Tài liệu này hướng dẫn cách triển khai hệ thống Pharmavet CRM lên môi trường Production (VPS Ubuntu) sử dụng Docker, Nginx, PostgreSQL và tự động cấp phát chứng chỉ SSL với Let's Encrypt.

## 🚀 Yêu cầu hệ thống (Prerequisites)
1. VPS đã cài đặt sẵn **Docker Engine** và **Docker Compose**.
2. Đã trỏ tên miền (`crmquanly.nhmsoft.com`) về địa chỉ IP của VPS.
3. Đã clone toàn bộ mã nguồn dự án về VPS.

---

## ⚙️ Bước 1: Cấu hình biến môi trường (.env)

Di chuyển vào thư mục `docker` và tạo file `.env` (nằm cùng cấp với `docker-compose.yml`):

```bash
cd docker
nano .env
```


## Bước 2: Cấp quyền thư mục
```bash
sudo chown -R 33:33 storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```


## 🔒 Bước 3: Khởi tạo chứng chỉ SSL tự động
🔑 Nếu đây là lần đầu tiên chạy hệ thống trên VPS này và bạn chưa có chứng chỉ SSL cho domain crmquanly.nhmsoft.com, hãy chạy script tự động lấy SSL:
cd docker

```bash
chmod +x init-letsencrypt.sh
./init-letsencrypt.sh
```

Lưu ý: Script này sẽ tạo chứng chỉ giả để khởi động Nginx, sau đó yêu cầu Certbot lấy chứng chỉ thật từ Let's Encrypt và tự động reload Nginx. Hãy đảm bảo bạn đã mở cổng 80 và 443 trên Firewall của VPS.

## Bước 4: Khởi chạy toàn bộ hệ thống
# Đang đứng trong thư mục 'docker'
```bash
docker compose up -d
```

## Bước 5: Cài đặt Laravel (Cho lần chạy đầu tiên)
Nếu đây là bản deploy đầu tiên, bạn cần sinh khóa ứng dụng, chạy migration và link thư mục storage:
```bash
# 1. Sinh khóa ứng dụng mới (Nếu chưa có trong .env)
docker compose exec app php artisan key:generate

# 2. Chạy Migration tạo các bảng trong Database (và Seed nếu cần)
docker compose exec app php artisan migrate --force
# (Hoặc: docker compose exec app php artisan migrate --seed --force)

# 3. Tạo Symbolic link cho storage
docker compose exec app php artisan storage:link

# 4. Clear và tối ưu hóa Cache
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan optimize
```
