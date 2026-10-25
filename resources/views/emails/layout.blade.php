<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f5efe3;font-family:Helvetica,Arial,sans-serif;color:#1f2a23">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5efe3;padding:30px 12px">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:6px;overflow:hidden">
            <tr><td style="background:#13221a;padding:28px;text-align:center">
                <div style="font-family:Georgia,serif;font-size:26px;letter-spacing:6px;color:#f5efe3">{{ strtoupper(setting('store_name', config('app.name'))) }}</div>
            </td></tr>
            <tr><td style="padding:34px 32px">@yield('body')</td></tr>
            <tr><td style="background:#fbf8f2;padding:22px 32px;font-size:12px;color:#6c6f66;text-align:center">
                {{ setting('store_name') }} · {{ setting('store_phone') }} · {{ setting('store_email') }}
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
