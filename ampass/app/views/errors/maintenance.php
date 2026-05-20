<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMPass - Updating</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; text-align: center; }
        .container { max-width: 400px; padding: 20px; }
        h1 { font-size: 1.5rem; margin-bottom: 8px; }
        p { color: #94a3b8; line-height: 1.6; }
        .spinner { width: 32px; height: 32px; border: 3px solid #334155; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 16px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <h1>AMPass is updating</h1>
        <p>The server is applying an update. This usually takes less than a minute. Please try again shortly.</p>
    </div>
</body>
</html>
