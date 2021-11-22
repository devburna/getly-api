<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <title>Password Reset</title>
    <link rel="stylesheet" href="{{asset('css/main.css')}}">
</head>

<body class="bg-light">
    <main class="container bg-light py-3 py-lg-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card bg-white border-0 p-2 p-lg-4">
                    <div class="card-body">
                        <h6 class="lh-lg">Hi, <b>{{explode(' ', $request->user->name)[0]}}</b></h6>
                        <p class="card-text">{{trans('passwords.reset')}}</p>
                    </div>
                    <div class="card-body">
                    </div>
                    <div class="card-footer border-0 bg-transparent">
                        <small>✌🏽 Team <b>{{config('app.name')}}</b></small>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>

</html>
