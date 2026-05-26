<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - WorkDrive</title>

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/auth.login.css') }}">
    <style>
        .background {
            width: 100%;
            height: 100%;
            background: #ffffffff;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1;
        }

        .background span {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            position: absolute;
            backface-visibility: hidden;
            animation-name: move;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
        }

        input {
            border: 1px solid #ffffffcc;
            box-shadow: 0px 0px 20px 2px #00000011;
            outline: none;
        }

        input:focus {
            border: 1px solid #ffffffff;
            box-shadow: 0px 0px 20px 2px #00000022;
        }

        .login-container {
            position: relative;
            z-index: 2;
        }
    </style>
</head>
<body>
    <div class="background">
        <div class="w-100p h-100p" style="position:relative">
            @for ($i = 0; $i < 5; $i++)
                @php
                    $colors = ['#ac3333cc', '#e87d30cc', '#162f65cc', '#e4aa2ccc', '#37711bcc'];
                    $color = $colors[$i];
                    $top = rand(0, 100);
                    $left = rand(0, 100);
                    $duration = rand(10, 20);
                    $delay = rand(-20, 0);
                    $originX = rand(-25, 25);
                    $originY = rand(-25, 25);
                    $blur = rand(10, 30);
                    $x = rand(0, 1) ? 1 : -1;
                    $shadowX = 40 * $x;
                @endphp
                <span style="
                    top: {{ $top }}%;
                    left: {{ $left }}%;
                    background-color: {{ $color }};
                    animation-duration: {{ $duration }}s;
                    animation-delay: {{ $delay }}s;
                    filter: blur(15px);
                    transform-origin: {{ $originX }}vw {{ $originY }}vh;
                    box-shadow: {{ $shadowX }}vmin 0 {{ $blur }}vmin {{ $color }};
                "></span>
            @endfor
        </div>
    </div>

    <div class="login-container" style="position:fixed; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; padding: 20px; z-index: 2;">
        <div style="position:relative; background-color: #ffffff66; border: 1px solid #ffffffaa; box-shadow:0px 0px 15px 2px #00000011; max-width: 600px; width: 100%; padding: 60px; border-radius: 32px;">
                <div class="w-100p h-100p" style="position:absolute; top:0; left:0; filter:blur(30px); background-color: #ffffffaa; z-index:-1"></div>
                <div class="d-flex-center fd-column" style="z-index:3; width: 100%;">
                    <div style="font-size: 28px; font-weight: 500; color: #1d1d1f; margin-bottom: 12px; text-align: center;">Welcome to WorkDrive!</div>
                    <div style="font-size: 14px; color: #999999; margin-bottom: 35px; text-align: center;">Please sign in to your WorkDrive account</div>

                    @if ($errors->any())
                        <div id="login-error-message" style="color: #d32f2f; margin-bottom: 20px; font-size: 13px; text-align: center;">
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @else
                        <div id="login-error-message" style="color: #d32f2f;"></div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" style="width: 100%;">
                        @csrf
                        <div style="margin-bottom: 20px;">
                            <label for="email" style="display: block; font-size: 14px; font-weight: 600; color: #5c5c5c; margin-bottom: 8px;">Email</label>
                            <input type="email" name="email" id="email" style="width: 100%; height: 48px; font-size: 14px; padding: 0 15px; border: 1px solid #e0e0e0; border-radius: 6px; background-color: #f5f5f5; outline: none; transition: 0.2s ease;" placeholder="Enter your email address" value="{{ old('email') }}" required autofocus>
                        </div>

                        <div style="margin-bottom: 25px;">
                            <label for="password" style="display: block; font-size: 14px; font-weight: 600; color: #5c5c5c; margin-bottom: 8px;">Password</label>
                            <div style="position: relative;">
                                <input type="password" name="password" id="password" style="width: 100%; height: 48px; font-size: 14px; padding: 0 15px 0 15px; padding-right: 45px; border: 1px solid #e0e0e0; border-radius: 6px; background-color: #f5f5f5; outline: none; transition: 0.2s ease;" placeholder="Enter your password" required>
                                <button type="button" id="password-toggle" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #333333; font-size: 20px; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;" aria-label="Toggle password visibility">
                                    <i class="ri-eye-line" id="password-icon"></i>
                                </button>
                            </div>
                        </div>

                        <input type="hidden" name="remember" value="1">

                        <button type="submit" style="width: 100%; height: 48px; font-size: 16px; font-weight: 600; color: white; background: linear-gradient(to right, #c84a00, #e85e20); border: none; border-radius: 6px; cursor: pointer; margin-bottom: 20px; transition: 0.2s ease;">Sign In</button>
                    </form>

                    <div style="font-size: 13px; color: #999999; text-align: center;">
                        Need access? <span style="color: #c84a00; font-weight: 600; cursor: pointer;">Contact your administrator</span>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('password-toggle').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('password-icon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.className = 'ri-eye-off-line';
            } else {
                passwordInput.type = 'password';
                icon.className = 'ri-eye-line';
            }
        });
    </script>
    <script src="{{ asset('js/auth-login.js') }}"></script>
</body>
</html>
