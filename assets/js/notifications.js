
class NotificationManager {
    constructor() {
        this.isInitialized = false;
        this.refreshInterval = null;
        this.notifications = [];
        this.unreadCount = 0;
    }

    /**
     * ເລີ່ມຕົ້ນລະບົບການແຈ້ງເຕືອນ
     */
    init() {
        if (this.isInitialized) return;

        this.createNotificationUI();
        this.loadNotifications();
        this.startAutoRefresh();
        this.bindEvents();
        
        this.isInitialized = true;
        console.log('Notification system initialized');
    }

    /**
     * ສ້າງ UI ສຳລັບການແຈ້ງເຕືອນ
     */
    createNotificationUI() {
        // ຫາ navbar ຫຼື header
        const navbar = document.querySelector('.navbar') || document.querySelector('header') || document.querySelector('.header');
        if (!navbar) {
            console.warn('Navbar not found, creating notification container');
            return;
        }

        // ສ້າງປຸ່ມການແຈ້ງເຕືອນ
        const notificationButton = document.createElement('div');
        notificationButton.className = 'notification-button-container';
        notificationButton.innerHTML = `
            <button id="notificationBtn" class="notification-btn relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <span id="notificationBadge" class="notification-badge absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full px-1 min-w-[16px] h-4 flex items-center justify-center hidden">0</span>
            </button>
        `;

        // ສ້າງ dropdown ການແຈ້ງເຕືອນ
        const notificationDropdown = document.createElement('div');
        notificationDropdown.id = 'notificationDropdown';
        notificationDropdown.className = 'notification-dropdown absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50 hidden';
        notificationDropdown.innerHTML = `
            <div class="notification-header p-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">ການແຈ້ງເຕືອນ</h3>
                    <button id="markAllReadBtn" class="text-sm text-blue-600 hover:text-blue-800">ອ່ານທັງໝົດ</button>
                </div>
            </div>
            <div class="notification-list max-h-96 overflow-y-auto">
                <div id="notificationItems"></div>
            </div>
            <div class="notification-footer p-3 border-t border-gray-200 text-center">
                <button id="loadMoreBtn" class="text-sm text-blue-600 hover:text-blue-800">ໂຫຼດເພີ່ມເຕີມ</button>
            </div>
        `;

        // ເພີ່ມ CSS styles
        this.addNotificationStyles();

        // ເພີ່ມເຂົ້າໃນ navbar
        const rightSection = navbar.querySelector('.navbar-nav:last-child') || navbar;
        const container = document.createElement('div');
        container.className = 'notification-container relative';
        container.appendChild(notificationButton);
        container.appendChild(notificationDropdown);
        rightSection.appendChild(container);
    }

    /**
     * ເພີ່ມ CSS styles
     */
    addNotificationStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .notification-container {
                position: relative;
                display: inline-block;
            }
            
            .notification-btn {
                transition: all 0.2s ease;
            }
            
            .notification-btn:hover {
                transform: scale(1.05);
            }
            
