<style>
    .login-box {
        margin-top: -10rem;
        padding: 5px;
    }

    .login-card-body {
        padding: 1.5rem 1.8rem 1.6rem;
    }

    .card, .card-body {
        border-radius: .25rem
    }

    .login-btn {
        padding-left: 2rem !important;;
        padding-right: 1.5rem !important;
    }

    .content {
        overflow-x: hidden;
    }

    .form-group .control-label {
        text-align: left;
    }

    .remember{
        margin-top: 1.5rem;
    }
    .card {
        position: relative;        /* 让角标定位到卡片 */
        overflow: hidden;         /* 避免角标被裁切 */
    }

    .corner-ribbon {
        position: absolute;
        top: 18px;
        right: -58px;
        width: 190px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        padding: 0 18px;
        transform: rotate(45deg);
        transform-origin: center;
        background: linear-gradient(135deg, #1677ff, #00c6ff);
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 0.02em;
        line-height: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-transform: none;
        box-shadow: 0 6px 14px rgba(22,119,255,0.35);
        z-index: 10;
        pointer-events: none;
        border-radius: 2px;
    }

    #googleVerifyModal .form-label-group {
        overflow: visible;
    }

    #googleVerifyModal .form-label-group > label {
        background: #fff;
        padding: 0 4px;
        z-index: 2;
    }

    #googleVerifyModal .modal-dialog {
        max-width: 380px;
    }

</style>

@php
    $adminLangKey = trim((string) config('agent-admin.lang_key'));
    $adminTitle = $adminLangKey !== '' && \Illuminate\Support\Facades\Lang::has('admin.' . $adminLangKey) ? __('admin.' . $adminLangKey) : config('agent-admin.title');
    $systemTitle = __('admin.agent_admin_title');
@endphp

<div class="login-page bg-40">
    <div class="login-box">

        <div class="card">
            <span class="corner-ribbon">{{ $systemTitle }}</span>
            <div class="card-body login-card-body shadow-100">
                <div class="login-logo mb-2">
                    {{ $adminTitle }}
                </div>
                <p class="login-box-msg mt-1 mb-1">{{ __('admin.welcome_back') }}</p>
                <form id="login-form" method="POST" action="{{ admin_url('auth/login') }}">

                    <input type="hidden" name="_token" value="{{ csrf_token() }}"/>
                    <input type="hidden" name="captcha" value="" id="captchaVerification"/>
                    <input type="hidden" name="captchaType" value="blockPuzzle" id="blockPuzzle"/>

                    <fieldset class="form-label-group form-group position-relative has-icon-left">
                        <input
                            type="text"
                            class="form-control {{ $errors->has('username') ? 'is-invalid' : '' }}"
                            name="username"
                            placeholder="{{ trans('admin.username') }}"
                            value="{{ old('username') }}"
                            required
                            autofocus
                        >

                        <div class="form-control-position">
                            <i class="feather icon-user"></i>
                        </div>

                        <label for="email">{{ trans('admin.username') }}</label>

                        <div class="help-block with-errors"></div>
                        @if($errors->has('username'))
                            <span class="invalid-feedback text-danger" role="alert">
                                            @foreach($errors->get('username') as $message)
                                    <span class="control-label" for="inputError"><i class="feather icon-x-circle"></i> {{$message}}</span>
                                    <br>
                                @endforeach
                                        </span>
                        @endif
                    </fieldset>

                    <fieldset class="form-label-group form-group position-relative has-icon-left">
                        <input
                            minlength="5"
                            maxlength="20"
                            id="password"
                            type="password"
                            class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}"
                            name="password"
                            placeholder="{{ trans('admin.password') }}"
                            required
                            autocomplete="current-password"
                        >

                        <div class="form-control-position">
                            <i class="feather icon-lock"></i>
                        </div>
                        <label for="password">{{ trans('admin.password') }}</label>

                        <div class="help-block with-errors"></div>
                        @if($errors->has('password'))
                            <span class="invalid-feedback text-danger" role="alert">
                                            @foreach($errors->get('password') as $message)
                                    <span class="control-label" for="inputError"><i class="feather icon-x-circle"></i> {{$message}}</span>
                                    <br>
                                @endforeach
                                            </span>
                        @endif

                    </fieldset>
                    <div class="form-group d-flex justify-content-between align-items-center remember">
                        <div class="text-left">
                            @if(intval(bob_admin_setting('agent_system_login_remember_switch')))
                                <fieldset class="checkbox">
                                    <div class="vs-checkbox-con vs-checkbox-primary">
                                        <input id="remember" name="remember"  value="1" type="checkbox" {{ (!old('username') || old('remember')) ? 'checked' : '' }}>
                                        <span class="vs-checkbox">
                                                        <span class="vs-checkbox--check">
                                                          <i class="vs-icon feather icon-check"></i>
                                                        </span>
                                                    </span>
                                        <span> {{ trans('admin.remember_me') }}</span>
                                    </div>
                                </fieldset>
                            @endif
                        </div>
                        <div class="text-right">
                            {!! \Dcat\Admin\Widgets\Dropdown::make()->button(__('admin.'.config('app.locale')))->options(__('admin.zh_CN'))->options(__('admin.en')) !!}
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary float-right login-btn" style="width: 100%" id="btn">
                        {{ __('admin.login') }}
                    </button>
                    <div id="mpanel1"></div>
                </form>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="googleVerifyModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('auth.agent_login.google_verify') }}</h5>
            </div>
            <div class="modal-body">
                <div id="google-qrcode-box" style="text-align:center;padding-bottom:15px;display:none;"></div>
                <fieldset class="form-label-group form-group position-relative has-icon-left mb-0">
                    <input
                        type="text"
                        id="google_2fa_code"
                        class="form-control"
                        placeholder="{{ __('auth.agent_login.input_google_code') }}"
                        maxlength="6"
                        autocomplete="one-time-code"
                    >

                    <div class="form-control-position">
                        <i class="fa fa-google"></i>
                    </div>

                    <label for="google_2fa_code">{{ __('admin.google_2fa_code') }}</label>
                </fieldset>
                <div class="text-danger mt-1" id="google-verify-error" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('admin.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="submit-google-verify">{{ __('auth.agent_login.confirm_google_verify') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        if (!window.Promise) {
            document.writeln('<script src="https://cdnjs.cloudflare.com/ajax/libs/es6-promise/4.1.1/es6-promise.min.js"><' + '/' + 'script>');
        }
    })();
