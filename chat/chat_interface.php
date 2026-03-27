<?php
session_start();

// محاكاة بيانات المستخدم إذا لم تكن موجودة
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'مستخدم تجريبي';
    $_SESSION['full_name'] = 'مستخدم تجريبي';
    $_SESSION['role'] = 'student';
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'المستخدم';
$user_type = $_SESSION['user_type'] ?? $_SESSION['role'] ?? 'student';

// تحديد الصفحة الرئيسية حسب نوع المستخدم
$home_page = ($user_type === 'teacher') ? '../teacher/dashboard.php' : '../student/dashboard.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المحادثات - منصة همة التوجيهي</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .chat-container {
            height: calc(100vh - 100px);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            background: white;
        }
        
        .conversations-list {
            height: 100%;
            overflow-y: auto;
            border-left: 1px solid #e9ecef;
            background: #f8f9fa;
        }
        
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .conversation-item:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateX(-5px);
        }
        
        .conversation-item.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .conversation-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        .unread-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 10px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .chat-messages {
            height: calc(100% - 120px);
            overflow-y: auto;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
            animation: fadeInUp 0.3s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message.own {
            justify-content: flex-end;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 12px 18px;
            border-radius: 20px;
            word-wrap: break-word;
            position: relative;
        }
        
        .message.own .message-bubble {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        .message:not(.own) .message-bubble {
            background: white;
            border: 1px solid #e9ecef;
            border-bottom-left-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .message-time {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 5px;
        }
        
        .chat-input {
            padding: 20px;
            border-top: 1px solid #e9ecef;
            background: white;
        }
        
        .chat-input .input-group {
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .chat-input .form-control {
            border: none;
            padding: 15px 20px;
        }
        
        .chat-input .btn {
            border: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 15px 25px;
        }
        
        .empty-chat {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #6c757d;
        }
        
        .search-users {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }
        
        .user-search-result {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 8px;
            margin-bottom: 5px;
        }
        
        .user-search-result:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateX(-3px);
        }
        
        .loading {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }

        .error-message {
            text-align: center;
            padding: 20px;
            color: #dc3545;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 10px;
            margin: 10px;
        }

        .user-role-badge {
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 12px;
            margin-right: 5px;
            font-weight: bold;
        }

        .teacher-badge {
            background: #28a745;
            color: white;
        }

        .student-badge {
            background: #17a2b8;
            color: white;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }

        .card {
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-2px);
        }

        .connection-status {
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 12px;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .connection-status.connected {
            background: #28a745;
            color: white;
        }

        .connection-status.disconnected {
            background: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <!-- حالة الاتصال -->
    <div class="connection-status connected" id="connectionStatus">
        <i class="fas fa-wifi"></i> متصل
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $home_page; ?>">
                <i class="fas fa-graduation-cap"></i>
                همة التوجيهي
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="<?php echo $home_page; ?>">
                    <i class="fas fa-home"></i> الرئيسية
                </a>
                <span class="nav-link">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($user_name); ?>
                </span>
                <a class="nav-link" href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-comments"></i> المحادثات
                        </h5>
                        <button class="btn btn-primary btn-sm" onclick="showNewChatModal()">
                            <i class="fas fa-plus"></i> محادثة جديدة
                        </button>
                    </div>
                    
                    <div class="card-body p-0">
                        <div class="row g-0 chat-container">
                            <!-- قائمة المحادثات -->
                            <div class="col-md-4">
                                <div class="conversations-list">
                                    <!-- البحث عن المستخدمين -->
                                    <div class="search-users" style="display: none;" id="userSearchPanel">
                                        <div class="input-group mb-3">
                                            <input type="text" class="form-control" id="userSearchInput" 
                                                   placeholder="البحث عن مستخدم...">
                                            <button class="btn btn-outline-secondary" onclick="hideUserSearch()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        <div id="userSearchResults"></div>
                                    </div>
                                    
                                    <!-- قائمة المحادثات -->
                                    <div id="conversationsList">
                                        <div class="loading">
                                            <i class="fas fa-spinner fa-spin fa-2x"></i>
                                            <p class="mt-3">جاري التحميل...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- منطقة المحادثة -->
                            <div class="col-md-8">
                                <div class="h-100 d-flex flex-column">
                                    <!-- رأس المحادثة -->
                                    <div class="chat-header p-3 border-bottom bg-light" id="chatHeader" style="display: none;">
                                        <div class="d-flex align-items-center">
                                            <div class="conversation-avatar me-3" id="chatAvatar">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0" id="chatTitle">اسم المحادثة</h6>
                                                <small class="text-muted" id="chatStatus">متصل</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- الرسائل -->
                                    <div class="chat-messages" id="chatMessages">
                                        <div class="empty-chat">
                                            <i class="fas fa-comments fa-4x mb-4" style="color: #667eea;"></i>
                                            <h4>اختر محادثة لبدء المراسلة</h4>
                                            <p class="text-muted">يمكنك إنشاء محادثة جديدة أو اختيار محادثة موجودة</p>
                                        </div>
                                    </div>
                                    
                                    <!-- إدخال الرسالة -->
                                    <div class="chat-input" id="chatInput" style="display: none;">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="messageInput" 
                                                   placeholder="اكتب رسالتك هنا..." maxlength="1000">
                                            <button class="btn btn-primary" onclick="sendMessage()">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal للمحادثة الجديدة -->
    <div class="modal fade" id="newChatModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">محادثة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">البحث عن مستخدم</label>
                        <input type="text" class="form-control" id="modalUserSearch" 
                               placeholder="ابحث بالاسم أو البريد الإلكتروني...">
                    </div>
                    <div id="modalUserResults"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentConversationId = null;
        let messageInterval = null;
        let connectionStatus = true;
        
        // تحميل المحادثات عند بدء الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            loadConversations();
            
            // إعداد البحث في المودال
            document.getElementById('modalUserSearch').addEventListener('input', function() {
                searchUsersInModal(this.value);
            });
            
            // إعداد إرسال الرسالة بالضغط على Enter
            document.getElementById('messageInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendMessage();
                }
            });

            // مراقبة حالة الاتصال
            monitorConnection();
        });
        
        // مراقبة حالة الاتصال
        function monitorConnection() {
            const statusElement = document.getElementById('connectionStatus');
            
            setInterval(async () => {
                try {
                    const response = await fetch('chat_api_simple.php?action=get_unread_counts');
                    if (response.ok) {
                        if (!connectionStatus) {
                            connectionStatus = true;
                            statusElement.className = 'connection-status connected';
                            statusElement.innerHTML = '<i class="fas fa-wifi"></i> متصل';
                            loadConversations(); // إعادة تحميل المحادثات عند الاتصال
                        }
                    } else {
                        throw new Error('Connection failed');
                    }
                } catch (error) {
                    if (connectionStatus) {
                        connectionStatus = false;
                        statusElement.className = 'connection-status disconnected';
                        statusElement.innerHTML = '<i class="fas fa-wifi-slash"></i> منقطع';
                    }
                }
            }, 10000); // فحص كل 10 ثواني
        }
        
        // تحميل قائمة المحادثات
        function loadConversations() {
            fetch('chat_api_simple.php?action=get_conversations')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    displayConversations(data.conversations || []);
                })
                .catch(error => {
                    console.error('خطأ في تحميل المحادثات:', error);
                    document.getElementById('conversationsList').innerHTML = 
                        `<div class="error-message">
                            <i class="fas fa-exclamation-triangle mb-2"></i><br>
                            خطأ في تحميل المحادثات<br>
                            <small>${error.message}</small><br>
                            <button class="btn btn-sm btn-primary mt-2" onclick="loadConversations()">
                                <i class="fas fa-redo"></i> إعادة المحاولة
                            </button>
                        </div>`;
                });
        }
        
        // عرض قائمة المحادثات
        function displayConversations(conversations) {
            const container = document.getElementById('conversationsList');
            
            if (conversations.length === 0) {
                container.innerHTML = `
                    <div class="text-center p-4">
                        <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">لا توجد محادثات بعد</h5>
                        <p class="text-muted">ابدأ محادثة جديدة مع المعلمين أو الطلاب</p>
                        <button class="btn btn-primary btn-sm" onclick="showNewChatModal()">
                            <i class="fas fa-plus"></i> إنشاء محادثة جديدة
                        </button>
                    </div>
                `;
                return;
            }
            
            let html = '';
            conversations.forEach(conversation => {
                const avatar = conversation.other_user_name ? 
                    conversation.other_user_name.charAt(0).toUpperCase() : 'M';
                
                const displayName = conversation.title || conversation.other_user_name || 'محادثة';
                const roleClass = conversation.other_user_type === 'teacher' ? 'teacher-badge' : 'student-badge';
                const roleText = conversation.other_user_type === 'teacher' ? 'معلم' : 'طالب';
                
                html += `
                    <div class="conversation-item" onclick="selectConversation(${conversation.id}, '${escapeHtml(displayName)}')">
                        ${conversation.unread_count > 0 ? 
                            `<div class="unread-badge">${conversation.unread_count}</div>` : 
                            ''
                        }
                        <div class="d-flex align-items-center">
                            <div class="conversation-avatar me-3">
                                ${avatar}
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            ${escapeHtml(displayName)}
                                            ${conversation.other_user_type ? 
                                                `<span class="user-role-badge ${roleClass}">${roleText}</span>` : 
                                                ''
                                            }
                                        </h6>
                                    </div>
                                </div>
                                <p class="mb-1 text-muted small">${escapeHtml(conversation.last_message || 'لا توجد رسائل')}</p>
                                <small class="text-muted">${conversation.last_message_formatted || ''}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // باقي الدوال تبقى كما هي مع تحديث مسار API
        function selectConversation(conversationId, title) {
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
            
            event.currentTarget.classList.add('active');
            
            currentConversationId = conversationId;
            
            document.getElementById('chatHeader').style.display = 'block';
            document.getElementById('chatTitle').textContent = title;
            document.getElementById('chatInput').style.display = 'block';
            
            loadMessages(conversationId);
            
            if (messageInterval) {
                clearInterval(messageInterval);
            }
            messageInterval = setInterval(() => {
                loadMessages(conversationId, false);
            }, 3000);
            
            markAsRead(conversationId);
        }
        
        function loadMessages(conversationId, scrollToBottom = true) {
            fetch(`chat_api_simple.php?action=get_messages&conversation_id=${conversationId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    displayMessages(data.messages || [], scrollToBottom);
                })
                .catch(error => {
                    console.error('خطأ في تحميل الرسائل:', error);
                    document.getElementById('chatMessages').innerHTML = 
                        `<div class="error-message">
                            <i class="fas fa-exclamation-triangle"></i><br>
                            خطأ في تحميل الرسائل: ${error.message}
                        </div>`;
                });
        }
        
        function displayMessages(messages, scrollToBottom = true) {
            const container = document.getElementById('chatMessages');
            
            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="empty-chat">
                        <i class="fas fa-comments fa-4x mb-4" style="color: #667eea;"></i>
                        <h4>لا توجد رسائل بعد</h4>
                        <p class="text-muted">ابدأ المحادثة بإرسال رسالة</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            messages.forEach(message => {
                html += `
                    <div class="message ${message.is_own ? 'own' : ''}">
                        <div class="message-bubble">
                            <div class="message-text">${escapeHtml(message.message)}</div>
                            <div class="message-time">${message.formatted_time}</div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            
            if (scrollToBottom) {
                container.scrollTop = container.scrollHeight;
            }
        }
        
        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message || !currentConversationId) {
                return;
            }
            
            input.disabled = true;
            
            fetch('chat_api_simple.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'send_message',
                    conversation_id: currentConversationId,
                    message: message
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    input.value = '';
                    loadMessages(currentConversationId);
                    loadConversations();
                } else {
                    throw new Error(data.error || 'خطأ غير معروف');
                }
            })
            .catch(error => {
                console.error('خطأ في إرسال الرسالة:', error);
                alert('خطأ في إرسال الرسالة: ' + error.message);
            })
            .finally(() => {
                input.disabled = false;
                input.focus();
            });
        }
        
        function showNewChatModal() {
            const modal = new bootstrap.Modal(document.getElementById('newChatModal'));
            modal.show();
        }
        
        function searchUsersInModal(query) {
            if (query.length < 2) {
                document.getElementById('modalUserResults').innerHTML = '';
                return;
            }
            
            fetch(`chat_api_simple.php?action=search_users&query=${encodeURIComponent(query)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    displayUserSearchResults(data.users || [], 'modalUserResults');
                })
                .catch(error => {
                    console.error('خطأ في البحث:', error);
                    document.getElementById('modalUserResults').innerHTML = 
                        `<div class="error-message">خطأ في البحث: ${error.message}</div>`;
                });
        }
        
        function displayUserSearchResults(users, containerId) {
            const container = document.getElementById(containerId);
            
            if (users.length === 0) {
                container.innerHTML = '<div class="text-center text-muted p-3">لا توجد نتائج</div>';
                return;
            }
            
            let html = '';
            users.forEach(user => {
                const roleClass = user.user_type === 'teacher' ? 'teacher-badge' : 'student-badge';
                const roleText = user.display_role || (user.user_type === 'teacher' ? 'معلم' : 'طالب');
                
                html += `
                    <div class="user-search-result d-flex align-items-center" onclick="startConversation(${user.id}, '${escapeHtml(user.name)}')">
                        <div class="conversation-avatar me-3">
                            ${user.name ? user.name.charAt(0).toUpperCase() : 'U'}
                        </div>
                        <div>
                            <h6 class="mb-0">
                                ${escapeHtml(user.name)}
                                <span class="user-role-badge ${roleClass}">${roleText}</span>
                            </h6>
                            <small class="text-muted">${escapeHtml(user.email || '')}</small>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function startConversation(userId, userName) {
            fetch('chat_api_simple.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'create_conversation',
                    participant_id: userId,
                    type: 'private'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('newChatModal')).hide();
                    loadConversations();
                    setTimeout(() => {
                        selectConversation(data.conversation_id, userName);
                    }, 500);
                } else {
                    throw new Error(data.error || 'خطأ غير معروف');
                }
            })
            .catch(error => {
                console.error('خطأ في إنشاء المحادثة:', error);
                alert('خطأ في إنشاء المحادثة: ' + error.message);
            });
        }
        
        function markAsRead(conversationId) {
            fetch('chat_api_simple.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_read&conversation_id=${conversationId}`
            })
            .catch(error => {
                console.error('خطأ في تحديد الرسائل كمقروءة:', error);
            });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        window.addEventListener('beforeunload', function() {
            if (messageInterval) {
                clearInterval(messageInterval);
            }
        });
    </script>
</body>
</html>