            .notification-badge {
                font-size: 10px;
                line-height: 1;
                animation: pulse 2s infinite;
            }
            
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.7; }
            }
            
            .notification-dropdown {
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            }
            
            .notification-item {
                transition: background-color 0.2s ease;
                cursor: pointer;
            }
            
            .notification-item:hover {
                background-color: #f9fafb;
            }
            
            .notification-item.unread {
                background-color: #eff6ff;
                border-left: 3px solid #3b82f6;
            }
            
            .notification-item.read {
                opacity: 0.8;
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * ໂຫຼດການແຈ້ງເຕືອນຈາກ server
     */
    async loadNotifications(offset = 0, limit = 10) {
        try {
            const response = await fetch(`/temples/api/get-notifications.php?offset=${offset}&limit=${limit}`);
            const data = await response.json();

            if (data.success) {
                if (offset === 0) {
                    this.notifications = data.notifications;
                } else {
                    this.notifications = this.notifications.concat(data.notifications);
                }
                
                this.unreadCount = data.unread_count;
                this.updateUI();
            } else {
                console.error('Error loading notifications:', data.error);
            }
        } catch (error) {
            console.error('Error fetching notifications:', error);
        }
    }

    /**
     * ອັບເດດ UI ການແຈ້ງເຕືອນ
     */
    updateUI() {
        this.updateBadge();
        this.updateNotificationList();
    }

    /**
     * ອັບເດດ badge ຈຳນວນການແຈ້ງເຕືອນ
     */
    updateBadge() {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            if (this.unreadCount > 0) {
                badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    }

    /**
     * ອັບເດດລາຍການການແຈ້ງເຕືອນ
     */
    updateNotificationList() {
        const container = document.getElementById('notificationItems');
        if (!container) return;

        if (this.notifications.length === 0) {
            container.innerHTML = `
                <div class="p-6 text-center text-gray-500">
                    <svg class="mx-auto w-12 h-12 mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <p>ບໍ່ມີການແຈ້ງເຕືອນ</p>
                </div>
            `;
            return;
        }

        container.innerHTML = this.notifications.map(notification => `
            <div class="notification-item p-4 border-b border-gray-100 ${notification.is_read ? 'read' : 'unread'}" 
                 data-id="${notification.id}">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        ${this.getNotificationIcon(notification.type)}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">
                            ${notification.title}
                        </p>
                        <p class="text-sm text-gray-600 mt-1">
                            ${notification.message}
                        </p>
                        <p class="text-xs text-gray-400 mt-2">
                            ${notification.time_ago}
                        </p>
                    </div>
                    ${!notification.is_read ? '<div class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0"></div>' : ''}
                </div>
            </div>
        `).join('');
    }

    /**
     * ໄອຄອນການແຈ້ງເຕືອນຕາມປະເພດ
     */
    getNotificationIcon(type) {
        const icons = {
            'user_approved': `<svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>`,
            'user_rejected': `<svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>`,
            'system': `<svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
            </svg>`,
            'default': `<svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 2L3 7v11a1 1 0 001 1h12a1 1 0 001-1V7l-7-5z"></path>
            </svg>`
        };
        return icons[type] || icons.default;
    }

    /**
     * ຜູກ events
     */
    bindEvents() {
        // ປຸ່ມການແຈ້ງເຕືອນ
        const notificationBtn = document.getElementById('notificationBtn');
        const dropdown = document.getElementById('notificationDropdown');
        
        if (notificationBtn && dropdown) {
            notificationBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdown.classList.toggle('hidden');
            });
        }

        // ປິດ dropdown ເມື່ອຄິກນອກ
        document.addEventListener('click', (e) => {
            if (dropdown && !dropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // ອ່ານທັງໝົດ
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', () => {
                this.markAllAsRead();
            });
        }

        // ໂຫຼດເພີ່ມ
        const loadMoreBtn = document.getElementById('loadMoreBtn');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', () => {
                this.loadNotifications(this.notifications.length);
            });
        }

        // ຄິກການແຈ້ງເຕືອນແຕ່ລະລາຍການ
        document.addEventListener('click', (e) => {
            const notificationItem = e.target.closest('.notification-item');
            if (notificationItem) {
                const notificationId = notificationItem.dataset.id;
                this.markAsRead(notificationId);
            }
        });
    }

    /**
     * ເລີ່ມ auto refresh
     */
    startAutoRefresh() {
        this.refreshInterval = setInterval(() => {
            this.loadNotifications(0, this.notifications.length || 10);
        }, 30000); // ທຸກ 30 ວິນາທີ
    }

    /**
     * ຢຸດ auto refresh
     */
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }

    /**
     * ໝາຍວ່າອ່ານແລ້ວ
     */
    async markAsRead(notificationId) {
        try {
            const response = await fetch('/temples/api/mark-notification-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId })
            });

            const data = await response.json();
            if (data.success) {
                // ອັບເດດ local state
                const notification = this.notifications.find(n => n.id == notificationId);
                if (notification && !notification.is_read) {
                    notification.is_read = true;
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                    this.updateUI();
                }
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    /**
     * ໝາຍວ່າອ່ານທັງໝົດແລ້ວ
     */
    async markAllAsRead() {
        try {
            const response = await fetch('/temples/api/mark-all-notifications-read.php', {
                method: 'POST'
            });

            const data = await response.json();
            if (data.success) {
                // ອັບເດດ local state
                this.notifications.forEach(notification => {
                    notification.is_read = true;
                });
                this.unreadCount = 0;
                this.updateUI();
            }
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
        }
    }

    /**
     * ທຳລາຍລະບົບການແຈ້ງເຕືອນ
     */
    destroy() {
        this.stopAutoRefresh();
        const container = document.querySelector('.notification-container');
        if (container) {
            container.remove();
        }
        this.isInitialized = false;
    }
}

// ສ້າງ instance ຂອງ NotificationManager
const notificationManager = new NotificationManager();

// ເລີ່ມຕົ້ນເມື່ອ DOM ພ້ອມ
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        notificationManager.init();
    });
} else {
    notificationManager.init();
}

// Export ສຳລັບການໃຊ້ງານ
window.NotificationManager = NotificationManager;
window.notificationManager = notificationManager;