</script>
<link rel="stylesheet" type="text/css" href="{{asset('vendor/captcha/css/verify.css')}}">
<script src="{{asset('vendor/captcha/js/crypto-js.js')}}"></script>
<script src="{{asset('vendor/captcha/js/ase.js')}}"></script>
<script src="{{asset('vendor/captcha/js/verify.js')}}" ></script>
@php
    $loginMessages = [
        'loginFailed' => __('auth.agent_login.login_failed'),
        'verifySessionExpired' => __('auth.agent_login.verify_session_expired'),
        'inputSixDigitGoogleCode' => __('auth.agent_login.input_six_digit_google_code'),
        'googleVerifyFailed' => __('auth.agent_login.google_verify_failed'),
    ];
@endphp
<script>
    Dcat.ready(function () {
        document.title  = "{{ $adminTitle }}";
        let loginUsername = '';
        let loginPassword = '';
        let loginRemember = 0;
        const loginMessages = {{ Illuminate\Support\Js::from($loginMessages) }};

        let baseUrl = "{{request()->getScheme()."://".config('agent-admin.route.domain')}}/{{config('agent-admin.route.prefix')}}";
        $('#mpanel1').slideVerify({
            baseUrl:baseUrl,
            mode:'pop',
            containerId:'btn',
            imgSize : {
                width: '300px',
                height: '150px',
            },
            barSize:{
                width: '300px',
                height: '40px',
            },
            beforeCheck:function(){
                var name = $('input[name="username"]').val();
                var pass = $('#password').val();
                if (name == '' || pass == '') {
                    Dcat.error(Dcat.lang.username_password_not_empty);
                    return false;
                }
                return true;
            },
            ready : function() {},
            success : function(params) {
                $("#captchaVerification").val(params.captchaVerification);
                $.ajax({
                    url: $('#login-form').attr('action'),
                    type: 'POST',
                    data: $('#login-form').serialize(),
                    dataType: 'json',
                    success: function (res) {
                        if (res.status && res.data && parseInt(res.data.need_2fa || 0) === 1) {
                            loginUsername = $('input[name="username"]').val();
                            loginPassword = $('#password').val();
                            loginRemember = $('#remember').is(':checked') ? 1 : 0;

                            $('#google-verify-error').hide().text('');
                            $('#google_2fa_code').val('');

                            if (parseInt(res.data.bind || 0) === 0 && res.data.qr) {
                                $('#google-qrcode-box').html(res.data.qr).show();
                            } else {
                                $('#google-qrcode-box').hide().html('');
                            }

                            $('#googleVerifyModal').modal({
                                backdrop: 'static',
                                keyboard: false
                            });
                            return;
                        }

                        if (res.status && res.redirect) {
                            window.location.href = res.redirect;
                            return;
                        }

                        Dcat.error(res.message || loginMessages.loginFailed);
                    },
                    error: function (xhr) {
                        let msg = loginMessages.loginFailed;
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        Dcat.error(msg);
                    }
                });
            },
            error : function() {}
        });
        // ajax表单提交
        $('#login-form').form({
            validate: true
        });
        $('.dropdown-item').click(function(event) {
            event.preventDefault();
            var item = $(this).text();
            if(item == '中文' || item == 'Chinese'){
                $.cookie('locale', 'zh_CN', { expires: 365, path: '/' });
                window.location.reload();
            }
            if(item == '英文' || item == 'English'){
                $.cookie('locale', 'en', { expires: 365, path: '/' });
                window.location.reload();
            }
        });

        $('#googleVerifyModal').on('hidden.bs.modal', function () {
            loginUsername = '';
            loginPassword = '';
            loginRemember = 0;
            $('#google_2fa_code').val('');
            $('#google-verify-error').hide().text('');
        });

        $('#submit-google-verify').on('click', function () {
            let code = $.trim($('#google_2fa_code').val());

            if (!loginUsername || !loginPassword) {
                $('#google-verify-error').text(loginMessages.verifySessionExpired).show();
                return;
            }

            if (!/^\d{6}$/.test(code)) {
                $('#google-verify-error').text(loginMessages.inputSixDigitGoogleCode).show();
                return;
            }

            $('#google-verify-error').hide().text('');

            $.ajax({
                url: "/{{ config('agent-admin.route.prefix') }}/auth/verify",
                type: 'POST',
                dataType: 'json',
                data: {
                    _token: "{{ csrf_token() }}",
                    username: loginUsername,
                    password: loginPassword,
                    remember: loginRemember,
                    google_2fa_code: code
                },
                success: function (res) {
                    if (res.status && res.redirect) {
                        loginUsername = '';
                        loginPassword = '';
                        loginRemember = 0;
                        $('#password').val('');
                        window.location.href = res.redirect;
                        return;
                    }

                    $('#google-verify-error').text(res.message || loginMessages.googleVerifyFailed).show();
                },
                error: function (xhr) {
                    let msg = loginMessages.googleVerifyFailed;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    $('#google-verify-error').text(msg).show();
                }
            });
        });
    });
</script>
