$(function () {
    // Already logged in? Go straight to the chat.
    $.getJSON('api/csrf.php').done(function (data) {
        $.ajaxSetup({ headers: { 'X-CSRF-Token': data.csrf_token } });

        $.getJSON('api/me.php').done(function () {
            window.location.href = 'chat.html';
        });
    });

    // Tab switching
    $('.tab').on('click', function () {
        $('.tab').removeClass('active');
        $(this).addClass('active');
        $('form').prop('hidden', true);
        $('#' + $(this).data('form')).prop('hidden', false);
        $('#error').prop('hidden', true);
        $('#info').prop('hidden', true);
    });

    // Send a form to the API, then go to whatever step the server says is next
    function submit(form, url) {
        $.post(url, $(form).serialize())
            .done(function (data) {
                goToNextStep(data);
            })
            .fail(function (xhr) {
                showError(xhr);
            });
    }

    $('#login-form').on('submit', function (e) {
        e.preventDefault();
        submit(this, 'api/login.php');
    });

    $('#register-form').on('submit', function (e) {
        e.preventDefault();
        var pwd1 = $('#password1').val();
        var pwd2 = $('#password2').val();

        if (!password_ok(pwd1)) {
            $('#error').text('Password needs 8+ characters, a capital letter and a number').prop('hidden', false);
            return;
        }
        if (pwd1 !== pwd2) {
            $('#error').text('Passwords do not match').prop('hidden', false);
            return;
        }
        submit(this, 'api/register.php');
    });

    // Step: email code
    $('#verify-form').on('submit', function (e) {
        e.preventDefault();
        $('#info').prop('hidden', true);
        submit(this, 'api/verify_email.php');
    });

    $('#resend').on('click', function () {
        $('#error').prop('hidden', true);
        $.post('api/resend_code.php')
            .done(function () {
                showInfo('A new code was sent to your email.');
            })
            .fail(function (xhr) {
                showError(xhr);
            });
    });

    // Step: first authenticator code (setup)
    $('#totp-setup-form').on('submit', function (e) {
        e.preventDefault();
        $('#info').prop('hidden', true);
        submit(this, 'api/totp_enable.php');
    });

    // Step: authenticator code at login
    $('#totp-form').on('submit', function (e) {
        e.preventDefault();
        submit(this, 'api/verify_totp.php');
    });

    // Live password feedback
    document.querySelector("#password1").addEventListener('keyup', function (e) {
        check_password1(e);
    });
    document.querySelector("#password2").addEventListener('keyup', function (e) {
        check_password2(e);
    });
});

// Show the form for the step the server asked for
function goToNextStep(data) {
    if (data.next === 'verify_email') {
        showVerifyForm(data.email_sent);
    } else if (data.next === 'setup_totp') {
        showTotpSetup();
    } else if (data.next === 'verify_totp') {
        showOnly('#totp-form');
    } else {
        window.location.href = 'chat.html';
    }
}

// Hide the tabs, all forms and messages, then show one form
function showOnly(formId) {
    $('.tabs').prop('hidden', true);
    $('form').prop('hidden', true);
    $('#error').prop('hidden', true);
    $('#info').prop('hidden', true);
    $(formId).prop('hidden', false);
}

function showVerifyForm(emailSent) {
    showOnly('#verify-form');
    if (emailSent) {
        showInfo('We sent a 6-digit code to your email.');
    } else {
        $('#error').text('We could not send the email. Press "Send a new code".').prop('hidden', false);
    }
}

// Ask the server for a new secret and draw it as a QR code
function showTotpSetup() {
    showOnly('#totp-setup-form');
    $.post('api/totp_setup.php')
        .done(function (data) {
            $('#totp-qr').empty();
            new QRCode(document.getElementById('totp-qr'), {
                text: data.uri,
                width: 180,
                height: 180
            });
            $('#totp-secret').text(data.secret);
        })
        .fail(function (xhr) {
            showError(xhr);
        });
}

function showInfo(text) {
    $('#info').text(text).prop('hidden', false);
}

function showError(xhr) {
    var message = 'Something went wrong';
    if (xhr.responseJSON) {
        message = xhr.responseJSON.error;
    }
    $('#info').prop('hidden', true);
    $('#error').text(message).prop('hidden', false);
}

function password_ok(pwd) {
    return pwd.length >= 8 && /[A-Z]/.test(pwd) && /[0-9]/.test(pwd);
}

function set_rule(selector, ok) {
    let rule = document.querySelector(selector);
    if (ok) {
        rule.classList.add("ok");
    } else {
        rule.classList.remove("ok");
    }
}

function check_password1(e) {
    let pwd = e.target.value;

    set_rule("#rule-length", pwd.length >= 8);
    set_rule("#rule-upper", /[A-Z]/.test(pwd));
    set_rule("#rule-number", /[0-9]/.test(pwd));

    if (password_ok(pwd)) {
        e.target.classList.remove("weak");
        e.target.classList.add("strong");
    } else {
        e.target.classList.remove("strong");
        e.target.classList.add("weak");
    }
}

function check_password2(e) {
    let pwd1 = document.querySelector("#password1");
    let pwd2 = e.target;
    let message = document.querySelector("#pwd-match");
    if (password_ok(pwd1.value) && (pwd1.value == pwd2.value)) {
        pwd2.classList.add("match");
        pwd2.classList.remove("mismatch");
        message.textContent = "Passwords match";
        message.className = "ok";
    } else {
        pwd2.classList.add("mismatch");
        pwd2.classList.remove("match");
        message.textContent = "Passwords do not match";
        message.className = "";
    }
}