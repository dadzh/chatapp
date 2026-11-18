var me = null;            // the logged-in user
var currentId = null;     // id of the open conversation
var lastMessageId = 0;    // id of the newest message on screen
var currentIsGroup = false;

$(function () {
    $.getJSON('api/csrf.php').done(function (tokenData) {
        $.ajaxSetup({ headers: { 'X-CSRF-Token': tokenData.csrf_token } });

        $.getJSON('api/me.php')
            .done(function (data) {
                me = data.user;
                $('#me-name').text(me.display_name);
                loadConversations();
                loadUsers();
                setInterval(poll, 2000);
            })
            .fail(function () {
                window.location.href = 'index.html';
            });
    });
        $('#logout').on('click', function () {
            $.post('api/logout.php').always(function () {
                window.location.href = 'index.html';
            });
        });

        $('#send-form').on('submit', function (e) {
            e.preventDefault();
            sendMessage();
        });
        $('#group-form').on('submit', function (e) {
            e.preventDefault();
            createGroup();
        });
    });

    // Fill the "Chats" list
    function loadConversations() {
        $.getJSON('api/conversations.php').done(function (data) {
            $('#conversation-list').empty();
            data.conversations.forEach(function (c) {
                var label = c.title;
                if (c.unread > 0) {
                    label = label + '  |(' + c.unread + ' unread messages)';
                }
                var li = $('<li>').text(label);
                if (c.id === currentId) {
                    li.addClass('active');
                }
                li.on('click', function () {
                    openConversation(c.id, c.title, c.is_group == 1);
                });
                $('#conversation-list').append(li);
            });
        });
    }

    // Fill the "People" list
    function loadUsers() {
        $.getJSON('api/users.php').done(function (data) {
            $('#user-list').empty();
            data.users.forEach(function (u) {
                var li = $('<li>').text(u.display_name);
                li.on('click', function () {
                    startConversation(u.id, u.display_name);
                });
                $('#user-list').append(li);
            });
            $('#group-members').empty();
            data.users.forEach(function (u) {
                var checkbox = $('<input>').attr('type', 'checkbox').val(u.id);
                var span = $('<span>').text(u.display_name);
                var label = $('<label>').append(checkbox).append(span);
                $('#group-members').append(label);
            });
        });
    }

    // Clicking a person: find or create the chat, then open it
    function startConversation(userId, name) {
        $.post('api/start_conversation.php', { user_id: userId }).done(function (data) {
            openConversation(data.conversation_id, name, false);
        });
    }

    function createGroup() {
        var groupName = $('#group-name').val().trim();

        var ids = [];
        $('#group-members input:checked').each(function () {
            ids.push($(this).val());
        });

        $.post('api/create_group.php', { user_ids: ids, name: groupName })
            .done(function (data) {
                $('#group-name').val('');
                $('#group-members input').prop('checked', false);
                openConversation(data.conversation_id, data.name, true);
            })
            .fail(function (xhr) {
                var message = 'Something went wrong';
                if (xhr.responseJSON) {
                    message = xhr.responseJSON.error;
                }
                alert(message);
            });
    }

    // Show a conversation in the chat area
    function openConversation(id, title, isGroup) {
        currentId = id;
        currentIsGroup = isGroup;
        lastMessageId = 0;
        $('#chat-title').text(title);
        $('#messages').empty();
        $('#send-form').prop('hidden', false);
        loadMessages();
        loadConversations();
    }

    // Fetch messages newer than the last one on screen and add them
    function loadMessages() {
        if (currentId === null) {
            return;
        }
        var requestedId = currentId;
        $.getJSON('api/messages.php', { conversation_id: currentId, after_id: lastMessageId })
            .done(function (data) {
                if (requestedId !== currentId) {
                    return;
                }
                data.messages.forEach(function (m) {
                    if (m.id <= lastMessageId) {
                        return;
                    }
                    var div = $('<div>').addClass('message').text(m.body);
                    if (m.sender_id === me.id) {
                        div.addClass('mine');
                    }
                    if (currentIsGroup && m.sender_id !== me.id) {
                        var sender = $('<div>').addClass('sender').text(m.sender_name);
                        $('#messages').append(sender);
                    }
                    $('#messages').append(div);
                    lastMessageId = m.id;
                });
                if (data.messages.length > 0) {
                    $('#messages').scrollTop($('#messages')[0].scrollHeight);
                }
            });
    }

    function sendMessage() {
        var body = $('#message-input').val().trim();
        if (body === '') {
            return;
        }
        $.post('api/send_message.php', { conversation_id: currentId, body: body }).done(function () {
            $('#message-input').val('');
            loadMessages();
        });
    }

    // Runs every 2 seconds
    function poll() {
        loadMessages();
        loadConversations();
    }