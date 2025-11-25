// (function () {
//     // Không cần activityTimer vì Server Middleware chịu trách nhiệm logout timeout.
//     // let activityTimer;
//     const TIMEOUT = 15 * 60 * 1000; // 15 phút - Dùng cho cảnh báo client nếu cần
//     const HEARTBEAT_INTERVAL = 60 * 1000; // 1 phút gửi heartbeat

//     // Ping server để update last_activity
//     function sendHeartbeat() {
//         fetch('/heartbeat', {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/json',
//                 // Đảm bảo CSRF token tồn tại
//                 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]') ?
//                     document.querySelector('meta[name="csrf-token"]').content : ''
//             },
//             credentials: 'same-origin'
//         }).then(response => {
//             if (response.status === 401) {
//                 // Server trả về 401 (Unauthorized) -> Session đã bị Server/Middleware xóa.
//                 // Chuyển hướng người dùng để đăng nhập lại.
//                 window.location.href = '/login?timeout=1';
//             }
//         }).catch(err => {
//             // Lỗi mạng/kết nối có thể xảy ra. Thường không cần phải chuyển hướng ngay.
//             // Nếu lỗi liên tục, Heartbeat tiếp theo sẽ bắt lỗi 401.
//             console.error('Heartbeat failed:', err);
//         });
//     }

//     // Detect user activity và GỬI HEARTBEAT ngay lập tức
//     function resetActivity() {
//         // Mỗi khi có hoạt động, gửi Heartbeat ngay để cập nhật session.
//         sendHeartbeat();
//     }

//     // Track user activities
//     ['mousedown', 'keypress', 'scroll', 'touchstart', 'click'].forEach(event => {
//         // Gọi resetActivity (gửi Heartbeat) ngay khi có hoạt động
//         document.addEventListener(event, resetActivity, true);
//     });

//     // Periodic heartbeat
//     // Đảm bảo hoạt động ngay cả khi user chỉ đọc mà không tương tác
//     setInterval(sendHeartbeat, HEARTBEAT_INTERVAL);

//     // Handle page visibility (tab switching)
//     document.addEventListener('visibilitychange', () => {
//         if (!document.hidden) {
//             sendHeartbeat();
//         }
//     });

//     // Handle beforeunload (đóng trình duyệt/tab)
//     window.addEventListener('beforeunload', () => {
//         // Dùng sendBeacon để đảm bảo request gửi đi ngay cả khi tab đóng
//         const success = navigator.sendBeacon('/user-leaving', JSON.stringify({
//             timestamp: Date.now()
//         }));
//         // Bạn có thể log trạng thái thành công/thất bại của sendBeacon nếu cần
//         // console.log('SendBeacon status:', success);
//     });

//     // Initialize - Gửi Heartbeat lần đầu khi script được tải
//     sendHeartbeat();
// })();
