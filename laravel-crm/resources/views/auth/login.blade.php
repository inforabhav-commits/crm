<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container min-vh-100 d-flex align-items-center justify-content-center">
    <section class="card shadow-sm border-0" style="max-width: 420px; width: 100%;">
        <div class="card-body p-4">
            <span class="badge text-bg-primary rounded-1 mb-3">CRM</span>
            <h1 class="h4 mb-1">Sign in</h1>
            <p class="text-muted mb-4">Access the CRM workspace.</p>

            @if ($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="post" action="{{ route('login.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" id="password" type="password" name="password" required autocomplete="current-password">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" id="remember" type="checkbox" name="remember" value="1">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <button class="btn btn-primary w-100" type="submit">Login</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
