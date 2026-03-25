#!/bin/bash

# NHỚ THAY EMAIL CỦA BẠN VÀO ĐÂY ĐỂ LET'S ENCRYPT GỬI THÔNG BÁO GIA HẠN
email="email_cua_ban@gmail.com"
domain="crmquanly.nhmsoft.com"

echo "### BẮT ĐẦU QUÁ TRÌNH LẤY CHỨNG CHỈ SSL TỰ ĐỘNG CHO $domain ###"

echo "1. Tạo chứng chỉ giả (dummy certificate) để Nginx có thể khởi động..."
path="./certbot/conf/live/$domain"
mkdir -p "$path"
docker compose run --rm --entrypoint "\
  openssl req -x509 -nodes -newkey rsa:4096 -days 1 \
    -keyout '/etc/letsencrypt/live/$domain/privkey.pem' \
    -out '/etc/letsencrypt/live/$domain/fullchain.pem' \
    -subj '/CN=localhost'" certbot

echo "2. Khởi động Nginx (với chứng chỉ giả)..."
docker compose up --force-recreate -d nginx

echo "3. Xóa chứng chỉ giả..."
docker compose run --rm --entrypoint "\
  rm -Rf /etc/letsencrypt/live/$domain && \
  rm -Rf /etc/letsencrypt/archive/$domain && \
  rm -Rf /etc/letsencrypt/renewal/$domain.conf" certbot

echo "4. Yêu cầu chứng chỉ thật từ Let's Encrypt..."
docker compose run --rm --entrypoint "\
  certbot certonly --webroot -w /var/www/certbot \
    --email $email \
    -d $domain \
    --rsa-key-size 4096 \
    --agree-tos \
    --force-renewal \
    --no-eff-email" certbot

echo "5. Tải lại cấu hình Nginx để nhận chứng chỉ thật..."
docker compose exec nginx nginx -s reload

echo "### HOÀN TẤT! HỆ THỐNG ĐÃ CÓ SSL CHÍNH THỨC ###"
echo "Bây giờ bạn có thể khởi động toàn bộ dự án bằng lệnh:"
echo "docker compose up -d"
